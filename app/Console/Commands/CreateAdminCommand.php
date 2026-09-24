<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\AI\QuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'admin:create 
                            {--email=admin@example.com : Admin email address} 
                            {--password=admin123456 : Admin password} 
                            {--name=Admin Antigravity : Admin display name}';

    /**
     * The console command description.
     */
    protected $description = 'Create or reset the Administrator account with active permissions and clear login rate limits';

    public function handle(QuotaService $quotaService): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');

        $this->info("Setting up Administrator account for: {$email}...");

        // Ensure at least one default user group exists
        $userGroup = UserGroup::where('slug', 'premium')->first()
            ?? UserGroup::where('slug', 'free')->first();

        if (!$userGroup) {
            $userGroup = UserGroup::create([
                'name' => 'Premium Tier',
                'slug' => 'premium',
                'description' => 'Unlimited administrative tier',
                'monthly_token_limit' => 0, // unlimited
                'daily_token_limit' => 0,
                'weekly_token_limit' => 0,
                'request_limit_per_minute' => 300,
                'concurrent_session_limit' => 50,
                'max_projects' => 100,
                'max_storage_bytes' => 10737418240, // 10 GB
                'max_file_size_bytes' => 104857600, // 100 MB
                'is_default' => false,
            ]);
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->name = $name;
            $user->password = Hash::make($password);
            $user->role = 'admin';
            $user->status = 'active';
            if ($userGroup) {
                $user->user_group_id = $userGroup->id;
            }
            $user->save();
            $this->info("Existing user found. Password and Admin role successfully updated!");
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
                'user_group_id' => $userGroup?->id,
                'custom_instructions' => 'Act as a Senior Principal Architect. Provide concise, clean, secure, and production-ready code.',
            ]);
            $this->info("New Admin user created successfully!");
        }

        // Initialize quota
        $quotaService->initializeUserQuota($user);

        // Clear any login throttle keys for this email
        RateLimiter::clear('login:' . Str::lower($email));

        $this->newLine();
        $this->table(
            ['Property', 'Value'],
            [
                ['User ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Password', $password],
                ['Role', $user->role],
                ['Status', $user->status],
                ['User Group', $user->userGroup?->name ?? 'None'],
                ['Total Users in Database', User::count()],
            ]
        );

        $this->newLine();
        $this->info("You can now sign in at your browser login screen with:");
        $this->line("  Email:    <comment>{$email}</comment>");
        $this->line("  Password: <comment>{$password}</comment>");

        return Command::SUCCESS;
    }
}
