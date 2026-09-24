<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserQuota;
use App\Services\AI\QuotaService;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    protected QuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quotaService = app(QuotaService::class);
    }

    public function test_user_quota_initialization(): void
    {
        $group = UserGroup::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'monthly_token_limit' => 2000000,
                'daily_token_limit' => 100000,
                'weekly_token_limit' => 500000,
                'request_limit_per_minute' => 20,
                'concurrent_session_limit' => 2,
                'max_projects' => 5,
                'max_storage_bytes' => 50000000,
                'max_file_size_bytes' => 5000000,
                'is_default' => false,
            ]
        );

        $user = User::factory()->create([
            'user_group_id' => $group->id,
            'status' => 'active',
        ]);

        $quota = $this->quotaService->initializeUserQuota($user);

        $this->assertNotNull($quota);
        $this->assertEquals(2000000, $quota->monthly_token_limit);
        $this->assertEquals(100000, $quota->daily_token_limit);
        $this->assertEquals(0, $quota->used_total_tokens);
    }

    public function test_can_consume_tokens_and_increments_correctly(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $quota = UserQuota::create([
            'user_id' => $user->id,
            'monthly_token_limit' => 500000,
            'daily_token_limit' => 100000,
            'weekly_token_limit' => 250000,
            'used_total_tokens' => 0,
            'used_cost' => 0,
        ]);

        $canConsume = $this->quotaService->checkUserQuota($user, 5000);
        $this->assertTrue($canConsume);

        $this->quotaService->consumeQuota($user, 5000, 0.015);

        $quota->refresh();
        $this->assertEquals(5000, $quota->used_total_tokens);
        $this->assertEquals(0.015, (float) $quota->used_cost);
    }

    public function test_exceeding_monthly_limit_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active', 'role' => 'user']);
        UserQuota::create([
            'user_id' => $user->id,
            'monthly_token_limit' => 10000,
            'daily_token_limit' => 10000,
            'weekly_token_limit' => 10000,
            'used_total_tokens' => 9500,
            'used_cost' => 0.05,
        ]);

        // Requesting 1000 tokens when only 500 remain
        $canConsume = $this->quotaService->checkUserQuota($user, 1000);
        $this->assertFalse($canConsume);
    }

    public function test_admin_can_update_user_quota_limits(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $quota = UserQuota::create([
            'user_id' => $user->id,
            'monthly_token_limit' => 1000000,
            'daily_token_limit' => 50000,
            'weekly_token_limit' => 250000,
            'used_total_tokens' => 10000,
        ]);

        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/users/{$user->id}/quota", [
                'monthly_token_limit' => 20000000,
                'daily_token_limit' => 1000000,
                'add_bonus_tokens' => 500000,
            ]);

        $response->assertStatus(200);

        $quota->refresh();
        // 20000000 + 500000 bonus = 20500000
        $this->assertEquals(20500000, $quota->monthly_token_limit);
        $this->assertEquals(1000000, $quota->daily_token_limit);
    }
}
