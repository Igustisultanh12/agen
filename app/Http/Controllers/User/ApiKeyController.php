<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keys = ApiKey::where('user_id', $request->user()->id)
            ->latest('id')
            ->get();

        return response()->json($keys);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:chat,models.read,projects.read,projects.write,files.read,files.write,usage.read',
            'expires_days' => 'nullable|integer|min:1|max:365',
        ]);

        $expiresAt = !empty($validated['expires_days']) ? now()->addDays($validated['expires_days']) : null;

        $result = ApiKey::generate(
            $request->user(),
            $validated['name'],
            $validated['permissions'] ?? ['chat', 'models.read', 'projects.read', 'projects.write', 'files.read', 'files.write', 'usage.read'],
            $expiresAt
        );

        AuditLog::log('create_api_key', 'ApiKey', (string) $result['api_key']->id, ['name' => $validated['name']], $request->user());

        return response()->json([
            'message' => 'API Key generated successfully. Please copy it now as it will not be shown again.',
            'api_key' => $result['api_key'],
            'plain_text_key' => $result['plain_text_key'],
        ], 201);
    }

    public function revoke(Request $request, ApiKey $apiKey): JsonResponse
    {
        if ($apiKey->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized API key access');
        }

        $apiKey->update(['is_active' => false]);

        AuditLog::log('revoke_api_key', 'ApiKey', (string) $apiKey->id, ['name' => $apiKey->name], $request->user());

        return response()->json(['message' => 'API key revoked successfully']);
    }

    public function rotate(Request $request, ApiKey $apiKey): JsonResponse
    {
        if ($apiKey->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized API key access');
        }

        $apiKey->update(['is_active' => false]);

        $result = ApiKey::generate(
            $request->user(),
            $apiKey->name . ' (Rotated)',
            $apiKey->permissions ?? [],
            $apiKey->expires_at
        );

        AuditLog::log('rotate_api_key', 'ApiKey', (string) $result['api_key']->id, ['old_id' => $apiKey->id], $request->user());

        return response()->json([
            'message' => 'API Key rotated successfully. Old key is revoked.',
            'api_key' => $result['api_key'],
            'plain_text_key' => $result['plain_text_key'],
        ]);
    }
}
