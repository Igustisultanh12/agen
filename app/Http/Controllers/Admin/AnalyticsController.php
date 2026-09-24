<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    /**
     * Paginated usage logs with detailed filters.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = AiUsageLog::with(['user', 'model', 'provider', 'project', 'conversation']);

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($modelId = $request->query('model_id')) {
            $query->where('model_id', $modelId);
        }

        if ($providerId = $request->query('provider_id')) {
            $query->where('provider_id', $providerId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('internal_request_id', 'like', "%{$search}%")
                  ->orWhere('provider_request_id', 'like', "%{$search}%");
            });
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->latest('id')->paginate(20);

        return response()->json($logs);
    }

    /**
     * Cost Breakdown by various dimensions (Requirement 40).
     */
    public function costReport(Request $request): JsonResponse
    {
        $dimension = $request->query('by', 'model'); // model, provider, user, project, date
        $range = $request->query('range', '30d');

        $startDate = match ($range) {
            'today' => today()->startOfDay(),
            '7d' => now()->subDays(7)->startOfDay(),
            '30d' => now()->subDays(30)->startOfDay(),
            'this_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            default => now()->subDays(30)->startOfDay(),
        };

        $endDate = now()->endOfDay();

        $query = DB::table('ai_usage_logs')
            ->whereBetween('ai_usage_logs.created_at', [$startDate, $endDate])
            ->where('ai_usage_logs.status', 'completed');

        if ($dimension === 'provider') {
            $rows = $query->join('model_providers', 'ai_usage_logs.provider_id', '=', 'model_providers.id')
                ->select([
                    'model_providers.id',
                    'model_providers.name as label',
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('SUM(ai_usage_logs.input_tokens) as input_tokens'),
                    DB::raw('SUM(ai_usage_logs.output_tokens) as output_tokens'),
                    DB::raw('SUM(ai_usage_logs.total_tokens) as total_tokens'),
                    DB::raw('ROUND(SUM(ai_usage_logs.estimated_cost), 4) as estimated_cost'),
                ])
                ->groupBy(['model_providers.id', 'model_providers.name'])
                ->orderByDesc('estimated_cost')
                ->get();
        } elseif ($dimension === 'user') {
            $rows = $query->join('users', 'ai_usage_logs.user_id', '=', 'users.id')
                ->select([
                    'users.id',
                    'users.name as label',
                    'users.email',
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('SUM(ai_usage_logs.input_tokens) as input_tokens'),
                    DB::raw('SUM(ai_usage_logs.output_tokens) as output_tokens'),
                    DB::raw('SUM(ai_usage_logs.total_tokens) as total_tokens'),
                    DB::raw('ROUND(SUM(ai_usage_logs.estimated_cost), 4) as estimated_cost'),
                ])
                ->groupBy(['users.id', 'users.name', 'users.email'])
                ->orderByDesc('estimated_cost')
                ->get();
        } elseif ($dimension === 'project') {
            $rows = $query->join('projects', 'ai_usage_logs.project_id', '=', 'projects.id')
                ->select([
                    'projects.id',
                    'projects.name as label',
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('SUM(ai_usage_logs.input_tokens) as input_tokens'),
                    DB::raw('SUM(ai_usage_logs.output_tokens) as output_tokens'),
                    DB::raw('SUM(ai_usage_logs.total_tokens) as total_tokens'),
                    DB::raw('ROUND(SUM(ai_usage_logs.estimated_cost), 4) as estimated_cost'),
                ])
                ->groupBy(['projects.id', 'projects.name'])
                ->orderByDesc('estimated_cost')
                ->get();
        } else {
            // Default: by model
            $rows = $query->join('ai_models', 'ai_usage_logs.model_id', '=', 'ai_models.id')
                ->select([
                    'ai_models.id',
                    'ai_models.name as label',
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('SUM(ai_usage_logs.input_tokens) as input_tokens'),
                    DB::raw('SUM(ai_usage_logs.output_tokens) as output_tokens'),
                    DB::raw('SUM(ai_usage_logs.total_tokens) as total_tokens'),
                    DB::raw('ROUND(SUM(ai_usage_logs.estimated_cost), 4) as estimated_cost'),
                ])
                ->groupBy(['ai_models.id', 'ai_models.name'])
                ->orderByDesc('estimated_cost')
                ->get();
        }

        return response()->json([
            'dimension' => $dimension,
            'range' => $range,
            'items' => $rows,
        ]);
    }

    /**
     * Export usage logs as CSV (Requirement 40).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'ai-usage-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Timestamp',
                'User',
                'Provider',
                'Model',
                'Input Tokens',
                'Output Tokens',
                'Total Tokens',
                'Estimated Cost (USD)',
                'Duration (ms)',
                'Status',
                'Internal Request ID',
            ]);

            $query = AiUsageLog::with(['user', 'provider', 'model'])->latest('id')->limit(5000);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->toIso8601String(),
                        $log->user?->name ?? 'N/A',
                        $log->provider?->name ?? 'N/A',
                        $log->model?->name ?? 'N/A',
                        $log->input_tokens,
                        $log->output_tokens,
                        $log->total_tokens,
                        $log->estimated_cost,
                        $log->duration_ms,
                        $log->status,
                        $log->internal_request_id,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
