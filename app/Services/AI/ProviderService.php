<?php

namespace App\Services\AI;

use App\Models\AuditLog;
use App\Models\ModelProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderService
{
    public function __construct(
        protected FccService $fccService
    ) {}

    /**
     * Check health and latency of a single provider.
     */
    public function checkProviderHealth(ModelProvider $provider): array
    {
        $start = microtime(true);
        try {
            $isHealthy = false;
            $errorMsg = null;

            if ($provider->type === 'ollama') {
                $url = rtrim($provider->base_url, '/') . '/api/tags';
                $resp = Http::timeout(5)->get($url);
                $isHealthy = $resp->successful();
                if (!$isHealthy) $errorMsg = "HTTP {$resp->status()}";
            } elseif ($provider->type === 'lmstudio' || $provider->type === 'llamacpp') {
                $url = rtrim($provider->base_url, '/') . '/models';
                $resp = Http::timeout(5)->get($url);
                $isHealthy = $resp->successful();
                if (!$isHealthy) $errorMsg = "HTTP {$resp->status()}";
            } else {
                // Check via FCC health or pinging base_url
                $fccHealth = $this->fccService->healthCheck();
                $isHealthy = $fccHealth['healthy'];
                if (!$isHealthy) $errorMsg = $fccHealth['error'] ?? 'FCC connection error';
            }

            $latency = (int) round((microtime(true) - $start) * 1000);
            $healthStatus = $isHealthy ? 'healthy' : ($latency > 3000 ? 'warning' : 'offline');

            $provider->update([
                'health_status' => $healthStatus,
                'latency_ms' => $latency,
                'last_error' => $errorMsg,
                'last_checked_at' => now(),
            ]);

            return [
                'provider_id' => $provider->id,
                'name' => $provider->name,
                'health_status' => $healthStatus,
                'latency_ms' => $latency,
                'error' => $errorMsg,
            ];
        } catch (\Exception $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $provider->update([
                'health_status' => 'offline',
                'latency_ms' => $latency,
                'last_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ]);

            return [
                'provider_id' => $provider->id,
                'name' => $provider->name,
                'health_status' => 'offline',
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check health of all active providers.
     */
    public function checkAllProvidersHealth(): array
    {
        $providers = ModelProvider::where('status', 'active')->get();
        $results = [];

        foreach ($providers as $provider) {
            $results[] = $this->checkProviderHealth($provider);
        }

        return $results;
    }

    /**
     * Create or update provider with encrypted API key.
     */
    public function saveProvider(array $data, ?ModelProvider $provider = null): ModelProvider
    {
        $provider = $provider ?? new ModelProvider();

        $provider->name = $data['name'];
        $provider->slug = $data['slug'];
        $provider->type = $data['type'] ?? 'custom';
        $provider->base_url = $data['base_url'];
        $provider->status = $data['status'] ?? 'active';
        $provider->priority = $data['priority'] ?? 1;

        if (!empty($data['api_key'])) {
            $provider->api_key = $data['api_key']; // triggers mutator for encryption
        }

        if (isset($data['settings'])) {
            $provider->settings = $data['settings'];
        }

        $provider->save();

        AuditLog::log(
            $provider->wasRecentlyCreated ? 'create_provider' : 'update_provider',
            'ModelProvider',
            (string) $provider->id,
            ['provider_name' => $provider->name, 'type' => $provider->type]
        );

        return $provider;
    }
}
