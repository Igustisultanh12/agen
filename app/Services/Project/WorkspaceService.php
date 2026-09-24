<?php

namespace App\Services\Project;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceService
{
    protected string $disk = 'local';

    // Blacklisted executable/dangerous extensions
    protected array $blockedExtensions = [
        'exe', 'bat', 'cmd', 'sh', 'com', 'msi', 'scr', 'vbs', 'ps1', 'jar'
    ];

    /**
     * Get or create physical directory for project workspace.
     */
    public function ensureProjectDirectory(Project $project): string
    {
        $path = 'workspaces/' . $project->uuid;
        if (!Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->makeDirectory($path);
        }
        return $path;
    }

    /**
     * List files and folders for project.
     */
    public function listFiles(Project $project): array
    {
        $files = ProjectFile::where('project_id', $project->id)
            ->orderBy('is_directory', 'desc')
            ->orderBy('filename', 'asc')
            ->get();

        return $files->map(fn($f) => [
            'id' => $f->id,
            'parent_id' => $f->parent_id,
            'name' => $f->filename,
            'path' => $f->path,
            'is_directory' => (bool) $f->is_directory,
            'size' => $f->size_bytes,
            'extension' => $f->extension,
            'mime_type' => $f->mime_type,
            'updated_at' => $f->updated_at?->toIso8601String(),
        ])->toArray();
    }

    /**
     * Read content of a project file with security check.
     */
    public function getFileContent(Project $project, ProjectFile $file): string
    {
        if ($file->project_id !== $project->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('File does not belong to project.');
        }

        if ($file->is_directory) {
            throw new \InvalidArgumentException('Cannot read contents of a directory.');
        }

        if ($file->storage_path && Storage::disk($this->disk)->exists($file->storage_path)) {
            return Storage::disk($this->disk)->get($file->storage_path);
        }

        return '';
    }

