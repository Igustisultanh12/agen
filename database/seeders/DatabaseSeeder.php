<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserGroupSeeder::class,
            ProviderAndModelSeeder::class,
            CodingAgentSeeder::class,
            SystemSettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
