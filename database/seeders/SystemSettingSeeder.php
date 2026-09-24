<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            [
                'key' => 'general.platform_name',
                'value' => 'AI Coding Workspace',
                'group' => 'general',
                'description' => 'The brand name of the AI platform',
            ],
            [
                'key' => 'general.allow_registration',
                'value' => 'true',
                'group' => 'general',
                'description' => 'Allow new users to sign up freely',
            ],

            // AI defaults
            [
                'key' => 'ai.default_system_prompt',
                'value' => 'You are an expert AI software engineer and coding assistant integrated into Free Claude Code Workspace. Write clean, modular, and maintainable code. Follow best practices for the chosen technologies.',
                'group' => 'ai',
                'description' => 'Default system prompt sent to AI models',
            ],
            [
                'key' => 'ai.stream_timeout_seconds',
                'value' => '120',
                'group' => 'ai',
                'description' => 'Maximum timeout for streaming connections',
            ],
            [
                'key' => 'ai.auto_title_generation',
                'value' => 'true',
                'group' => 'ai',
                'description' => 'Automatically generate chat titles from first prompt',
            ],

            // Cost budget alerts (Requirement 34, 73)
            [
                'key' => 'cost.monthly_budget_usd',
                'value' => '100.00',
                'group' => 'quota',
                'description' => 'Monthly platform spending budget in USD',
            ],
            [
                'key' => 'cost.warning_threshold_percent',
                'value' => '80',
                'group' => 'quota',
                'description' => 'Send warning alert when budget reaches this %',
            ],
            [
                'key' => 'cost.critical_threshold_percent',
                'value' => '95',
                'group' => 'quota',
                'description' => 'Critical alert threshold %',
            ],
            [
                'key' => 'cost.limit_reached_action',
                'value' => 'only_free', // block_all, only_free, only_local, warning_only (Requirement 73)
                'group' => 'quota',
                'description' => 'Action when platform budget hits 100%',
            ],

            // Security
            [
                'key' => 'security.rate_limit_per_minute',
                'value' => '60',
                'group' => 'security',
                'description' => 'Global API rate limit per minute per user',
            ],
            [
                'key' => 'security.allow_file_upload',
                'value' => 'true',
                'group' => 'security',
                'description' => 'Allow users to upload project files',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
