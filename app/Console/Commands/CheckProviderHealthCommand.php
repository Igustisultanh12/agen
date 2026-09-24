<?php

namespace App\Console\Commands;

use App\Services\AI\ProviderService;
use Illuminate\Console\Command;

class CheckProviderHealthCommand extends Command
{
    protected $signature = 'fcc:check-health';
    protected $description = 'Ping all active AI providers and check latency and health status.';

    public function handle(ProviderService $providerService): int
    {
        $this->info("Checking AI providers health...");
        $results = $providerService->checkAllProvidersHealth();

        foreach ($results as $res) {
            $this->line("Provider {$res['name']}: {$res['health_status']} ({$res['latency_ms']}ms)");
        }

        return self::SUCCESS;
    }
}
