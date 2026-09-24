<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\CodingAgent;
use App\Models\Conversation;
use App\Models\ModelPricing;
use App\Models\ModelProvider;
use App\Models\User;
use App\Services\AI\CostCalculatorService;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    protected CostCalculatorService $costCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costCalculator = app(CostCalculatorService::class);
    }

    public function test_catalog_returns_models_and_agents(): void
    {
        $provider = ModelProvider::create([
            'name' => 'OpenRouter Test',
            'slug' => 'openrouter-test',
            'type' => 'openrouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'status' => 'active',
            'priority' => 1,
        ]);

        $model = AiModel::create([
            'provider_id' => $provider->id,
            'name' => 'Claude 3.5 Sonnet',
            'slug' => 'claude-3-5-sonnet',
            'provider_model_id' => 'anthropic/claude-3.5-sonnet',
            'context_window' => 200000,
            'max_tokens' => 8192,
            'category' => 'paid',
            'status' => 'active',
            'visibility' => 'all',
            'is_default' => true,
        ]);

        $agent = CodingAgent::create([
            'name' => 'Claude Code',
            'slug' => 'claude-code',
            'harness_type' => 'fcc_claude_code',
            'is_active' => true,
            'is_default' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/chat/catalog');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'providers',
                'agents',
            ]);
    }

    public function test_user_can_create_and_manage_conversations(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $createResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/chat/conversations', [
                'title' => 'Refactoring Auth Middleware',
            ]);

        $createResp->assertStatus(201);
        $convId = $createResp->json('id');

        $this->assertDatabaseHas('conversations', [
            'id' => $convId,
            'user_id' => $user->id,
            'title' => 'Refactoring Auth Middleware',
        ]);

        $listResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/chat/conversations');

        $listResp->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_cost_calculator_calculates_per_1m_tokens_accurately(): void
    {
        $provider = ModelProvider::create([
            'name' => 'NVIDIA NIM',
            'slug' => 'nvidia',
            'type' => 'nvidia',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'status' => 'active',
            'priority' => 1,
        ]);

        $model = AiModel::create([
            'provider_id' => $provider->id,
            'name' => 'Nemotron 120B',
            'slug' => 'nemotron-120b',
            'provider_model_id' => 'nvidia/llama-3.1-nemotron-70b-instruct',
            'context_window' => 128000,
            'max_tokens' => 4096,
            'category' => 'paid',
            'status' => 'active',
            'visibility' => 'all',
        ]);

        ModelPricing::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'input_price_per_1m' => 0.50,
            'output_price_per_1m' => 1.50,
            'cached_input_price_per_1m' => 0.25,
            'reasoning_price_per_1m' => 0.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        // Test with 1,000,000 input tokens and 1,000,000 output tokens:
        // Expected: $0.50 input + $1.50 output = $2.00
        $result = $this->costCalculator->calculateCost($model, 1000000, 1000000);
        $this->assertEquals(0.50, $result['input_cost']);
        $this->assertEquals(1.50, $result['output_cost']);
        $this->assertEquals(2.00, $result['total_cost']);

        // Test with 10,000 input and 2,000 output:
        // (10,000/1,000,000) * 0.50 = 0.005
        // (2,000/1,000,000) * 1.50 = 0.003
        // Total = 0.008
        $smallResult = $this->costCalculator->calculateCost($model, 10000, 2000);
        $this->assertEquals(0.005, $smallResult['input_cost']);
        $this->assertEquals(0.003, $smallResult['output_cost']);
        $this->assertEquals(0.008, $smallResult['total_cost']);
    }

    public function test_local_model_cost_is_free(): void
    {
        $provider = ModelProvider::create([
            'name' => 'Ollama Local',
            'slug' => 'ollama',
            'type' => 'ollama',
            'base_url' => 'http://localhost:11434',
            'status' => 'active',
            'priority' => 1,
        ]);

        $model = AiModel::create([
            'provider_id' => $provider->id,
            'name' => 'Qwen 2.5 Coder',
            'slug' => 'qwen-coder',
            'provider_model_id' => 'qwen2.5-coder:14b',
            'context_window' => 32000,
            'max_tokens' => 4096,
            'category' => 'local',
            'status' => 'active',
            'visibility' => 'all',
        ]);

        $result = $this->costCalculator->calculateCost($model, 50000, 20000);
        $this->assertTrue($result['is_free']);
        $this->assertEquals(0.0, $result['total_cost']);
    }
}
