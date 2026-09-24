<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UserGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = UserGroup::with(['allowedModels', 'allowedAgents'])
            ->withCount('users')
            ->get();

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:user_groups,slug',
            'description' => 'nullable|string',
            'monthly_token_limit' => 'required|integer|min:0',
            'daily_token_limit' => 'required|integer|min:0',
            'weekly_token_limit' => 'required|integer|min:0',
            'request_limit_per_minute' => 'required|integer|min:1',
            'concurrent_session_limit' => 'required|integer|min:1',
            'max_projects' => 'required|integer|min:1',
            'max_storage_bytes' => 'required|integer|min:0',
            'max_file_size_bytes' => 'required|integer|min:0',
            'is_default' => 'boolean',
            'model_ids' => 'nullable|array',
            'model_ids.*' => 'exists:ai_models,id',
            'agent_ids' => 'nullable|array',
            'agent_ids.*' => 'exists:coding_agents,id',
        ]);

        if (!empty($validated['is_default'])) {
            UserGroup::where('is_default', true)->update(['is_default' => false]);
        }

        $group = UserGroup::create($validated);

        if (!empty($validated['model_ids'])) {
            $group->allowedModels()->sync($validated['model_ids']);
        }

        if (!empty($validated['agent_ids'])) {
            $group->allowedAgents()->sync($validated['agent_ids']);
        }

        AuditLog::log('create_user_group', 'UserGroup', (string) $group->id, ['name' => $group->name]);

        return response()->json([
            'message' => 'User group created successfully',
            'group' => $group->load(['allowedModels', 'allowedAgents']),
        ], 201);
    }

    public function update(Request $request, UserGroup $userGroup): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('user_groups')->ignore($userGroup->id)],
            'description' => 'nullable|string',
            'monthly_token_limit' => 'required|integer|min:0',
            'daily_token_limit' => 'required|integer|min:0',
            'weekly_token_limit' => 'required|integer|min:0',
            'request_limit_per_minute' => 'required|integer|min:1',
            'concurrent_session_limit' => 'required|integer|min:1',
            'max_projects' => 'required|integer|min:1',
            'max_storage_bytes' => 'required|integer|min:0',
            'max_file_size_bytes' => 'required|integer|min:0',
            'is_default' => 'boolean',
            'model_ids' => 'nullable|array',
            'model_ids.*' => 'exists:ai_models,id',
            'agent_ids' => 'nullable|array',
            'agent_ids.*' => 'exists:coding_agents,id',
        ]);

        if (!empty($validated['is_default']) && !$userGroup->is_default) {
            UserGroup::where('is_default', true)->update(['is_default' => false]);
        }

        $userGroup->update($validated);

        if (isset($validated['model_ids'])) {
            $userGroup->allowedModels()->sync($validated['model_ids']);
        }

        if (isset($validated['agent_ids'])) {
            $userGroup->allowedAgents()->sync($validated['agent_ids']);
        }

        AuditLog::log('update_user_group', 'UserGroup', (string) $userGroup->id, ['name' => $userGroup->name]);

        return response()->json([
            'message' => 'User group updated successfully',
            'group' => $userGroup->load(['allowedModels', 'allowedAgents']),
        ]);
    }

    public function destroy(UserGroup $userGroup): JsonResponse
    {
        if ($userGroup->users()->count() > 0) {
            return response()->json(['error' => 'Cannot delete group with active assigned users.'], 422);
        }

        AuditLog::log('delete_user_group', 'UserGroup', (string) $userGroup->id, ['name' => $userGroup->name]);

        $userGroup->delete();

        return response()->json(['message' => 'User group deleted successfully']);
    }
}
