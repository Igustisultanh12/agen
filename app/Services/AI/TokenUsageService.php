<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AiRequestAttempt;
use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModelProvider;
use App\Models\Project;
use App\Models\UsageDailyStat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TokenUsageService
{
    public function __construct(
        protected CostCalculatorService $costCalculator,
        protected QuotaService $quotaService
    ) {}

    /**
     * Record completed or failed AI request in ai_usage_logs and update aggregate stats.
     */
    public function recordUsage(
        User $user,
        AiModel $model,
        string $internalRequestId,
        int $inputTokens,
        int $outputTokens,
        int $cachedTokens = 0,
        int $reasoningTokens = 0,
        int $durationMs = 0,
        string $status = 'completed',
        string $usageSource = 'estimated',
        ?string $providerRequestId = null,
        ?Project $project = null,
        ?Conversation $conversation = null,
        ?Message $message = null,
        ?string $errorMessage = null,
        ?float $actualCost = null
    ): AiUsageLog {
        $costData = $this->costCalculator->calculateCost(
            $model,
            $inputTokens,
            $outputTokens,
            $cachedTokens,
            $reasoningTokens
        );

        $estimatedCost = $costData['total_cost'];
        $totalTokens = $inputTokens + $outputTokens;

        $log = AiUsageLog::create([
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'conversation_id' => $conversation?->id,
            'message_id' => $message?->id,
            'provider_id' => $model->provider_id,
            'model_id' => $model->id,
            'agent_id' => $conversation?->agent_id,
            'internal_request_id' => $internalRequestId,
            'provider_request_id' => $providerRequestId,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'reasoning_tokens' => $reasoningTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'actual_cost' => $actualCost,
            'currency' => $costData['currency'] ?? 'USD',
            'duration_ms' => $durationMs,
            'status' => $status,
            'usage_source' => $usageSource,
            'error_message' => $errorMessage,
        ]);

        if ($status === 'completed' && $totalTokens > 0) {
            // Update user quota
            $this->quotaService->consumeQuota($user, $totalTokens, $estimatedCost);

            // Update daily aggregate stats
            $this->updateDailyStat(
                today()->toDateString(),
                $user->id,
                $model->provider_id,
                $model->id,
                $inputTokens,
                $outputTokens,
                $totalTokens,
                $estimatedCost
            );
        }

        return $log;
    }

    /**
     * Record an individual attempt in a fallback chain.
     */
    public function recordAttempt(
        int $usageLogId,
        string $internalRequestId,
        int $providerId,
        int $modelId,
        int $attemptNumber,
        string $status,
        int $latencyMs = 0,
        ?string $errorMessage = null
    ): AiRequestAttempt {
        return AiRequestAttempt::create([
            'usage_log_id' => $usageLogId,
            'internal_request_id' => $internalRequestId,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'attempt_number' => $attemptNumber,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Fast atomic update to usage_daily_stats table.
     */
    protected function updateDailyStat(
        string $date,
        int $userId,
        int $providerId,
        int $modelId,
        int $inputTokens,
        int $outputTokens,
        int $totalTokens,
        float $cost
    ): void {
        $stat = UsageDailyStat::firstOrNew([
            'date' => $date,
            'user_id' => $userId,
            'provider_id' => $providerId,
            'model_id' => $modelId,
        ]);

        $stat->requests = ($stat->requests ?? 0) + 1;
        $stat->input_tokens = ($stat->input_tokens ?? 0) + $inputTokens;
        $stat->output_tokens = ($stat->output_tokens ?? 0) + $outputTokens;
        $stat->total_tokens = ($stat->total_tokens ?? 0) + $totalTokens;
        $stat->estimated_cost = round(($stat->estimated_cost ?? 0.0) + $cost, 6);
        $stat->save();
    }

    /**
     * Aggregation scheduler command worker: sync historical logs into daily stats.
     */
    public function runDailyStatsAggregation(?string $date = null): int
    {
        $targetDate = $date ?? today()->toDateString();

        $rows = DB::table('ai_usage_logs')
            ->select([
                'user_id',
                'provider_id',
                'model_id',
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(input_tokens) as total_input'),
                DB::raw('SUM(output_tokens) as total_output'),
                DB::raw('SUM(total_tokens) as total_all'),
                DB::raw('SUM(estimated_cost) as total_cost'),
            ])
            ->whereDate('created_at', $targetDate)
            ->where('status', 'completed')
            ->groupBy(['user_id', 'provider_id', 'model_id'])
            ->get();

        foreach ($rows as $row) {
            UsageDailyStat::updateOrCreate(
                [
                    'date' => $targetDate,
                    'user_id' => $row->user_id,
                    'provider_id' => $row->provider_id,
                    'model_id' => $row->model_id,
                ],
                [
                    'requests' => $row->requests,
                    'input_tokens' => $row->total_input,
                    'output_tokens' => $row->total_output,
                    'total_tokens' => $row->total_all,
                    'estimated_cost' => round((float) $row->total_cost, 6),
                ]
            );
        }

        return count($rows);
    }

    /**
     * Get user usage summary for User Dashboard (Requirement 24).
     */
    public function getUserUsageSummary(User $user): array
    {
        $today = today()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $todayStats = DB::table('ai_usage_logs')
            ->where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(total_tokens), 0) as total_tokens, COALESCE(SUM(estimated_cost), 0) as estimated_cost, COUNT(*) as requests')
            ->first();

        $monthStats = DB::table('ai_usage_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $startOfMonth)
            ->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens, COALESCE(SUM(estimated_cost), 0) as estimated_cost, COUNT(*) as requests')
            ->first();

        $quota = $this->quotaService->getRemainingQuota($user);

        $recentRequests = AiUsageLog::with(['model', 'provider'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'model' => $log->model?->name ?? 'Unknown',
                'provider' => $log->provider?->name ?? 'Unknown',
                'input_tokens' => $log->input_tokens,
                'output_tokens' => $log->output_tokens,
                'total_tokens' => $log->total_tokens,
                'estimated_cost' => (float) $log->estimated_cost,
                'duration_ms' => $log->duration_ms,
                'status' => $log->status,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return [
            'today' => [
                'input_tokens' => (int) $todayStats->input_tokens,
                'output_tokens' => (int) $todayStats->output_tokens,
                'total_tokens' => (int) $todayStats->total_tokens,
                'estimated_cost' => (float) $todayStats->estimated_cost,
                'requests' => (int) $todayStats->requests,
            ],
            'month' => [
                'total_tokens' => (int) $monthStats->total_tokens,
                'estimated_cost' => (float) $monthStats->estimated_cost,
                'requests' => (int) $monthStats->requests,
            ],
            'quota' => $quota,
            'recent_requests' => $recentRequests,
        ];
    }
}
