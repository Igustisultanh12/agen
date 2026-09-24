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
        ?string $idempotencyKey = null
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
            $currentFileContent
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
        ]);

        $internalRequestId = $idempotencyKey ?: (string) Str::uuid();
        $fallbackChain = $this->modelService->getFallbackChain($primaryModel);

        $lastException = null;
        $attemptNum = 0;
        $activeUsageLog = null;

        foreach ($fallbackChain as $model) {
            $attemptNum++;
            $startAttempt = microtime(true);

            // Construct provider model ref for FCC (e.g. nvidia_nim/nvidia/nemotron-3-super-120b-a12b)
            $fccModelRef = $model->provider ? "{$model->provider->slug}/{$model->provider_model_id}" : $model->provider_model_id;

            try {
                $fccResponse = $this->fccService->createMessage(
                    $fccModelRef,
                    $context['messages'],
                    $context['system'],
                    $model->max_tokens,
                    null,
                    $internalRequestId
                );

                $durationMs = (int) round((microtime(true) - $startAttempt) * 1000);

                // Save Assistant Message
                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $fccResponse['content'],
                    'model' => $model->slug,
                    'input_tokens' => $fccResponse['input_tokens'],
                    'output_tokens' => $fccResponse['output_tokens'],
                    'cached_tokens' => $fccResponse['cached_tokens'],
                    'total_tokens' => $fccResponse['total_tokens'],
                    'duration_ms' => $durationMs,
                ]);

                // Record usage
                $usageLog = $this->tokenUsageService->recordUsage(
                    $user,
                    $model,
                    $internalRequestId,
                    $fccResponse['input_tokens'],
                    $fccResponse['output_tokens'],
                    $fccResponse['cached_tokens'],
                    0,
                    $durationMs,
                    'completed',
                    $fccResponse['usage_source'],
                    $fccResponse['provider_request_id'],
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
                        'input_tokens' => $fccResponse['input_tokens'],
                        'output_tokens' => $fccResponse['output_tokens'],
                        'total_tokens' => $fccResponse['total_tokens'],
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
        ?string $currentFileContent = null
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
            $currentFileContent
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
        ]);

        $internalRequestId = (string) Str::uuid();
        $fccModelRef = $primaryModel->provider ? "{$primaryModel->provider->slug}/{$primaryModel->provider_model_id}" : $primaryModel->provider_model_id;

        return response()->stream(function () use ($user, $conversation, $primaryModel, $context, $internalRequestId, $fccModelRef, $prompt) {
            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $start = microtime(true);
            $fullContent = '';
            $inputTokens = 0;
            $outputTokens = 0;
            $cachedTokens = 0;
            $providerRequestId = null;

            // Send initial SSE ping
            echo "event: start\ndata: " . json_encode(['internal_request_id' => $internalRequestId, 'model' => $primaryModel->name]) . "\n\n";
            flush();

            try {
                // Connect to FCC Anthropic streaming endpoint
                $url = rtrim(config('fcc.base_url', 'http://127.0.0.1:8082'), '/') . '/v1/messages';
                $payload = json_encode([
                    'model' => $fccModelRef,
                    'messages' => $context['messages'],
                    'system' => $context['system'],
                    'max_tokens' => $primaryModel->max_tokens,
                    'stream' => true,
                ]);

                $headers = [
                    'Authorization: Bearer ' . config('fcc.auth_token', 'freecc'),
                    'x-api-key: ' . config('fcc.auth_token', 'freecc'),
                    'anthropic-version: 2023-06-01',
                    'Content-Type: application/json',
                    'x-request-id: ' . $internalRequestId,
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullContent, &$inputTokens, &$outputTokens, &$cachedTokens, &$providerRequestId) {
                    $lines = explode("\n", $data);
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (str_starts_with($trimmed, 'data: ')) {
                            $jsonStr = substr($trimmed, 6);
                            if ($jsonStr === '[DONE]') {
                                continue;
                            }
                            $eventData = json_decode($jsonStr, true);
                            if ($eventData && isset($eventData['type'])) {
                                if ($eventData['type'] === 'message_start' && isset($eventData['message']['usage'])) {
                                    $inputTokens = $eventData['message']['usage']['input_tokens'] ?? $inputTokens;
                                    $cachedTokens = $eventData['message']['usage']['cache_read_input_tokens'] ?? $cachedTokens;
                                    $providerRequestId = $eventData['message']['id'] ?? $providerRequestId;
                                } elseif ($eventData['type'] === 'content_block_delta' && isset($eventData['delta']['text'])) {
                                    $chunk = $eventData['delta']['text'];
                                    $fullContent .= $chunk;
                                    echo "event: chunk\ndata: " . json_encode(['text' => $chunk]) . "\n\n";
                                    flush();
                                } elseif ($eventData['type'] === 'message_delta' && isset($eventData['usage'])) {
                                    $outputTokens = $eventData['usage']['output_tokens'] ?? $outputTokens;
                                }
                            }
                        }
                    }
                    return strlen($data);
                });

                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $durationMs = (int) round((microtime(true) - $start) * 1000);

                if ($httpCode >= 400 || empty($fullContent)) {
                    // If streaming upstream was unavailable or failed
                    $err = $this->fccService->mapError($httpCode ?: 500, 'Stream disconnected');
                    echo "event: error\ndata: " . json_encode(['error' => $err]) . "\n\n";
                    flush();
                    return;
                }

                // If output tokens not provided in stream, estimate
                if ($outputTokens === 0) {
                    $outputTokens = (int) ceil(mb_strlen($fullContent) / 4);
                }
                if ($inputTokens === 0) {
                    $inputTokens = $context['estimated_tokens'];
                }

                // Save assistant message
                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'model' => $primaryModel->slug,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cached_tokens' => $cachedTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                    'duration_ms' => $durationMs,
                ]);

                // Record usage
                $usageLog = $this->tokenUsageService->recordUsage(
                    $user,
                    $primaryModel,
                    $internalRequestId,
                    $inputTokens,
                    $outputTokens,
                    $cachedTokens,
                    0,
                    $durationMs,
                    'completed',
                    'fcc',
                    $providerRequestId,
                    $conversation->project,
                    $conversation,
                    $assistantMessage
                );

                $this->maybeGenerateTitle($conversation, $prompt);

                echo "event: done\ndata: " . json_encode([
                    'message_id' => $assistantMessage->id,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                    'estimated_cost' => (float) $usageLog->estimated_cost,
                    'currency' => $usageLog->currency,
                    'duration_ms' => $durationMs,
                ]) . "\n\n";
                flush();
            } catch (\Exception $e) {
                Log::error('SSE Stream error: ' . $e->getMessage());
                echo "event: error\ndata: " . json_encode(['error' => 'AI stream disconnected. Please try again.']) . "\n\n";
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
