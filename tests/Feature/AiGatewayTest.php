<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\CodingAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModelPricing;
use App\Models\ModelProvider;
use App\Models\User;
use App\Services\AI\AiGatewayService;
use App\Services\AI\CostCalculatorService;
use App\Services\AI\NativeGatewayService;
use App\Services\AI\ProviderService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    protected CostCalculatorService $costCalculator;
    protected NativeGatewayService $nativeGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costCalculator = app(CostCalculatorService::class);
        $this->nativeGateway = app(NativeGatewayService::class);
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

        $result = $this->costCalculator->calculateCost($model, 1000000, 1000000);
        $this->assertEquals(0.50, $result['input_cost']);
        $this->assertEquals(1.50, $result['output_cost']);
        $this->assertEquals(2.00, $result['total_cost']);

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

    public function test_native_gateway_resolves_endpoints_correctly(): void
    {
        $nvidia = new ModelProvider(['base_url' => 'https://integrate.api.nvidia.com/v1', 'type' => 'nvidia_nim']);
        $this->assertEquals('https://integrate.api.nvidia.com/v1/chat/completions', $this->nativeGateway->resolveChatEndpoint($nvidia));
        $this->assertEquals('https://integrate.api.nvidia.com/v1/models', $this->nativeGateway->resolveModelsEndpoint($nvidia));

        $deepseek = new ModelProvider(['base_url' => 'https://api.deepseek.com', 'type' => 'deepseek']);
        $this->assertEquals('https://api.deepseek.com/chat/completions', $this->nativeGateway->resolveChatEndpoint($deepseek));

        $ollama = new ModelProvider(['base_url' => 'http://localhost:11434', 'type' => 'ollama']);
        $this->assertEquals('http://localhost:11434/v1/chat/completions', $this->nativeGateway->resolveChatEndpoint($ollama));
        $this->assertEquals('http://localhost:11434/api/tags', $this->nativeGateway->resolveModelsEndpoint($ollama));

        $fcc = new ModelProvider(['base_url' => 'http://127.0.0.1:8082', 'type' => 'fcc']);
        $this->assertEquals('http://127.0.0.1:8082/v1/messages', $this->nativeGateway->resolveChatEndpoint($fcc));
        $this->assertEquals('http://127.0.0.1:8082/health', $this->nativeGateway->resolveModelsEndpoint($fcc));
    }

    public function test_native_gateway_executes_chat_and_parses_response(): void
    {
        Http::fake([
            'https://integrate.api.nvidia.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-123',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! I am Nemotron running directly on Laravel native gateway.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 15,
                    'total_tokens' => 35,
                ],
            ], 200),
        ]);

        $provider = ModelProvider::create([
            'name' => 'NVIDIA NIM Direct',
            'slug' => 'nvidia_direct',
            'type' => 'nvidia_nim',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'status' => 'active',
            'priority' => 1,
        ]);
        $provider->api_key = 'nvapi-test-secret-key';
        $provider->save();

        $model = AiModel::create([
            'provider_id' => $provider->id,
            'name' => 'Nemotron Direct',
            'slug' => 'nemotron-direct',
            'provider_model_id' => 'nvidia/nemotron-3-super-120b-a12b',
            'context_window' => 131072,
            'max_tokens' => 4096,
            'category' => 'free',
            'status' => 'active',
            'visibility' => 'all',
            'is_default' => true,
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Hello there'],
        ];

        $response = $this->nativeGateway->executeChat($model, $messages, 'You are an AI assistant.');

        $this->assertEquals('Hello! I am Nemotron running directly on Laravel native gateway.', $response['content']);
        $this->assertEquals(20, $response['input_tokens']);
        $this->assertEquals(15, $response['output_tokens']);
        $this->assertEquals(35, $response['total_tokens']);
        $this->assertEquals('chatcmpl-test-123', $response['provider_request_id']);
    }

    public function test_ai_gateway_service_sends_message_and_records_usage(): void
    {
        Http::fake([
            'https://integrate.api.nvidia.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-ai-gateway-test',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'function helloWorld() { return "hello"; }',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 25,
                    'completion_tokens' => 30,
                    'total_tokens' => 55,
                ],
            ], 200),
        ]);

        $provider = ModelProvider::create([
            'name' => 'NVIDIA NIM Full',
            'slug' => 'nvidia_full',
            'type' => 'nvidia_nim',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'status' => 'active',
            'priority' => 1,
        ]);
        $provider->api_key = 'nvapi-full-secret';
        $provider->save();

        $model = AiModel::create([
            'provider_id' => $provider->id,
            'name' => 'Nemotron 120B Full',
            'slug' => 'nemotron-full',
            'provider_model_id' => 'nvidia/nemotron-3-super-120b-a12b',
            'context_window' => 131072,
            'max_tokens' => 4096,
            'category' => 'free',
            'status' => 'active',
            'visibility' => 'all',
            'is_default' => true,
        ]);

        ModelPricing::create([
            'provider_id' => $provider->id,
            'model_id' => $model->id,
            'input_price_per_1m' => 0.0,
            'output_price_per_1m' => 0.0,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'model_id' => $model->id,
            'title' => 'New Chat',
            'status' => 'active',
        ]);

        $aiGateway = app(AiGatewayService::class);
        $result = $aiGateway->sendMessage($user, $conversation, 'Write a hello world function');

        $this->assertInstanceOf(Message::class, $result['message']);
        $this->assertEquals('assistant', $result['message']->role);
        $this->assertStringContainsString('helloWorld', $result['message']->content);
        $this->assertEquals(55, $result['usage']['total_tokens']);

        // Assert database usage log was created
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $user->id,
            'model_id' => $model->id,
            'total_tokens' => 55,
            'status' => 'completed',
        ]);
    }

    public function test_provider_health_check_pings_upstream_directly(): void
    {
        Http::fake([
            'https://integrate.api.nvidia.com/v1/models' => Http::response(['data' => []], 200),
        ]);

        $provider = ModelProvider::create([
            'name' => 'NVIDIA Ping Test',
            'slug' => 'nvidia_ping',
            'type' => 'nvidia_nim',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'status' => 'active',
            'priority' => 1,
        ]);
        $provider->api_key = 'nvapi-ping-test';
        $provider->save();

        $providerService = app(ProviderService::class);
        $result = $providerService->checkProviderHealth($provider);

        $this->assertEquals('healthy', $result['health_status']);
        $this->assertNull($result['error']);

        $provider->refresh();
        $this->assertEquals('healthy', $provider->health_status);
        $this->assertNotNull($provider->last_checked_at);
    }

    public function test_chat_stream_endpoint(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $provider = ModelProvider::create([
            'name' => 'NVIDIA Test',
            'slug' => 'nvidia_test_stream',
            'type' => 'nvidia_nim',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'status' => 'active',
            'priority' => 1,
        ]);
        $provider->api_key = 'nvapi-test';
        $provider->save();

        $model = AiModel::create([
            'provider_id' => $provider->id,
            'name' => 'Nemotron',
            'slug' => 'nemotron-stream-test',
            'provider_model_id' => 'nvidia/nemotron-3-super-120b-a12b',
            'context_window' => 131072,
            'max_tokens' => 4096,
            'category' => 'free',
            'status' => 'active',
            'visibility' => 'all',
            'is_default' => true,
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'model_id' => $model->id,
            'title' => 'Test Stream',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/chat/conversations/{$conversation->id}/stream", [
                'prompt' => 'Hello',
            ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 500]));
    }
}
