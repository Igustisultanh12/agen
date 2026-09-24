<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\ProjectFile;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ContextManagerService
{
    /**
     * Assemble system prompt and messages context with token budgeting.
     *
     * @param array<int> $selectedFileIds
     * @return array{
     *     system: string,
     *     messages: array<array{role: string, content: string}>,
     *     estimated_tokens: int
     * }
     */
    public function buildContext(
        Conversation $conversation,
        string $userPrompt,
        array $selectedFileIds = [],
        ?string $currentFilePath = null,
        ?string $currentFileContent = null,
        array $attachments = []
    ): array {
        $user = $conversation->user;

        // 1. Build System Instruction
        $defaultSystemPrompt = SystemSetting::get(
            'ai.default_system_prompt',
            'You are an expert AI software engineer and coding assistant integrated into Free Claude Code Workspace. Write clean, modular, and maintainable code. Follow best practices for the chosen technologies.'
        );

        $customInstructions = $user->custom_instructions ?? '';
        $agentInstructions = $conversation->agent?->description ? "\nActive Coding Agent: {$conversation->agent->name} ({$conversation->agent->harness_type})" : '';

        $systemParts = [
            $defaultSystemPrompt,
            $agentInstructions,
        ];

        if (!empty($customInstructions)) {
            $systemParts[] = "\nUser Custom Instructions:\n" . $customInstructions;
        }

        // 2. Add Project & Files Context (Budgeted)
        $filesContext = '';
        if ($conversation->project_id) {
            $project = $conversation->project;
            $filesContext .= "\nProject: {$project->name}\nWorkspace Path: {$project->workspace_path}\n";

            if (!empty($selectedFileIds)) {
                $files = ProjectFile::where('project_id', $conversation->project_id)
                    ->whereIn('id', $selectedFileIds)
                    ->where('is_directory', false)
                    ->get();

                foreach ($files as $file) {
                    $content = $this->readFileContent($file);
                    if ($content !== null) {
                        // Limit individual file preview to ~8,000 characters to conserve context
                        $truncated = mb_substr($content, 0, 8000);
                        if (mb_strlen($content) > 8000) {
                            $truncated .= "\n... [truncated]";
                        }
                        $filesContext .= "\n--- File: {$file->path} ---\n```\n{$truncated}\n```\n";
                    }
                }
            }

            if ($currentFilePath && $currentFileContent) {
                $truncated = mb_substr($currentFileContent, 0, 10000);
                $filesContext .= "\n--- Active Open File: {$currentFilePath} ---\n```\n{$truncated}\n```\n";
            }
        }

        if (!empty($filesContext)) {
            $systemParts[] = "\nProject Workspace Context:\n" . $filesContext;
        }

        $systemPrompt = implode("\n", array_filter($systemParts));

        // 3. Assemble Conversation History
        $historyMessages = [];
        $recentMessages = $conversation->messages()
            ->latest('id')
            ->limit(20) // Keep last 20 messages for context
            ->get()
            ->reverse();

        foreach ($recentMessages as $msg) {
            $role = in_array($msg->role, ['user', 'assistant']) ? $msg->role : 'user';
            $historyMessages[] = [
                'role' => $role,
                'content' => $msg->content,
            ];
        }

        // 2b. Add Direct File Attachments (Images, PDFs, Documents)
        $attachmentsText = '';
        if (!empty($attachments)) {
            $attachmentsText .= "\n\n=== FILE & DOKUMEN DILAMPIRKAN PENGGUNA ===";
            foreach ($attachments as $att) {
                $name = $att['name'] ?? 'lampiran';
                $size = isset($att['size']) ? round($att['size'] / 1024, 1) . ' KB' : '';
                $isImg = !empty($att['is_image']);

                if ($isImg) {
                    $attachmentsText .= "\n[Foto/Gambar: {$name} ({$size})]";
                } elseif (!empty($att['text_content'])) {
                    $attachmentsText .= "\n[Dokumen: {$name} ({$size})]:\n```\n{$att['text_content']}\n```";
                } else {
                    $attachmentsText .= "\n[File: {$name} ({$size})]";
                }
            }
            $attachmentsText .= "\n=== AKHIR LAMPIRAN ===\n";
        }

        // Add current prompt with attached files
        $historyMessages[] = [
            'role' => 'user',
            'content' => $userPrompt . $attachmentsText,
            'attachments' => $attachments,
        ];

        // 4. Token estimation
        $totalChars = mb_strlen($systemPrompt);
        foreach ($historyMessages as $hm) {
            $totalChars += mb_strlen($hm['content']);
        }
        $estimatedTokens = (int) ceil($totalChars / 4);

        return [
            'system' => $systemPrompt,
            'messages' => $historyMessages,
            'estimated_tokens' => $estimatedTokens,
            'attachments' => $attachments,
        ];
    }

    /**
     * Read file content from local disk storage.
     */
    protected function readFileContent(ProjectFile $file): ?string
    {
        if ($file->storage_path && Storage::disk('local')->exists($file->storage_path)) {
            return Storage::disk('local')->get($file->storage_path);
        }

        return null;
    }
}
