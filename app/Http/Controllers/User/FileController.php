<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\Project\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $files = $this->workspaceService->listFiles($project);

        return response()->json($files);
    }

    public function getContent(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $content = $this->workspaceService->getFileContent($project, $file);

        return response()->json([
            'id' => $file->id,
            'name' => $file->filename,
            'path' => $file->path,
            'extension' => $file->extension,
            'content' => $content,
        ]);
    }

    public function saveContent(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $validated = $request->validate([
            'content' => 'present|string',
        ]);

        $updatedFile = $this->workspaceService->saveFileContent($project, $file, $validated['content'], $request->user());

        return response()->json([
            'message' => 'File saved successfully',
            'file' => $updatedFile,
        ]);
    }

    public function createFile(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $validated = $request->validate([
            'path' => 'required|string|max:500',
            'content' => 'nullable|string',
        ]);

        try {
            $file = $this->workspaceService->createFile($project, $validated['path'], $validated['content'] ?? '', $request->user());

            return response()->json([
                'message' => 'File created successfully',
                'file' => $file,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function createFolder(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $validated = $request->validate([
            'path' => 'required|string|max:500',
        ]);

        try {
            $folder = $this->workspaceService->createFolder($project, $validated['path'], $request->user());

            return response()->json([
                'message' => 'Folder created successfully',
                'folder' => $folder,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function upload(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $request->validate([
            'file' => 'required|file',
            'target_folder' => 'nullable|string',
        ]);

        try {
            $uploaded = $this->workspaceService->uploadFile(
                $project,
                $request->file('file'),
                $request->input('target_folder', ''),
                $request->user()
            );

            return response()->json([
                'message' => 'File uploaded successfully',
                'file' => $uploaded,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $this->workspaceService->deleteFile($project, $file, $request->user());

        return response()->json(['message' => 'File deleted successfully']);
    }

    protected function authorizeProjectAccess($user, Project $project): void
    {
        if ($project->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized project access');
        }
    }
}
