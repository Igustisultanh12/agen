<?php

namespace App\Services\AI;

use App\Models\ModelProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FccService
{
    protected string $baseUrl;
    protected string $authToken;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('fcc.base_url', 'http://127.0.0.1:8082'), '/');
        $this->authToken = config('fcc.auth_token', 'freecc');
        $this->timeout = (int) config('fcc.timeout', 120);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Check if FCC server is up and healthy.
     */
    public function healthCheck(): array
    {
        $start = microtime(true);
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return [
                    'healthy' => true,
                    'status' => 'healthy',
                    'latency_ms' => $latency,
                    'details' => $response->json() ?? ['status' => 'ok'],
                ];
            }

            return [
                'healthy' => false,
                'status' => 'warning',
                'latency_ms' => $latency,
                'error' => "HTTP {$response->status()}: " . $response->body(),
            ];
        } catch (\Exception $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'healthy' => false,
                'status' => 'offline',
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch active model catalog from FCC.
     */
    public function listModels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->authToken}",
                'x-api-key' => $this->authToken,
            ])->timeout(10)->get("{$this->baseUrl}/v1/models");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::warning('FCC listModels returned error: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('FCC listModels failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count tokens using FCC Anthropic count_tokens endpoint.
     */
    public function countTokens(string $model, array $messages, ?string $system = null): int
    {
        try {
            $payload = [
                'model' => $model,
                'messages' => $messages,
            ];
            if ($system) {
                $payload['system'] = $system;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->authToken}",
                'x-api-key' => $this->authToken,
            ])->timeout(10)->post("{$this->baseUrl}/v1/messages/count_tokens", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return (int) ($data['input_tokens'] ?? 0);
            }
        } catch (\Exception $e) {
            Log::debug('FCC countTokens error: ' . $e->getMessage());
        }

        // Fallback estimation: ~4 chars per token
        $text = $system ?? '';
        foreach ($messages as $msg) {
            $text .= ' ' . ($msg['content'] ?? '');
        }
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Send a non-streaming message to FCC.
     */
    public function createMessage(
        string $model,
        array $messages,
        ?string $system = null,
        int $maxTokens = 4096,
        ?array $tools = null,
        ?string $requestId = null
    ): array {
        $headers = [
            'Authorization' => "Bearer {$this->authToken}",
            'x-api-key' => $this->authToken,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];

        if ($requestId) {
            $headers['x-request-id'] = $requestId;
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];

        if ($system) {
            $payload['system'] = $system;
        }

        if ($tools) {
            $payload['tools'] = $tools;
        }

        $start = microtime(true);
        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/v1/messages", $payload);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if (!$response->successful()) {
            $errorMessage = $this->mapError($response->status(), $response->body());
            throw new \RuntimeException($errorMessage, $response->status());
        }

        $data = $response->json();

        // Extract text content
        $text = '';
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
        $cachedTokens = (int) ($usage['cache_read_input_tokens'] ?? $usage['cached_tokens'] ?? 0);

        return [
            'content' => $text,
            'raw' => $data,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'duration_ms' => $durationMs,
            'usage_source' => (!empty($usage)) ? 'fcc' : 'estimated',
            'provider_request_id' => $response->header('x-request-id') ?? ($data['id'] ?? null),
        ];
    }

    /**
     * Stop generation downstream.
     */
    public function stopGeneration(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->authToken}",
                'x-api-key' => $this->authToken,
            ])->timeout(5)->post("{$this->baseUrl}/stop");

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Map HTTP error status codes into secure, user-friendly messages.
     * Prevents internal credentials/stacktraces leaking to client.
     */
    public function mapError(int $statusCode, string $rawBody): string
    {
        Log::error("FCC Error [{$statusCode}]: {$rawBody}");

        return match ($statusCode) {
            401, 403 => 'AI provider authentication failed. Please check provider credentials in Admin Settings.',
            404 => 'Selected AI model or resource not found on provider.',
            429 => 'AI provider rate limit reached. Please wait a moment or switch to a fallback model.',
            500, 502, 503, 504 => 'AI provider temporarily unavailable. Please try again shortly.',
            default => 'An error occurred while communicating with the AI engine.',
        };
    }
}
