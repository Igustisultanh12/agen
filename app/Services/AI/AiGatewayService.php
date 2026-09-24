<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiGatewayService
{
    public function __construct(
        protected NativeGatewayService $nativeGateway,
        protected FccService $fccService,
        protected ModelService $modelService,
        protected QuotaService $quotaService,
        protected TokenUsageService $tokenUsageService,
        protected ContextManagerService $contextManager
    ) {}

    /**
     * Send a non-streaming AI message with automatic fallback routing.
     */
    public function sendMessage(
        User $user,
        Conversation $conversation,
        string $prompt,
        array $selectedFileIds = [],
        ?string $currentFilePath = null,
        ?string $currentFileContent = null,
        ?string $idempotencyKey = null,
        array $attachments = []
    ): array {
        // 1. Validate User & Quota
        $this->validateUserAccess($user);

        $primaryModel = $conversation->model;
        if (!$primaryModel) {
            throw new \InvalidArgumentException('No AI model specified for this conversation.');
        }

        if (!$user->canAccessModel($primaryModel)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You do not have access to this AI model.');
        }

        // 2. Build Context
        $context = $this->contextManager->buildContext(
            $conversation,
            $prompt,
            $selectedFileIds,
            $currentFilePath,
            $currentFileContent,
            $attachments
        );

        // 3. Check Quota
        if (!$this->quotaService->checkUserQuota($user, $context['estimated_tokens'])) {
            throw new \RuntimeException('Monthly or daily AI token limit has been reached.');
        }

        // 4. Save User Message
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $prompt,
            'model' => $primaryModel->slug,
            'metadata' => !empty($attachments) ? ['attachments' => $attachments] : null,
        ]);

        $internalRequestId = $idempotencyKey ?: (string) Str::uuid();
        $fallbackChain = $this->modelService->getFallbackChain($primaryModel);

        $lastException = null;
        $attemptNum = 0;

        foreach ($fallbackChain as $model) {
            $attemptNum++;
            $startAttempt = microtime(true);

            try {
                $response = $this->nativeGateway->executeChat(
                    $model,
                    $context['messages'],
                    $context['system'],
                    $model->max_tokens,
                    $internalRequestId
                );

                $durationMs = (int) round((microtime(true) - $startAttempt) * 1000);

                // Save Assistant Message
                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'model' => $model->slug,
                    'input_tokens' => $response['input_tokens'],
                    'output_tokens' => $response['output_tokens'],
                    'cached_tokens' => $response['cached_tokens'],
                    'total_tokens' => $response['total_tokens'],
                    'duration_ms' => $durationMs,
                ]);

                // Record usage
                $usageLog = $this->tokenUsageService->recordUsage(
                    $user,
                    $model,
                    $internalRequestId,
                    $response['input_tokens'],
                    $response['output_tokens'],
                    $response['cached_tokens'],
                    0,
                    $durationMs,
                    'completed',
                    $response['usage_source'],
                    $response['provider_request_id'],
                    $conversation->project,
                    $conversation,
                    $assistantMessage
                );

                $this->tokenUsageService->recordAttempt(
                    $usageLog->id,
                    $internalRequestId,
                    $model->provider_id,
                    $model->id,
                    $attemptNum,
                    'success',
                    $durationMs
                );

                // Auto generate title if first user turn
                $this->maybeGenerateTitle($conversation, $prompt);

                return [
                    'message' => $assistantMessage,
                    'usage' => [
                        'input_tokens' => $response['input_tokens'],
                        'output_tokens' => $response['output_tokens'],
                        'total_tokens' => $response['total_tokens'],
                        'estimated_cost' => (float) $usageLog->estimated_cost,
                        'currency' => $usageLog->currency,
                    ],
                    'model' => $model->name,
                    'provider' => $model->provider?->name,
                    'internal_request_id' => $internalRequestId,
                ];
            } catch (\Exception $e) {
                $durationMs = (int) round((microtime(true) - $startAttempt) * 1000);
                Log::warning("Model {$model->slug} failed attempt {$attemptNum}: " . $e->getMessage());

                $lastException = $e;
            }
        }

        // If all fallbacks failed
        throw new \RuntimeException(
            $lastException ? $lastException->getMessage() : 'All available AI models in the fallback chain failed.',
            500,
            $lastException
        );
    }

    /**
     * Create a Server-Sent Events (SSE) StreamedResponse for realtime chat output.
     */
    public function createStreamResponse(
        User $user,
        Conversation $conversation,
        string $prompt,
        array $selectedFileIds = [],
        ?string $currentFilePath = null,
        ?string $currentFileContent = null,
        array $attachments = []
    ): StreamedResponse {
        $this->validateUserAccess($user);

        $primaryModel = $conversation->model;
        if (!$primaryModel) {
            throw new \InvalidArgumentException('No AI model selected for this conversation.');
        }

        if (!$user->canAccessModel($primaryModel)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You do not have permission to use this model.');
        }

        $context = $this->contextManager->buildContext(
            $conversation,
            $prompt,
            $selectedFileIds,
            $currentFilePath,
            $currentFileContent,
            $attachments
        );

        if (!$this->quotaService->checkUserQuota($user, $context['estimated_tokens'])) {
            throw new \RuntimeException('Monthly or daily AI token limit has been reached.');
        }

        // Save User Message
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $prompt,
            'model' => $primaryModel->slug,
            'metadata' => !empty($attachments) ? ['attachments' => $attachments] : null,
        ]);

        $internalRequestId = (string) Str::uuid();
        $fallbackChain = $this->modelService->getFallbackChain($primaryModel);

        return response()->stream(function () use ($user, $conversation, $fallbackChain, $context, $internalRequestId, $prompt) {
            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $hasEmittedTokens = false;
            $attemptNum = 0;
            $streamSuccess = false;

            foreach ($fallbackChain as $model) {
                $attemptNum++;
                $startAttempt = microtime(true);

                // Send initial SSE ping
                echo "event: start\ndata: " . json_encode([
                    'internal_request_id' => $internalRequestId,
                    'model' => $model->name,
                    'attempt' => $attemptNum,
                ]) . "\n\n";
                flush();

                try {
                    $streamResult = $this->nativeGateway->streamChat(
                        $model,
                        $context['messages'],
                        $context['system'],
                        function (string $chunk) use (&$hasEmittedTokens) {
                            $hasEmittedTokens = true;
                            echo "event: chunk\ndata: " . json_encode(['text' => $chunk]) . "\n\n";
                            flush();
                        },
                        $internalRequestId
                    );

                    $durationMs = (int) round((microtime(true) - $startAttempt) * 1000);

                    // Save assistant message
                    $assistantMessage = Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => null,
                        'role' => 'assistant',
                        'content' => $streamResult['content'],
                        'model' => $model->slug,
                        'input_tokens' => $streamResult['input_tokens'],
                        'output_tokens' => $streamResult['output_tokens'],
                        'cached_tokens' => $streamResult['cached_tokens'],
                        'total_tokens' => $streamResult['total_tokens'],
                        'duration_ms' => $durationMs,
                    ]);

                    // Record usage
                    $usageLog = $this->tokenUsageService->recordUsage(
                        $user,
                        $model,
                        $internalRequestId,
                        $streamResult['input_tokens'],
                        $streamResult['output_tokens'],
                        $streamResult['cached_tokens'],
                        0,
                        $durationMs,
                        'completed',
                        'provider',
                        $streamResult['provider_request_id'],
                        $conversation->project,
                        $conversation,
                        $assistantMessage
                    );

                    $this->tokenUsageService->recordAttempt(
                        $usageLog->id,
                        $internalRequestId,
                        $model->provider_id,
                        $model->id,
                        $attemptNum,
                        'success',
                        $durationMs
                    );

                    $this->maybeGenerateTitle($conversation, $prompt);

                    echo "event: done\ndata: " . json_encode([
                        'message_id' => $assistantMessage->id,
                        'input_tokens' => $streamResult['input_tokens'],
                        'output_tokens' => $streamResult['output_tokens'],
                        'total_tokens' => $streamResult['total_tokens'],
                        'estimated_cost' => (float) $usageLog->estimated_cost,
                        'currency' => $usageLog->currency,
                        'duration_ms' => $durationMs,
                    ]) . "\n\n";
                    flush();

                    $streamSuccess = true;
                    break;
                } catch (\Exception $e) {
                    Log::warning("Stream attempt {$attemptNum} for model {$model->slug} failed: " . $e->getMessage());

                    // If tokens were already partially emitted, do not attempt to fallback mid-stream
                    if ($hasEmittedTokens) {
                        echo "event: error\ndata: " . json_encode(['error' => 'Stream interrupted: ' . $e->getMessage()]) . "\n\n";
                        flush();
                        return;
                    }
                }
            }

            if (!$streamSuccess && !$hasEmittedTokens) {
                echo "event: error\ndata: " . json_encode(['error' => 'All AI models in the fallback chain were unavailable. Please verify provider credentials in Admin Settings.']) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Check user status.
     */
    protected function validateUserAccess(User $user): void
    {
        if ($user->isSuspended()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Your account has been suspended. Please contact administrator.');
        }

        if (!$user->isActive()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Your account is currently inactive.');
        }
    }

    /**
     * Auto generate conversation title if not yet titled (Requirement 64).
     */
    public function maybeGenerateTitle(Conversation $conversation, string $userPrompt): void
    {
        if ($conversation->auto_title_generated || $conversation->title !== 'New Chat') {
            return;
        }

        // Generate clean concise title from user prompt
        $clean = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $userPrompt);
        $title = Str::limit(trim($clean), 40, '...');

        if (!empty($title)) {
            $conversation->update([
                'title' => $title,
                'auto_title_generated' => true,
            ]);
        }
    }
}
