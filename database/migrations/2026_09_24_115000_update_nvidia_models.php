<?php

use App\Models\AiModel;
use App\Models\FallbackModel;
use App\Models\ModelPricing;
use App\Models\ModelProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $nvidia = ModelProvider::where('slug', 'nvidia_nim')->first();
        if (!$nvidia) {
            return;
        }

        // 1. Update deprecated meta/llama-3.1-70b-instruct to meta/llama-3.3-70b-instruct
        $llamaOld = AiModel::where('provider_model_id', 'meta/llama-3.1-70b-instruct')->first();
        if ($llamaOld) {
            $llamaOld->update([
                'name' => 'Llama 3.3 70B Instruct',
                'slug' => 'nvidia-llama-3-3-70b',
                'provider_model_id' => 'meta/llama-3.3-70b-instruct',
                'description' => 'Fast and powerful Meta model hosted on NVIDIA NIM high-speed inference.',
            ]);
        } else {
            AiModel::firstOrCreate(
                ['provider_model_id' => 'meta/llama-3.3-70b-instruct'],
                [
                    'provider_id' => $nvidia->id,
                    'name' => 'Llama 3.3 70B Instruct',
                    'slug' => 'nvidia-llama-3-3-70b',
                    'description' => 'Fast and powerful Meta model hosted on NVIDIA NIM high-speed inference.',
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'category' => 'free',
                    'status' => 'active',
                    'visibility' => 'all',
                    'is_default' => false,
                ]
            );
        }

        // 2. Ensure Llama 3.1 Nemotron 70B is available
        $nemotron70b = AiModel::firstOrCreate(
            ['provider_model_id' => 'nvidia/llama-3.1-nemotron-70b-instruct'],
            [
                'provider_id' => $nvidia->id,
                'name' => 'Llama 3.1 Nemotron 70B',
                'slug' => 'nvidia-llama-nemotron-70b',
                'description' => 'NVIDIA tuned ultra-accurate coding and helpfulness model.',
                'context_window' => 131072,
                'max_tokens' => 8192,
                'category' => 'free',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
            ]
        );

        ModelPricing::firstOrCreate(
            ['model_id' => $nemotron70b->id],
            [
                'provider_id' => $nvidia->id,
                'input_price_per_1m' => 0.0,
                'output_price_per_1m' => 0.0,
                'currency' => 'USD',
                'is_active' => true,
            ]
        );

        // 3. Configure Fallbacks: Link all NVIDIA models to Nemotron 120B (and vice-versa)
        $nemotron120b = AiModel::where('slug', 'nvidia-nemotron-120b')->first();
        $llama33 = AiModel::where('provider_model_id', 'meta/llama-3.3-70b-instruct')->first();

        if ($nemotron120b && $llama33) {
            FallbackModel::firstOrCreate(
                [
                    'primary_model_id' => $llama33->id,
                    'fallback_model_id' => $nemotron120b->id,
                ],
                [
                    'priority' => 1,
                    'is_active' => true,
                ]
            );

            FallbackModel::firstOrCreate(
                [
                    'primary_model_id' => $nemotron70b->id,
                    'fallback_model_id' => $nemotron120b->id,
                ],
                [
                    'priority' => 1,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
