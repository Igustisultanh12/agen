<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\ModelProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NativeGatewayService
{
    protected int $defaultTimeout;

    public function __construct()
    {
        $this->defaultTimeout = (int) config('fcc.timeout', 120);
    }

    /**
     * Resolve the chat completions endpoint URL for any provider.
     */
    public function resolveChatEndpoint(ModelProvider $provider): string
    {
        $baseUrl = rtrim($provider->base_url, '/');

        // Explicit chat completions or messages
        if (str_ends_with($baseUrl, '/chat/completions') || str_ends_with($baseUrl, '/messages')) {
            return $baseUrl;
        }

        // FCC Proxy or Anthropic protocol
        if ($provider->type === 'fcc' || str_contains($baseUrl, '8082')) {
            return "{$baseUrl}/v1/messages";
        }
        if ($provider->type === 'anthropic') {
            return "{$baseUrl}/v1/messages";
        }

        // Ollama OpenAI-compatible endpoint
        if ($provider->type === 'ollama') {
            return str_ends_with($baseUrl, '/v1') ? "{$baseUrl}/chat/completions" : "{$baseUrl}/v1/chat/completions";
        }

        // Standard OpenAI-compatible (NVIDIA NIM, OpenRouter, Groq, DeepSeek, Gemini, LM Studio)
        if (str_ends_with($baseUrl, '/v1') || str_ends_with($baseUrl, '/openai')) {
            return "{$baseUrl}/chat/completions";
        }

        return "{$baseUrl}/chat/completions";
    }

    /**
     * Resolve the models / health check endpoint URL for a provider.
     */
    public function resolveModelsEndpoint(ModelProvider $provider): string
    {
        $baseUrl = rtrim($provider->base_url, '/');

        if ($provider->type === 'ollama') {
            return "{$baseUrl}/api/tags";
        }
        if ($provider->type === 'fcc' || str_contains($baseUrl, '8082')) {
            return "{$baseUrl}/health";
        }
        if ($provider->type === 'lmstudio') {
            return str_ends_with($baseUrl, '/v1') ? "{$baseUrl}/models" : "{$baseUrl}/v1/models";
        }
        if (str_ends_with($baseUrl, '/v1') || str_ends_with($baseUrl, '/openai')) {
            return "{$baseUrl}/models";
        }

        return "{$baseUrl}/v1/models";
    }

    /**
     * Build appropriate authentication and routing headers for a provider.
     */
    public function buildHeaders(ModelProvider $provider, ?string $requestId = null): array
    {
        $apiKey = $provider->api_key_decrypted;
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($requestId) {
            $headers['x-request-id'] = $requestId;
        }

        if ($provider->type === 'fcc' || str_contains($provider->base_url, '8082')) {
            $token = $apiKey ?: config('fcc.auth_token', 'freecc');
            $headers['Authorization'] = "Bearer {$token}";
            $headers['x-api-key'] = $token;
            $headers['anthropic-version'] = '2023-06-01';
        } elseif ($provider->type === 'anthropic') {
            if ($apiKey) {
                $headers['x-api-key'] = $apiKey;
            }
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            // Standard OpenAI-compatible (NVIDIA NIM, OpenRouter, Groq, DeepSeek, Gemini, etc.)
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            if ($provider->type === 'open_router' || $provider->type === 'openrouter' || str_contains($provider->base_url, 'openrouter')) {
                $headers['HTTP-Referer'] = config('app.url', 'https://ai.site');
                $headers['X-Title'] = config('app.name', 'Agen AI Workspace');
            }
        }

        return $headers;
    }

    /**
     * Format payload according to provider requirements (OpenAI vs Anthropic).
     */
    public function formatPayload(AiModel $model, array $messages, ?string $system = null, bool $stream = false): array
    {
        $provider = $model->provider;
        $isAnthropicOrFcc = ($provider && ($provider->type === 'fcc' || $provider->type === 'anthropic' || str_contains($provider->base_url, '8082')));

        $maxTokens = $model->max_tokens ?: 4096;

        if ($isAnthropicOrFcc) {
            $payload = [
                'model' => $model->provider_model_id,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'stream' => $stream,
            ];
            if ($system) {
                $payload['system'] = $system;
            }
            return $payload;
        }

        // OpenAI format
        $formattedMessages = [];
        if (!empty($system)) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $payload = [
            'model' => $model->provider_model_id,
            'messages' => $formattedMessages,
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
            'stream' => $stream,
        ];

        if ($stream) {
            $payload['stream_options'] = ['include_usage' => true];
        }

        return $payload;
    }

    /**
     * Send a non-streaming chat request directly to the upstream AI provider.
     */
    public function executeChat(
        AiModel $model,
        array $messages,
        ?string $system = null,
        int $maxTokens = 4096,
        ?string $requestId = null
    ): array {
        $provider = $model->provider;
        if (!$provider) {
            throw new \RuntimeException("Model {$model->name} has no provider configured.");
        }

        $url = $this->resolveChatEndpoint($provider);
        $headers = $this->buildHeaders($provider, $requestId);
        $payload = $this->formatPayload($model, $messages, $system, false);
        $payload['max_tokens'] = $maxTokens;

        $start = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->defaultTimeout)
                ->post($url, $payload);
        } catch (\Exception $e) {
            Log::error("Direct AI Gateway connection exception for {$provider->name}: {$e->getMessage()}");
            throw new \RuntimeException("Connection to {$provider->name} failed: " . $e->getMessage(), 503, $e);
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if (!$response->successful()) {
            $errorMsg = $this->mapError($response->status(), $response->body());
            Log::warning("AI Request to {$provider->name} ({$url}) failed [{$response->status()}]: {$response->body()}");
            throw new \RuntimeException($errorMsg, $response->status());
        }

        $data = $response->json();
        $isAnthropicOrFcc = ($provider->type === 'fcc' || $provider->type === 'anthropic' || str_contains($provider->base_url, '8082'));

        $text = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $cachedTokens = 0;

        if ($isAnthropicOrFcc) {
            if (isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $block) {
                    if (isset($block['type']) && $block['type'] === 'text') {
                        $text .= $block['text'] ?? '';
                    }
                }
            }
            $usage = $data['usage'] ?? [];
            $inputTokens = (int) ($usage['input_tokens'] ?? 0);
            $outputTokens = (int) ($usage['output_tokens'] ?? 0);
            $cachedTokens = (int) ($usage['cache_read_input_tokens'] ?? ($usage['cached_tokens'] ?? 0));
        } else {
            // OpenAI format
            $text = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];
            $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
            $outputTokens = (int) ($usage['completion_tokens'] ?? 0);
            $cachedTokens = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        }

        // Fallback token calculation if provider did not return usage
        if ($inputTokens === 0) {
            $inputTokens = $this->estimateTokens($messages, $system);
        }
        if ($outputTokens === 0) {
            $outputTokens = (int) ceil(mb_strlen($text) / 4);
        }

        $headerReqId = $response->header('x-request-id');
        $providerReqId = !empty($headerReqId) ? $headerReqId : ($data['id'] ?? null);

        return [
            'content' => $text,
            'raw' => $data,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'duration_ms' => $durationMs,
            'usage_source' => (!empty($data['usage'])) ? 'provider' : 'estimated',
            'provider_request_id' => $providerReqId,
        ];
    }

    /**
     * Stream AI chat completion directly using cURL Server-Sent Events (SSE).
     *
     * @param callable(string): void $onChunk
     */
    public function streamChat(
        AiModel $model,
        array $messages,
        ?string $system,
        callable $onChunk,
        ?string $requestId = null
    ): array {
        $provider = $model->provider;
        if (!$provider) {
            throw new \RuntimeException("Model {$model->name} has no provider configured.");
        }

        $url = $this->resolveChatEndpoint($provider);
        $headers = $this->buildHeaders($provider, $requestId);
        $payload = $this->formatPayload($model, $messages, $system, true);

        // Convert associative headers array to curl format ("Header: Value")
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $start = microtime(true);
        $fullContent = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $cachedTokens = 0;
        $providerRequestId = null;
        $streamBuffer = '';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->defaultTimeout);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (
            &$fullContent,
            &$inputTokens,
            &$outputTokens,
            &$cachedTokens,
            &$providerRequestId,
            &$streamBuffer,
            $onChunk
        ) {
            $streamBuffer .= $data;
            $lines = explode("\n", $streamBuffer);
            $streamBuffer = array_pop($lines); // hold last line if incomplete

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (!str_starts_with($trimmed, 'data: ')) {
                    continue;
                }

                $jsonStr = trim(substr($trimmed, 6));
                if ($jsonStr === '[DONE]' || empty($jsonStr)) {
                    continue;
                }

                $eventData = json_decode($jsonStr, true);
                if (!$eventData) {
                    continue;
                }

                // 1. OpenAI-compatible chunk
                if (isset($eventData['choices'][0]['delta'])) {
                    $delta = $eventData['choices'][0]['delta'];
                    if (isset($delta['content']) && $delta['content'] !== '') {
                        $chunk = $delta['content'];
                        $fullContent .= $chunk;
                        $onChunk($chunk);
                    }
                }

                if (isset($eventData['usage'])) {
                    $inputTokens = $eventData['usage']['prompt_tokens'] ?? $inputTokens;
                    $outputTokens = $eventData['usage']['completion_tokens'] ?? $outputTokens;
                }

                // 2. Anthropic / FCC event chunk
                if (isset($eventData['type'])) {
                    if ($eventData['type'] === 'content_block_delta' && isset($eventData['delta']['text'])) {
                        $chunk = $eventData['delta']['text'];
                        $fullContent .= $chunk;
                        $onChunk($chunk);
                    } elseif ($eventData['type'] === 'message_start' && isset($eventData['message']['usage'])) {
                        $inputTokens = $eventData['message']['usage']['input_tokens'] ?? $inputTokens;
                        $cachedTokens = $eventData['message']['usage']['cache_read_input_tokens'] ?? $cachedTokens;
                        $providerRequestId = $eventData['message']['id'] ?? $providerRequestId;
                    } elseif ($eventData['type'] === 'message_delta' && isset($eventData['usage'])) {
                        $outputTokens = $eventData['usage']['output_tokens'] ?? $outputTokens;
                    }
                }

                if (isset($eventData['id']) && !$providerRequestId) {
                    $providerRequestId = $eventData['id'];
                }
            }

            return strlen($data);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if ($httpCode >= 400 || !empty($curlError)) {
            $msg = !empty($curlError) ? $curlError : "HTTP {$httpCode}";
            Log::warning("Direct AI Gateway stream failed: {$msg}");
            throw new \RuntimeException($this->mapError($httpCode ?: 500, $msg), $httpCode ?: 500);
        }

        if ($outputTokens === 0) {
            $outputTokens = (int) ceil(mb_strlen($fullContent) / 4);
        }
        if ($inputTokens === 0) {
            $inputTokens = $this->estimateTokens($messages, $system);
        }

        return [
            'content' => $fullContent,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'duration_ms' => $durationMs,
            'provider_request_id' => $providerRequestId,
        ];
    }

    /**
     * Test provider connectivity, latency, and credentials directly.
     */
    public function pingProvider(ModelProvider $provider): array
    {
        $start = microtime(true);
        $modelsUrl = $this->resolveModelsEndpoint($provider);
        $headers = $this->buildHeaders($provider);

        // Check if API key is expected for cloud providers
        $isLocal = in_array($provider->type, ['ollama', 'lmstudio', 'llamacpp']);
        $isFcc = ($provider->type === 'fcc' || str_contains($provider->base_url, '8082'));

        if (!$isLocal && !$isFcc && empty($provider->api_key_decrypted)) {
            return [
                'healthy' => true,
                'status' => 'warning',
                'latency_ms' => 0,
                'error' => 'API Key not configured yet. Edit provider to enter key.',
            ];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(6)
                ->get($modelsUrl);

            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return [
                    'healthy' => true,
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'error' => null,
                ];
            }

            // 401 / 403 indicates wrong key
            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'healthy' => false,
                    'status' => 'warning',
                    'latency_ms' => $latency,
                    'error' => "Authentication failed (HTTP {$response->status()}) - check API key.",
                ];
            }

            // If 404 on /models, server is reachable but endpoint differs
            if ($response->status() === 404) {
                return [
                    'healthy' => true,
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'error' => null,
                ];
            }

            return [
                'healthy' => false,
                'status' => 'warning',
                'latency_ms' => $latency,
                'error' => "HTTP {$response->status()}: " . substr($response->body(), 0, 100),
            ];
        } catch (\Exception $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'healthy' => false,
                'status' => 'offline',
                'latency_ms' => $latency,
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fast token estimator (~4 chars per token).
     */
    public function estimateTokens(array $messages, ?string $system = null): int
    {
        $text = $system ?? '';
        foreach ($messages as $msg) {
            $text .= ' ' . ($msg['content'] ?? '');
        }
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Map error codes into user-friendly strings.
     */
    public function mapError(int $statusCode, string $rawBody): string
    {
        return match ($statusCode) {
            401, 403 => 'AI provider authentication failed. Please verify provider API key in Admin Settings.',
            404 => 'Selected AI model or endpoint not found on upstream provider.',
            429 => 'AI provider rate limit reached. Please retry in a moment or switch to a fallback model.',
            500, 502, 503, 504 => 'AI provider temporarily unavailable. Please retry shortly.',
            default => 'AI request failed (' . ($statusCode ?: 500) . ').',
        };
    }
}
