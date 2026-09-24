<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\CodingAgent;
use App\Models\FallbackModel;
use App\Models\ModelPricing;
use App\Models\ModelProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ModelService
{
    /**
     * Get models grouped by provider accessible by user.
     */
    public function getGroupedModelsForUser(User $user): array
    {
        $query = AiModel::with(['provider', 'activePricing'])
            ->where('status', 'active')
            ->whereHas('provider', function ($q) {
                $q->where('status', 'active');
            });

        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('visibility', 'all');

                if ($user->user_group_id) {
                    $q->orWhereHas('userGroups', function ($gq) use ($user) {
                        $gq->where('user_groups.id', $user->user_group_id);
                    });
                }
            });
        }

        $models = $query->orderBy('name')->get();

        $grouped = [];
        foreach ($models as $model) {
            $providerName = $model->provider?->name ?? 'Other';
            if (!isset($grouped[$providerName])) {
                $grouped[$providerName] = [
                    'provider_name' => $providerName,
                    'provider_slug' => $model->provider?->slug ?? 'other',
                    'provider_health' => $model->provider?->health_status ?? 'healthy',
                    'models' => [],
                ];
            }

            $pricing = $model->activePricing;

            $grouped[$providerName]['models'][] = [
                'id' => $model->id,
                'name' => $model->name,
                'slug' => $model->slug,
                'provider_model_id' => $model->provider_model_id,
                'description' => $model->description,
                'category' => $model->category,
                'context_window' => $model->context_window,
                'is_default' => (bool) $model->is_default,
                'pricing' => [
                    'input_price_per_1m' => (float) ($pricing?->input_price_per_1m ?? 0.0),
                    'output_price_per_1m' => (float) ($pricing?->output_price_per_1m ?? 0.0),
                    'currency' => $pricing?->currency ?? 'USD',
                    'is_free' => $model->category === 'free' || $model->category === 'local',
                ],
            ];
        }

        return array_values($grouped);
    }

    /**
     * Get available coding agents for user.
     */
    public function getAgentsForUser(User $user): array
    {
        $query = CodingAgent::where('is_active', true);

        if (!$user->isAdmin() && $user->user_group_id) {
            $query->where(function ($q) use ($user) {
                $q->whereDoesntHave('userGroups')
                    ->orWhereHas('userGroups', function ($gq) use ($user) {
                        $gq->where('user_groups.id', $user->user_group_id);
                    });
            });
        }

        return $query->orderBy('name')->get()->map(fn($agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
            'slug' => $agent->slug,
            'harness_type' => $agent->harness_type,
            'description' => $agent->description,
            'is_default' => (bool) $agent->is_default,
        ])->toArray();
    }

    /**
     * Get ordered fallback chain starting from primary model.
     *
     * @return array<AiModel>
     */
    public function getFallbackChain(AiModel $primaryModel): array
    {
        $chain = [$primaryModel];

        $fallbacks = FallbackModel::with('fallbackModel.provider')
            ->where('primary_model_id', $primaryModel->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($fallbacks as $fb) {
            if ($fb->fallbackModel && $fb->fallbackModel->status === 'active') {
                $chain[] = $fb->fallbackModel;
            }
        }

        return $chain;
    }

    /**
     * Save model with pricing configuration.
     */
    public function saveModel(array $data, ?AiModel $model = null): AiModel
    {
        $model = $model ?? new AiModel();
        $model->fill([
            'provider_id' => $data['provider_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'provider_model_id' => $data['provider_model_id'],
            'description' => $data['description'] ?? null,
            'context_window' => $data['context_window'] ?? 128000,
            'max_tokens' => $data['max_tokens'] ?? 4096,
            'category' => $data['category'] ?? 'free',
            'status' => $data['status'] ?? 'active',
            'visibility' => $data['visibility'] ?? 'all',
            'is_default' => $data['is_default'] ?? false,
        ]);
        $model->save();

        if (isset($data['pricing'])) {
            ModelPricing::updateOrCreate(
                ['model_id' => $model->id, 'is_active' => true],
                [
                    'provider_id' => $model->provider_id,
                    'input_price_per_1m' => $data['pricing']['input_price_per_1m'] ?? 0.0,
                    'output_price_per_1m' => $data['pricing']['output_price_per_1m'] ?? 0.0,
                    'cached_input_price_per_1m' => $data['pricing']['cached_input_price_per_1m'] ?? 0.0,
                    'reasoning_price_per_1m' => $data['pricing']['reasoning_price_per_1m'] ?? 0.0,
                    'currency' => $data['pricing']['currency'] ?? 'USD',
                ]
            );
        }

        AuditLog::log(
            $model->wasRecentlyCreated ? 'create_model' : 'update_model',
            'AiModel',
            (string) $model->id,
            ['name' => $model->name, 'provider_id' => $model->provider_id]
        );

        return $model;
    }
}
