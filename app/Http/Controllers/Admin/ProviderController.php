<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ModelProvider;
use App\Services\AI\ProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderController extends Controller
{
    public function __construct(
        protected ProviderService $providerService
    ) {}

    public function index(): JsonResponse
    {
        $providers = ModelProvider::withCount('models')
            ->orderBy('priority', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($providers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:model_providers,slug',
            'type' => 'required|string',
            'base_url' => 'required|url',
            'api_key' => 'nullable|string',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'priority' => 'required|integer|min:1',
            'settings' => 'nullable|array',
        ]);

        $provider = $this->providerService->saveProvider($validated);

        return response()->json([
            'message' => 'AI Provider created successfully',
            'provider' => $provider,
        ], 201);
    }

    public function update(Request $request, ModelProvider $modelProvider): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('model_providers')->ignore($modelProvider->id)],
            'type' => 'required|string',
            'base_url' => 'required|url',
            'api_key' => 'nullable|string',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'priority' => 'required|integer|min:1',
            'settings' => 'nullable|array',
        ]);

        $provider = $this->providerService->saveProvider($validated, $modelProvider);

        return response()->json([
            'message' => 'AI Provider updated successfully',
            'provider' => $provider,
        ]);
    }

    public function checkHealth(ModelProvider $modelProvider): JsonResponse
    {
        $result = $this->providerService->checkProviderHealth($modelProvider);

        return response()->json([
            'message' => 'Health check complete',
            'result' => $result,
        ]);
    }

    public function checkAllHealth(): JsonResponse
    {
        $results = $this->providerService->checkAllProvidersHealth();

        return response()->json([
            'message' => 'Health checks completed for all providers',
            'results' => $results,
        ]);
    }

    public function destroy(ModelProvider $modelProvider): JsonResponse
    {
        AuditLog::log('delete_provider', 'ModelProvider', (string) $modelProvider->id, ['name' => $modelProvider->name]);

        $modelProvider->delete();

        return response()->json(['message' => 'AI Provider deleted successfully']);
    }
}
