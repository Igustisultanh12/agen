<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure default group exists
        UserGroup::firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free Tier',
                'monthly_token_limit' => 5000000,
                'daily_token_limit' => 200000,
                'weekly_token_limit' => 1250000,
                'request_limit_per_minute' => 15,
                'concurrent_session_limit' => 1,
                'max_projects' => 3,
                'max_storage_bytes' => 52428800,
                'max_file_size_bytes' => 5242880,
                'is_default' => true,
            ]
        );
    }

    public function test_user_can_register_and_receive_token(): void
    {
        $payload = [
            'name' => 'Alice Developer',
            'email' => 'alice@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role', 'status'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user->quota);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'bob@example.com',
            'password' => Hash::make('secret12345'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'bob@example.com',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'charlie@example.com',
            'password' => Hash::make('secret12345'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'charlie@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'banned@example.com',
            'password' => Hash::make('secret12345'),
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'banned@example.com',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_fetch_me_with_quota(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'quota' => ['monthly_token_limit', 'monthly_used_tokens', 'monthly_remaining_tokens'],
            ]);
    }

    public function test_user_can_update_profile_and_custom_instructions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/profile', [
                'name' => 'Alice Modified',
                'custom_instructions' => 'Always use TypeScript with strict types',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Alice Modified',
            'custom_instructions' => 'Always use TypeScript with strict types',
        ]);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123'),
            'status' => 'active',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword456',
                'password_confirmation' => 'newpassword456',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword456', $user->password));
    }
}
