<?php

namespace Database\Seeders;

use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class UserGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => 'Free Tier',
                'slug' => 'free',
                'description' => 'Default tier with access to free and local AI models.',
                'monthly_token_limit' => 2000000,
                'daily_token_limit' => 200000,
                'weekly_token_limit' => 1000000,
                'request_limit_per_minute' => 20,
                'concurrent_session_limit' => 2,
                'max_projects' => 3,
                'max_storage_bytes' => 52428800, // 50MB
                'max_file_size_bytes' => 5242880, // 5MB
                'is_default' => true,
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'description' => 'Ideal for daily coding with expanded token quota.',
                'monthly_token_limit' => 10000000,
                'daily_token_limit' => 1000000,
                'weekly_token_limit' => 5000000,
                'request_limit_per_minute' => 60,
                'concurrent_session_limit' => 5,
                'max_projects' => 10,
                'max_storage_bytes' => 209715200, // 200MB
                'max_file_size_bytes' => 10485760, // 10MB
                'is_default' => false,
            ],
            [
                'name' => 'Developer',
                'slug' => 'developer',
                'description' => 'Full access to high-performance coding models and agents.',
                'monthly_token_limit' => 30000000,
                'daily_token_limit' => 3000000,
                'weekly_token_limit' => 15000000,
                'request_limit_per_minute' => 120,
                'concurrent_session_limit' => 10,
                'max_projects' => 30,
                'max_storage_bytes' => 524288000, // 500MB
                'max_file_size_bytes' => 20971520, // 20MB
                'is_default' => false,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Uncapped high-concurrency plan for enterprise developers.',
                'monthly_token_limit' => 100000000,
                'daily_token_limit' => 10000000,
                'weekly_token_limit' => 50000000,
                'request_limit_per_minute' => 300,
                'concurrent_session_limit' => 25,
                'max_projects' => 100,
                'max_storage_bytes' => 2147483648, // 2GB
                'max_file_size_bytes' => 52428800,  // 50MB
                'is_default' => false,
            ],
        ];

        foreach ($groups as $data) {
            UserGroup::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
