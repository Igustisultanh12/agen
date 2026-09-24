<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserQuota;
use App\Services\AI\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['userGroup', 'quota']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($groupId = $request->query('user_group_id')) {
            $query->where('user_group_id', $groupId);
        }

        $users = $query->latest('id')->paginate(15);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'user'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'user_group_id' => 'nullable|exists:user_groups,id',
            'monthly_token_limit' => 'nullable|integer|min:0',
            'daily_token_limit' => 'nullable|integer|min:0',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'user_group_id' => $validated['user_group_id'] ?? null,
        ]);

        $quota = $this->quotaService->initializeUserQuota($user);

        if (isset($validated['monthly_token_limit'])) {
            $quota->monthly_token_limit = $validated['monthly_token_limit'];
        }
        if (isset($validated['daily_token_limit'])) {
            $quota->daily_token_limit = $validated['daily_token_limit'];
        }
        $quota->save();

        AuditLog::log('create_user', 'User', (string) $user->id, ['name' => $user->name, 'email' => $user->email]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load(['userGroup', 'quota']),
        ], 201);
    }

    /**
     * Admin User Detail view (Requirement 25).
     * Displays profile, usage, quota, projects, conversations, models used, agents used, cost, audit logs.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['userGroup', 'quota']);

        // User projects
        $projects = $user->projects()->latest('id')->limit(10)->get();

        // User conversations
        $conversations = $user->conversations()->with(['model', 'agent'])->latest('id')->limit(10)->get();

        // Models used by user
        $modelsUsed = $user->usageLogs()
            ->join('ai_models', 'ai_usage_logs.model_id', '=', 'ai_models.id')
            ->selectRaw('ai_models.name, COUNT(*) as requests, SUM(total_tokens) as total_tokens, SUM(estimated_cost) as total_cost')
            ->groupBy('ai_models.name')
            ->orderByDesc('total_tokens')
            ->get();

        // Total usage stats
        $usageStats = $user->usageLogs()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as total_requests, SUM(input_tokens) as total_input, SUM(output_tokens) as total_output, SUM(total_tokens) as total_tokens, SUM(estimated_cost) as total_cost')
            ->first();

        // Recent audit logs
        $auditLogs = AuditLog::where('user_id', $user->id)->latest('id')->limit(15)->get();

        $remainingQuota = $this->quotaService->getRemainingQuota($user);

        return response()->json([
            'user' => $user,
            'quota' => $remainingQuota,
            'usage_stats' => [
                'total_requests' => (int) $usageStats->total_requests,
                'total_input_tokens' => (int) $usageStats->total_input,
                'total_output_tokens' => (int) $usageStats->total_output,
                'total_tokens' => (int) $usageStats->total_tokens,
                'total_estimated_cost' => (float) $usageStats->total_cost,
            ],
            'models_used' => $modelsUsed,
            'projects' => $projects,
            'conversations' => $conversations,
            'audit_logs' => $auditLogs,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'user_group_id' => 'nullable|exists:user_groups,id',
            'password' => 'nullable|string|min:8',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'user_group_id' => $validated['user_group_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        AuditLog::log('update_user', 'User', (string) $user->id, $updateData);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->load(['userGroup', 'quota']),
        ]);
    }

    public function toggleSuspend(User $user): JsonResponse
    {
        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $user->update(['status' => $newStatus]);

        AuditLog::log($newStatus === 'suspended' ? 'suspend_user' : 'activate_user', 'User', (string) $user->id);

        if ($newStatus === 'suspended') {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'security',
                'title' => 'Account Suspended',
                'message' => 'Your account has been suspended by an administrator.',
            ]);
        }

        return response()->json([
            'message' => "User status updated to {$newStatus}",
            'user' => $user,
        ]);
    }

    public function updateQuota(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'monthly_token_limit' => 'required|integer|min:0',
            'daily_token_limit' => 'required|integer|min:0',
            'add_bonus_tokens' => 'nullable|integer|min:0',
        ]);

        $quota = UserQuota::firstOrCreate(['user_id' => $user->id]);
        $quota->monthly_token_limit = $validated['monthly_token_limit'];
        $quota->daily_token_limit = $validated['daily_token_limit'];

        if (!empty($validated['add_bonus_tokens'])) {
            $quota->monthly_token_limit += $validated['add_bonus_tokens'];
        }

        $quota->save();

        AuditLog::log('change_quota', 'User', (string) $user->id, [
            'monthly' => $quota->monthly_token_limit,
            'daily' => $quota->daily_token_limit,
        ]);

        return response()->json([
            'message' => 'User quota updated successfully',
            'quota' => $quota,
        ]);
    }

    public function resetQuota(User $user): JsonResponse
    {
        $quota = UserQuota::where('user_id', $user->id)->first();
        if ($quota) {
            $quota->update([
                'used_input_tokens' => 0,
                'used_output_tokens' => 0,
                'used_total_tokens' => 0,
                'used_cost' => 0.0,
            ]);
        }

        AuditLog::log('reset_quota', 'User', (string) $user->id);

        return response()->json(['message' => 'User quota usage has been reset to zero']);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Cannot delete your own account'], 422);
        }

        AuditLog::log('delete_user', 'User', (string) $user->id, ['email' => $user->email]);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
