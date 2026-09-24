<?php

namespace App\Services\AI;

use App\Models\AuditLog;
use App\Models\ModelProvider;
use Illuminate\Support\Facades\Log;

class ProviderService
{
    public function __construct(
        protected NativeGatewayService $nativeGateway,
        protected FccService $fccService
    ) {}

    /**
     * Check health and latency of a single provider directly.
     */
    public function checkProviderHealth(ModelProvider $provider): array
    {
        $health = $this->nativeGateway->pingProvider($provider);

        $provider->update([
            'health_status' => $health['status'],
            'latency_ms' => $health['latency_ms'],
            'last_error' => $health['error'],
            'last_checked_at' => now(),
        ]);

        return [
            'provider_id' => $provider->id,
            'name' => $provider->name,
            'health_status' => $health['status'],
            'latency_ms' => $health['latency_ms'],
            'error' => $health['error'],
        ];
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
