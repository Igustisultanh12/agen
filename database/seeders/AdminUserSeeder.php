<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\AI\QuotaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $quotaService = app(QuotaService::class);

        $adminName = env('ADMIN_NAME', 'Admin Antigravity');
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'admin123456');

        $premiumGroup = UserGroup::where('slug', 'premium')->first();
        $freeGroup = UserGroup::where('slug', 'free')->first();

        // 1. Admin User
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'status' => 'active',
                'user_group_id' => $premiumGroup?->id,
                'custom_instructions' => 'Act as a Senior Principal Architect. Provide concise, clean, secure, and production-ready code.',
            ]
        );
        $quotaService->initializeUserQuota($admin);

        // 2. Demo Normal User
        $userEmail = env('USER_EMAIL', 'user@example.com');
        $userPassword = env('USER_PASSWORD', 'user123456');

        $user = User::updateOrCreate(
            ['email' => $userEmail],
            [
                'name' => 'Demo Developer',
                'password' => Hash::make($userPassword),
                'role' => 'user',
                'status' => 'active',
                'user_group_id' => $freeGroup?->id,
                'custom_instructions' => 'Gunakan Laravel 12, Vue 3 Composition API dan TypeScript.',
            ]
        );
        $quotaService->initializeUserQuota($user);
    }
}
