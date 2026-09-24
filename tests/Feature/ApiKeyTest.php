<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    public function test_user_can_create_api_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/api-keys', [
                'name' => 'CLI Automation',
                'permissions' => ['chat', 'models.read', 'projects.read'],
                'expires_days' => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'api_key' => ['id', 'name', 'key_preview', 'is_active'],
                'plain_text_key',
            ]);

        $plainTextKey = $response->json('plain_text_key');
        $this->assertStringStartsWith('fcc_', $plainTextKey);

        $hash = hash('sha256', $plainTextKey);
        $this->assertDatabaseHas('api_keys', [
            'key_hash' => $hash,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_external_api_v1_models_authenticates_with_api_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $generated = ApiKey::generate($user, 'Integration Key', ['models.read']);
        $rawKey = $generated['plain_text_key'];

        $response = $this->withHeader('x-api-key', $rawKey)
            ->getJson('/api/v1/models');

        $response->assertStatus(200);
    }

    public function test_external_api_rejects_missing_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        // Key with only chat permission, not models.read
        $generated = ApiKey::generate($user, 'Chat Only Key', ['chat']);
        $rawKey = $generated['plain_text_key'];

        $response = $this->withHeader('x-api-key', $rawKey)
            ->getJson('/api/v1/models');

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Forbidden']);
    }

    public function test_revoking_api_key_prevents_access(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;
        $generated = ApiKey::generate($user, 'Key to Revoke', ['models.read']);
        $apiKey = $generated['api_key'];
        $rawKey = $generated['plain_text_key'];

        $revokeResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/api-keys/{$apiKey->id}/revoke");

        $revokeResp->assertStatus(200);

        // Access with revoked key should fail
        $accessResp = $this->withHeader('x-api-key', $rawKey)
            ->getJson('/api/v1/models');

        $accessResp->assertStatus(401);
    }
}
