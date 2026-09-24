<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\ModelProvider;
use App\Models\UsageDailyStat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Admin Dashboard statistics & overview (Requirement 6, 82).
     */
    public function index(): JsonResponse
    {
        $today = today()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();

        // Overall stats
        $overallStats = DB::table('ai_usage_logs')
            ->selectRaw('
                COUNT(*) as total_requests,
                COALESCE(SUM(input_tokens), 0) as total_input_tokens,
                COALESCE(SUM(output_tokens), 0) as total_output_tokens,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(estimated_cost), 0) as estimated_cost
            ')
            ->where('status', 'completed')
            ->first();

        // Today stats
        $todayStats = DB::table('ai_usage_logs')
            ->selectRaw('
                COUNT(*) as requests_today,
                COALESCE(SUM(total_tokens), 0) as tokens_today,
                COALESCE(SUM(estimated_cost), 0) as cost_today
            ')
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->first();

        // Month stats
        $monthStats = DB::table('ai_usage_logs')
            ->selectRaw('
                COUNT(*) as requests_month,
                COALESCE(SUM(total_tokens), 0) as tokens_month,
                COALESCE(SUM(estimated_cost), 0) as cost_month
            ')
            ->where('created_at', '>=', $startOfMonth)
            ->where('status', 'completed')
            ->first();

        $failedRequests = AiUsageLog::where('status', 'failed')->count();
        $activeSessions = DB::table('conversations')->where('updated_at', '>=', now()->subHours(1))->count();

        // Top users by tokens & cost
        $topUsers = DB::table('ai_usage_logs')
            ->join('users', 'ai_usage_logs.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as requests'),
                DB::raw('COALESCE(SUM(ai_usage_logs.total_tokens), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(ai_usage_logs.estimated_cost), 0) as estimated_cost'),
            ])
            ->where('ai_usage_logs.status', 'completed')
            ->groupBy(['users.id', 'users.name', 'users.email'])
            ->orderByDesc('total_tokens')
            ->limit(5)
            ->get();

        // Cost and tokens by model
        $costByModel = DB::table('ai_usage_logs')
            ->join('ai_models', 'ai_usage_logs.model_id', '=', 'ai_models.id')
            ->select([
                'ai_models.name',
                DB::raw('COUNT(*) as requests'),
                DB::raw('COALESCE(SUM(ai_usage_logs.total_tokens), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(ai_usage_logs.estimated_cost), 0) as estimated_cost'),
            ])
            ->where('ai_usage_logs.status', 'completed')
            ->groupBy(['ai_models.name'])
            ->orderByDesc('total_tokens')
            ->limit(5)
            ->get();

        // Cost by provider
        $costByProvider = DB::table('ai_usage_logs')
            ->join('model_providers', 'ai_usage_logs.provider_id', '=', 'model_providers.id')
            ->select([
                'model_providers.name',
                DB::raw('COUNT(*) as requests'),
                DB::raw('COALESCE(SUM(ai_usage_logs.total_tokens), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(ai_usage_logs.estimated_cost), 0) as estimated_cost'),
            ])
            ->where('ai_usage_logs.status', 'completed')
            ->groupBy(['model_providers.name'])
            ->orderByDesc('estimated_cost')
            ->limit(5)
            ->get();

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'suspended' => $suspendedUsers,
            ],
            'requests' => [
                'total' => (int) $overallStats->total_requests,
                'today' => (int) $todayStats->requests_today,
                'this_month' => (int) $monthStats->requests_month,
                'failed' => $failedRequests,
                'active_sessions' => $activeSessions,
            ],
            'tokens' => [
                'total_input' => (int) $overallStats->total_input_tokens,
                'total_output' => (int) $overallStats->total_output_tokens,
                'total' => (int) $overallStats->total_tokens,
                'today' => (int) $todayStats->tokens_today,
                'this_month' => (int) $monthStats->tokens_month,
            ],
            'cost' => [
                'total' => (float) $overallStats->estimated_cost,
                'today' => (float) $todayStats->cost_today,
                'this_month' => (float) $monthStats->cost_month,
            ],
            'top_users' => $topUsers,
            'cost_by_model' => $costByModel,
            'cost_by_provider' => $costByProvider,
        ]);
    }

    /**
     * Token Usage Analytics time-series charts (Requirement 7).
     * Filter by: Today, 7 Days, 30 Days, This Month, Last Month, Custom Range.
     */
    public function analytics(Request $request): JsonResponse
    {
        $range = $request->query('range', '30d');
        $now = now();

        [$startDate, $endDate] = match ($range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'custom' => [
                Carbon::parse($request->query('start_date', $now->copy()->subDays(7)->toDateString()))->startOfDay(),
                Carbon::parse($request->query('end_date', $now->toDateString()))->endOfDay(),
            ],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        // Query daily time series
        $dailyRecords = DB::table('ai_usage_logs')
            ->select([
                DB::raw('DATE(created_at) as date_label'),
                DB::raw('COUNT(*) as requests'),
                DB::raw('COALESCE(SUM(input_tokens), 0) as input_tokens'),
                DB::raw('COALESCE(SUM(output_tokens), 0) as output_tokens'),
                DB::raw('COALESCE(SUM(total_tokens), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(estimated_cost), 0) as estimated_cost'),
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->groupBy('date_label')
            ->orderBy('date_label')
            ->get();

        return response()->json([
            'range' => $range,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'timeline' => $dailyRecords,
        ]);
    }
}
