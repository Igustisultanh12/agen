<?php

namespace App\Services\AI;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotaService
{
    /**
     * Check if user has sufficient quota for an incoming request.
     * Uses DB transaction with lockForUpdate to prevent race conditions.
     */
    public function checkUserQuota(User $user, int $estimatedTokens = 1000): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return DB::transaction(function () use ($user, $estimatedTokens) {
            $quota = UserQuota::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$quota) {
                // Initialize default quota from user group or platform default
                $quota = $this->initializeUserQuota($user);
            }

            // Check if monthly limit is reached
            if ($quota->monthly_token_limit > 0 && ($quota->used_total_tokens + $estimatedTokens) > $quota->monthly_token_limit) {
                return false;
            }

            // Check if daily limit is reached
            if ($quota->daily_token_limit > 0) {
                $todayTokens = $this->getTodayTokensUsed($user);
                if (($todayTokens + $estimatedTokens) > $quota->daily_token_limit) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Atomically consume quota after AI response completes.
     */
    public function consumeQuota(User $user, int $tokens, float $cost = 0.0): UserQuota
    {
        return DB::transaction(function () use ($user, $tokens, $cost) {
            $quota = UserQuota::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$quota) {
                $quota = $this->initializeUserQuota($user);
            }

            $previousTokens = $quota->used_total_tokens;
            $quota->used_total_tokens += $tokens;
            $quota->used_cost += $cost;
            $quota->save();

            // Check notification thresholds (50%, 75%, 90%, 100%)
            $this->checkThresholdNotifications($user, $quota, $previousTokens);

            return $quota;
        });
    }

    /**
     * Release quota (e.g. if a request fails or was cancelled).
     */
    public function releaseQuota(User $user, int $tokens, float $cost = 0.0): void
    {
        DB::transaction(function () use ($user, $tokens, $cost) {
            $quota = UserQuota::where('user_id', $user->id)->lockForUpdate()->first();

            if ($quota) {
                $quota->used_total_tokens = max(0, $quota->used_total_tokens - $tokens);
                $quota->used_cost = max(0, (float) $quota->used_cost - $cost);
                $quota->save();
            }
        });
    }

    /**
     * Get remaining quota details for a user.
     */
    public function getRemainingQuota(User $user): array
    {
        $quota = UserQuota::where('user_id', $user->id)->first();
        if (!$quota) {
            $quota = $this->initializeUserQuota($user);
        }

        $monthlyLimit = $quota->monthly_token_limit;
        $monthlyUsed = $quota->used_total_tokens;
        $monthlyRemaining = max(0, $monthlyLimit - $monthlyUsed);
        $monthlyPercent = $monthlyLimit > 0 ? round(($monthlyUsed / $monthlyLimit) * 100, 2) : 0;

        $dailyLimit = $quota->daily_token_limit;
        $dailyUsed = $this->getTodayTokensUsed($user);
        $dailyRemaining = max(0, $dailyLimit - $dailyUsed);
        $dailyPercent = $dailyLimit > 0 ? round(($dailyUsed / $dailyLimit) * 100, 2) : 0;

        return [
            'monthly_token_limit' => $monthlyLimit,
            'monthly_used_tokens' => $monthlyUsed,
            'monthly_remaining_tokens' => $monthlyRemaining,
            'monthly_usage_percentage' => $monthlyPercent,
            'daily_token_limit' => $dailyLimit,
            'daily_used_tokens' => $dailyUsed,
            'daily_remaining_tokens' => $dailyRemaining,
            'daily_usage_percentage' => $dailyPercent,
            'total_cost_usd' => (float) $quota->used_cost,
        ];
    }

    /**
     * Initialize user quota based on group or fallback.
     */
    public function initializeUserQuota(User $user): UserQuota
    {
        $group = $user->userGroup;
        $monthly = $group ? $group->monthly_token_limit : 10000000;
        $daily = $group ? $group->daily_token_limit : 1000000;
        $weekly = $group ? $group->weekly_token_limit : 5000000;

        return UserQuota::firstOrCreate(
            ['user_id' => $user->id],
            [
                'monthly_token_limit' => $monthly,
                'daily_token_limit' => $daily,
                'weekly_token_limit' => $weekly,
                'used_input_tokens' => 0,
                'used_output_tokens' => 0,
                'used_total_tokens' => 0,
                'used_cost' => 0.0,
                'daily_reset_at' => now()->endOfDay(),
                'monthly_reset_at' => now()->endOfMonth(),
            ]
        );
    }

    /**
     * Get tokens used by user today from daily stats / usage logs.
     */
    protected function getTodayTokensUsed(User $user): int
    {
        return (int) DB::table('ai_usage_logs')
            ->where('user_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->where('status', 'completed')
            ->sum('total_tokens');
    }

    /**
     * Check quota percentage thresholds (50%, 75%, 90%, 100%) and dispatch notification.
     */
    protected function checkThresholdNotifications(User $user, UserQuota $quota, int $previousTotal): void
    {
        if ($quota->monthly_token_limit <= 0) {
            return;
        }

        $prevPercent = ($previousTotal / $quota->monthly_token_limit) * 100;
        $currPercent = ($quota->used_total_tokens / $quota->monthly_token_limit) * 100;

        $thresholds = [50, 75, 90, 100];

        foreach ($thresholds as $th) {
            if ($prevPercent < $th && $currPercent >= $th) {
                $title = $th === 100 ? 'Monthly AI Token Limit Reached' : "Quota Alert: {$th}% Used";
                $message = $th === 100
                    ? 'You have reached 100% of your monthly AI token limit. Requests will be blocked until next cycle or quota upgrade.'
                    : "You have consumed {$th}% of your monthly AI tokens ({$quota->used_total_tokens} / {$quota->monthly_token_limit}).";

                Notification::create([
                    'user_id' => $user->id,
                    'type' => $th >= 90 ? 'error' : 'warning',
                    'title' => $title,
                    'message' => $message,
                    'data' => [
                        'threshold' => $th,
                        'used_tokens' => $quota->used_total_tokens,
                        'limit' => $quota->monthly_token_limit,
                    ],
                ]);
            }
        }
    }

    /**
     * Scheduled reset for daily quota.
     */
    public function resetDailyQuota(): int
    {
        // Daily resets are tracked via date partition in usage logs / daily stats
        return 1;
    }

    /**
     * Scheduled reset for monthly quota.
     */
    public function resetMonthlyQuota(): int
    {
        return UserQuota::query()->update([
            'used_input_tokens' => 0,
            'used_output_tokens' => 0,
            'used_total_tokens' => 0,
            'used_cost' => 0.0,
            'monthly_reset_at' => now()->addMonth()->startOfMonth(),
        ]);
    }
}
