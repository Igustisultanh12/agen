<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\FallbackModel;
use App\Models\ModelPricing;
use App\Models\ModelProvider;
use Illuminate\Database\Seeder;

class ProviderAndModelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Providers
        $providers = [
            [
                'name' => 'NVIDIA NIM',
                'slug' => 'nvidia_nim',
                'type' => 'nvidia_nim',
                'base_url' => 'https://integrate.api.nvidia.com/v1',
                'status' => 'active',
                'priority' => 1,
                'health_status' => 'healthy',
            ],
            [
                'name' => 'OpenRouter',
                'slug' => 'open_router',
                'type' => 'open_router',
                'base_url' => 'https://openrouter.ai/api/v1',
                'status' => 'active',
                'priority' => 2,
                'health_status' => 'healthy',
            ],
            [
                'name' => 'Groq',
                'slug' => 'groq',
                'type' => 'groq',
                'base_url' => 'https://api.groq.com/openai/v1',
                'status' => 'active',
                'priority' => 3,
                'health_status' => 'healthy',
            ],
            [
                'name' => 'DeepSeek',
                'slug' => 'deepseek',
                'type' => 'deepseek',
                'base_url' => 'https://api.deepseek.com',
                'status' => 'active',
                'priority' => 4,
                'health_status' => 'healthy',
            ],
            [
                'name' => 'Google Gemini',
                'slug' => 'gemini',
                'type' => 'gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/',
                'status' => 'active',
                'priority' => 5,
                'health_status' => 'healthy',
            ],
            [
                'name' => 'Ollama (Local)',
                'slug' => 'ollama',
                'type' => 'ollama',
                'base_url' => 'http://localhost:11434',
                'status' => 'active',
                'priority' => 10,
                'health_status' => 'healthy',
            ],
            [
                'name' => 'LM Studio (Local)',
                'slug' => 'lmstudio',
                'type' => 'lmstudio',
                'base_url' => 'http://localhost:1234/v1',
                'status' => 'active',
                'priority' => 11,
                'health_status' => 'healthy',
            ],
        ];

        $providerMap = [];
        foreach ($providers as $prov) {
            $providerMap[$prov['slug']] = ModelProvider::updateOrCreate(['slug' => $prov['slug']], $prov);
        }

        // 2. Models & Pricing
        $models = [
            // NVIDIA NIM Models (Free tier available)
            [
                'provider_slug' => 'nvidia_nim',
                'name' => 'Nemotron-3 Super 120B',
                'slug' => 'nvidia-nemotron-120b',
                'provider_model_id' => 'nvidia/nemotron-3-super-120b-a12b',
                'description' => 'Flagship NVIDIA high-capability coding and reasoning model.',
                'context_window' => 131072,
                'max_tokens' => 8192,
                'category' => 'free',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => true,
                'pricing' => [
                    'input_price_per_1m' => 0.000000,
                    'output_price_per_1m' => 0.000000,
                ],
            ],
            [
                'provider_slug' => 'nvidia_nim',
                'name' => 'Llama 3.1 70B Instruct',
                'slug' => 'nvidia-llama-3-1-70b',
                'provider_model_id' => 'meta/llama-3.1-70b-instruct',
                'description' => 'Fast and powerful Meta model hosted on NVIDIA NIM high-speed inference.',
                'context_window' => 131072,
                'max_tokens' => 8192,
                'category' => 'free',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.000000,
                    'output_price_per_1m' => 0.000000,
                ],
            ],

            // OpenRouter Models
            [
                'provider_slug' => 'open_router',
                'name' => 'Claude 3.5 Sonnet',
                'slug' => 'claude-3-5-sonnet',
                'provider_model_id' => 'anthropic/claude-3.5-sonnet',
                'description' => 'State-of-the-art coding and agentic problem solving.',
                'context_window' => 200000,
                'max_tokens' => 8192,
                'category' => 'paid',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 3.000000,
                    'output_price_per_1m' => 15.000000,
                    'cached_input_price_per_1m' => 0.300000,
                ],
            ],
            [
                'provider_slug' => 'open_router',
                'name' => 'Claude 3.5 Haiku',
                'slug' => 'claude-3-5-haiku',
                'provider_model_id' => 'anthropic/claude-3.5-haiku',
                'description' => 'Ultra fast, responsive Anthropic model with strong coding capabilities.',
                'context_window' => 200000,
                'max_tokens' => 8192,
                'category' => 'paid',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.800000,
                    'output_price_per_1m' => 4.000000,
                ],
            ],

            // Groq Models
            [
                'provider_slug' => 'groq',
                'name' => 'Llama 3.3 70B Versatile',
                'slug' => 'groq-llama-3-3-70b',
                'provider_model_id' => 'llama-3.3-70b-versatile',
                'description' => 'Ultra-low latency LPU inference for instant coding answers.',
                'context_window' => 128000,
                'max_tokens' => 8192,
                'category' => 'free',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.590000,
                    'output_price_per_1m' => 0.790000,
                ],
            ],

            // DeepSeek
            [
                'provider_slug' => 'deepseek',
                'name' => 'DeepSeek V3 Chat',
                'slug' => 'deepseek-v3',
                'provider_model_id' => 'deepseek-chat',
                'description' => 'Advanced 671B MoE model with exceptional coding and mathematical reasoning.',
                'context_window' => 65536,
                'max_tokens' => 8192,
                'category' => 'paid',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.140000,
                    'output_price_per_1m' => 0.280000,
                    'cached_input_price_per_1m' => 0.014000,
                ],
            ],

            // Gemini
            [
                'provider_slug' => 'gemini',
                'name' => 'Gemini 2.5 Flash',
                'slug' => 'gemini-2-5-flash',
                'provider_model_id' => 'gemini-2.5-flash',
                'description' => 'Google multimodal speed and reasoning powerhouse.',
                'context_window' => 1000000,
                'max_tokens' => 8192,
                'category' => 'free',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.075000,
                    'output_price_per_1m' => 0.300000,
                ],
            ],

            // Local Models
            [
                'provider_slug' => 'ollama',
                'name' => 'Qwen 2.5 Coder 7B (Local)',
                'slug' => 'ollama-qwen-coder-7b',
                'provider_model_id' => 'qwen2.5-coder:7b',
                'description' => 'Local offline inference via Ollama server. Cost: $0.00.',
                'context_window' => 32768,
                'max_tokens' => 4096,
                'category' => 'local',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.000000,
                    'output_price_per_1m' => 0.000000,
                ],
            ],
            [
                'provider_slug' => 'lmstudio',
                'name' => 'Local LM Studio Model',
                'slug' => 'lmstudio-local-model',
                'provider_model_id' => 'local-model',
                'description' => 'Local OpenAI-compatible inference via LM Studio. Cost: $0.00.',
                'context_window' => 32768,
                'max_tokens' => 4096,
                'category' => 'local',
                'status' => 'active',
                'visibility' => 'all',
                'is_default' => false,
                'pricing' => [
                    'input_price_per_1m' => 0.000000,
                    'output_price_per_1m' => 0.000000,
                ],
            ],
        ];

        $modelInstances = [];
        foreach ($models as $m) {
            $provider = $providerMap[$m['provider_slug']] ?? null;
            if (!$provider) continue;

            $pricingData = $m['pricing'];
            unset($m['provider_slug'], $m['pricing']);

            $m['provider_id'] = $provider->id;

            $model = AiModel::updateOrCreate(['slug' => $m['slug']], $m);
            $modelInstances[$m['slug']] = $model;

            ModelPricing::updateOrCreate(
                ['model_id' => $model->id, 'is_active' => true],
                [
                    'provider_id' => $provider->id,
                    'input_price_per_1m' => $pricingData['input_price_per_1m'] ?? 0.0,
                    'output_price_per_1m' => $pricingData['output_price_per_1m'] ?? 0.0,
                    'cached_input_price_per_1m' => $pricingData['cached_input_price_per_1m'] ?? 0.0,
                    'reasoning_price_per_1m' => $pricingData['reasoning_price_per_1m'] ?? 0.0,
                    'currency' => 'USD',
                ]
            );
        }

        // 3. Fallback Model Chains (Requirement 33)
        // Primary Nemotron -> Fallback Llama 3.3 Groq -> Fallback Gemini Flash
        if (isset($modelInstances['nvidia-nemotron-120b'])) {
            $pri = $modelInstances['nvidia-nemotron-120b'];
            if (isset($modelInstances['groq-llama-3-3-70b'])) {
                FallbackModel::updateOrCreate(
                    ['primary_model_id' => $pri->id, 'fallback_model_id' => $modelInstances['groq-llama-3-3-70b']->id],
                    ['priority' => 1, 'is_active' => true]
                );
            }
            if (isset($modelInstances['gemini-2-5-flash'])) {
                FallbackModel::updateOrCreate(
                    ['primary_model_id' => $pri->id, 'fallback_model_id' => $modelInstances['gemini-2-5-flash']->id],
                    ['priority' => 2, 'is_active' => true]
                );
            }
        }
    }
}
