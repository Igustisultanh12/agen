<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CodingAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = CodingAgent::with('userGroups')->get();

        return response()->json($agents);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:coding_agents,slug',
            'description' => 'nullable|string',
            'harness_type' => 'required|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'user_group_ids' => 'nullable|array',
            'user_group_ids.*' => 'exists:user_groups,id',
        ]);

        if (!empty($validated['is_default'])) {
            CodingAgent::where('is_default', true)->update(['is_default' => false]);
        }

        $agent = CodingAgent::create($validated);

        if (!empty($validated['user_group_ids'])) {
            $agent->userGroups()->sync($validated['user_group_ids']);
        }

        AuditLog::log('create_agent', 'CodingAgent', (string) $agent->id, ['name' => $agent->name]);

        return response()->json([
            'message' => 'Coding agent created successfully',
            'agent' => $agent->load('userGroups'),
        ], 201);
    }

    public function update(Request $request, CodingAgent $codingAgent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('coding_agents')->ignore($codingAgent->id)],
            'description' => 'nullable|string',
            'harness_type' => 'required|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'user_group_ids' => 'nullable|array',
            'user_group_ids.*' => 'exists:user_groups,id',
        ]);

        if (!empty($validated['is_default']) && !$codingAgent->is_default) {
            CodingAgent::where('is_default', true)->update(['is_default' => false]);
        }

        $codingAgent->update($validated);

        if (isset($validated['user_group_ids'])) {
            $codingAgent->userGroups()->sync($validated['user_group_ids']);
        }

        AuditLog::log('update_agent', 'CodingAgent', (string) $codingAgent->id, ['name' => $codingAgent->name]);

        return response()->json([
            'message' => 'Coding agent updated successfully',
            'agent' => $codingAgent->load('userGroups'),
        ]);
    }

    public function toggleStatus(CodingAgent $codingAgent): JsonResponse
    {
        $newStatus = !$codingAgent->is_active;
        $codingAgent->update(['is_active' => $newStatus]);

        AuditLog::log('toggle_agent_status', 'CodingAgent', (string) $codingAgent->id, ['is_active' => $newStatus]);

        return response()->json([
            'message' => 'Agent status updated successfully',
            'agent' => $codingAgent,
        ]);
    }
}
