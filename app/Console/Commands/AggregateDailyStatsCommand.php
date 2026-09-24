<?php

namespace App\Console\Commands;

use App\Services\AI\TokenUsageService;
use Illuminate\Console\Command;

class AggregateDailyStatsCommand extends Command
{
    protected $signature = 'fcc:aggregate-usage {--date= : The date to aggregate (YYYY-MM-DD)}';
    protected $description = 'Aggregate raw AI usage logs into fast queryable daily statistics.';

    public function handle(TokenUsageService $usageService): int
    {
        $date = $this->option('date') ?: today()->toDateString();
        $this->info("Aggregating AI usage stats for {$date}...");

        $count = $usageService->runDailyStatsAggregation($date);
        $this->info("Successfully aggregated {$count} distinct metric combinations for {$date}.");

        return self::SUCCESS;
    }
}
