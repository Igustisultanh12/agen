<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\FallbackModel;
use App\Services\AI\ModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModelController extends Controller
{
    public function __construct(
        protected ModelService $modelService
    ) {}

    public function index(): JsonResponse
    {
        $models = AiModel::with(['provider', 'activePricing', 'fallbackModels.fallbackModel'])
            ->orderBy('provider_id')
            ->orderBy('name')
            ->get();

        return response()->json($models);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:model_providers,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:ai_models,slug',
            'provider_model_id' => 'required|string|max:255',
            'description' => 'nullable|string',
            'context_window' => 'required|integer|min:1024',
            'max_tokens' => 'required|integer|min:256',
            'category' => ['required', Rule::in(['free', 'paid', 'local'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'visibility' => ['required', Rule::in(['all', 'user_group', 'admin'])],
            'is_default' => 'boolean',
            'pricing' => 'nullable|array',
            'pricing.input_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.output_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.cached_input_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.reasoning_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.currency' => 'nullable|string|max:10',
            'fallback_model_ids' => 'nullable|array',
            'fallback_model_ids.*' => 'exists:ai_models,id',
        ]);

        if (!empty($validated['is_default'])) {
            AiModel::where('is_default', true)->update(['is_default' => false]);
        }

        $model = $this->modelService->saveModel($validated);

        if (!empty($validated['fallback_model_ids'])) {
            $this->syncFallbacks($model, $validated['fallback_model_ids']);
        }

        return response()->json([
            'message' => 'AI Model created successfully',
            'model' => $model->load(['provider', 'activePricing', 'fallbackModels.fallbackModel']),
        ], 201);
    }

    public function update(Request $request, AiModel $aiModel): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:model_providers,id',
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('ai_models')->ignore($aiModel->id)],
            'provider_model_id' => 'required|string|max:255',
            'description' => 'nullable|string',
            'context_window' => 'required|integer|min:1024',
            'max_tokens' => 'required|integer|min:256',
            'category' => ['required', Rule::in(['free', 'paid', 'local'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'visibility' => ['required', Rule::in(['all', 'user_group', 'admin'])],
            'is_default' => 'boolean',
            'pricing' => 'nullable|array',
            'pricing.input_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.output_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.cached_input_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.reasoning_price_per_1m' => 'nullable|numeric|min:0',
            'pricing.currency' => 'nullable|string|max:10',
            'fallback_model_ids' => 'nullable|array',
            'fallback_model_ids.*' => 'exists:ai_models,id',
        ]);

        if (!empty($validated['is_default']) && !$aiModel->is_default) {
            AiModel::where('is_default', true)->update(['is_default' => false]);
        }

        $model = $this->modelService->saveModel($validated, $aiModel);

        if (isset($validated['fallback_model_ids'])) {
            $this->syncFallbacks($model, $validated['fallback_model_ids']);
        }

        return response()->json([
            'message' => 'AI Model updated successfully',
            'model' => $model->load(['provider', 'activePricing', 'fallbackModels.fallbackModel']),
        ]);
    }

    public function toggleStatus(AiModel $aiModel): JsonResponse
    {
        $newStatus = $aiModel->status === 'active' ? 'inactive' : 'active';
        $aiModel->update(['status' => $newStatus]);

        AuditLog::log('toggle_model_status', 'AiModel', (string) $aiModel->id, ['status' => $newStatus]);

        return response()->json([
            'message' => "Model status changed to {$newStatus}",
            'model' => $aiModel,
        ]);
    }

    public function destroy(AiModel $aiModel): JsonResponse
    {
        AuditLog::log('delete_model', 'AiModel', (string) $aiModel->id, ['name' => $aiModel->name]);

        $aiModel->delete();

        return response()->json(['message' => 'AI Model deleted successfully']);
    }

    protected function syncFallbacks(AiModel $model, array $fallbackIds): void
    {
        FallbackModel::where('primary_model_id', $model->id)->delete();

        $priority = 1;
        foreach ($fallbackIds as $fbId) {
            if ($fbId != $model->id) {
                FallbackModel::create([
                    'primary_model_id' => $model->id,
                    'fallback_model_id' => $fbId,
                    'priority' => $priority++,
                    'is_active' => true,
                ]);
            }
        }
    }
}