    /**
     * Create a new text/code file.
     */
    public function createFile(Project $project, string $path, string $content = '', ?User $user = null): ProjectFile
    {
        $this->validateSafePath($path);
        $filename = basename($path);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $this->blockedExtensions)) {
            throw new \InvalidArgumentException("Extension .{$ext} is restricted for security.");
        }

        $workspaceDir = $this->ensureProjectDirectory($project);
        $randomStorageName = Str::random(40) . ($ext ? ".{$ext}" : '');
        $storagePath = "{$workspaceDir}/{$randomStorageName}";

        Storage::disk($this->disk)->put($storagePath, $content);
        $size = strlen($content);

        // Check if file already exists in db
        $projectFile = ProjectFile::updateOrCreate(
            ['project_id' => $project->id, 'path' => $path],
            [
                'filename' => $filename,
                'extension' => $ext ?: null,
                'mime_type' => $this->detectMimeType($filename),
                'size_bytes' => $size,
                'storage_path' => $storagePath,
                'is_directory' => false,
            ]
        );

        AuditLog::log('file_create', 'ProjectFile', (string) $projectFile->id, ['project_id' => $project->id, 'path' => $path], $user);

        return $projectFile;
    }

    /**
     * Create folder in workspace.
     */
    public function createFolder(Project $project, string $path, ?User $user = null): ProjectFile
    {
        $this->validateSafePath($path);
        $filename = basename($path);

        $folder = ProjectFile::firstOrCreate(
            ['project_id' => $project->id, 'path' => $path],
            [
                'filename' => $filename,
                'is_directory' => true,
                'size_bytes' => 0,
            ]
        );

        AuditLog::log('folder_create', 'ProjectFile', (string) $folder->id, ['project_id' => $project->id, 'path' => $path], $user);

        return $folder;
    }

    /**
     * Save/update file content (from Monaco Editor).
     */
    public function saveFileContent(Project $project, ProjectFile $file, string $content, ?User $user = null): ProjectFile
    {
        if ($file->project_id !== $project->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('File access denied.');
        }

        if ($file->is_directory) {
            throw new \InvalidArgumentException('Directories have no editable content.');
        }

        $workspaceDir = $this->ensureProjectDirectory($project);
        if (!$file->storage_path) {
            $ext = $file->extension ? ".{$file->extension}" : '';
            $file->storage_path = "{$workspaceDir}/" . Str::random(40) . $ext;
        }

        Storage::disk($this->disk)->put($file->storage_path, $content);
        $file->size_bytes = strlen($content);
        $file->save();

        AuditLog::log('file_update', 'ProjectFile', (string) $file->id, ['project_id' => $project->id, 'path' => $file->path, 'size' => $file->size_bytes], $user);

        return $file;
    }

    /**
     * Upload file with strict MIME and size checks.
     */
    public function uploadFile(Project $project, UploadedFile $uploadedFile, string $targetFolder = '', ?User $user = null): ProjectFile
    {
        $maxSizeBytes = 10 * 1024 * 1024; // 10MB default
        if ($user && $user->userGroup && $user->userGroup->max_file_size_bytes > 0) {
            $maxSizeBytes = $user->userGroup->max_file_size_bytes;
        }

        if ($uploadedFile->getSize() > $maxSizeBytes) {
            throw new \InvalidArgumentException("Uploaded file exceeds limit of " . round($maxSizeBytes / 1048576, 1) . "MB");
        }

        $origName = $uploadedFile->getClientOriginalName();
        $ext = strtolower($uploadedFile->getClientOriginalExtension());

        if (in_array($ext, $this->blockedExtensions)) {
            throw new \InvalidArgumentException("File type .{$ext} is blocked for security.");
        }

        $cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
        $relPath = trim($targetFolder, '/') . ($targetFolder ? '/' : '') . $cleanFilename;
        $this->validateSafePath($relPath);

        $workspaceDir = $this->ensureProjectDirectory($project);
        $randomStorageName = Str::random(40) . ($ext ? ".{$ext}" : '');
        $storagePath = "{$workspaceDir}/{$randomStorageName}";

        Storage::disk($this->disk)->putFileAs($workspaceDir, $uploadedFile, $randomStorageName);

        $projectFile = ProjectFile::updateOrCreate(
            ['project_id' => $project->id, 'path' => $relPath],
            [
                'filename' => $cleanFilename,
                'extension' => $ext ?: null,
                'mime_type' => $uploadedFile->getMimeType(),
                'size_bytes' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
                'is_directory' => false,
            ]
        );

        AuditLog::log('file_upload', 'ProjectFile', (string) $projectFile->id, ['project_id' => $project->id, 'path' => $relPath], $user);

        return $projectFile;
    }

    /**
     * Delete file or directory.
     */
    public function deleteFile(Project $project, ProjectFile $file, ?User $user = null): bool
    {
        if ($file->project_id !== $project->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('File access denied.');
        }

        if ($file->is_directory) {
            // Delete all children recursively
            ProjectFile::where('project_id', $project->id)
                ->where('path', 'like', $file->path . '/%')
                ->delete();
        }

        if ($file->storage_path && Storage::disk($this->disk)->exists($file->storage_path)) {
            Storage::disk($this->disk)->delete($file->storage_path);
        }

        AuditLog::log('file_delete', 'ProjectFile', (string) $file->id, ['project_id' => $project->id, 'path' => $file->path], $user);

        return (bool) $file->delete();
    }

    /**
     * Path traversal protection.
     */
    public function validateSafePath(string $path): void
    {
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new \InvalidArgumentException('Path traversal or invalid path detected.');
        }
    }

    /**
     * Basic MIME detector for source code files.
     */
    protected function detectMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'php' => 'application/x-httpd-php',
            'js', 'mjs', 'cjs' => 'application/javascript',
            'ts', 'tsx' => 'application/typescript',
            'vue' => 'text/x-vue',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'json' => 'application/json',
            'md' => 'text/markdown',
            'py' => 'text/x-python',
            'sql' => 'application/sql',
            'yaml', 'yml' => 'application/x-yaml',
            'txt' => 'text/plain',
            default => 'text/plain',
        };
    }
}
