<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('title')->default('New Chat');
            $table->foreignId('provider_id')->nullable()->constrained('model_providers')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('coding_agents')->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active')->index();
            $table->boolean('auto_title_generated')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('role', ['system', 'user', 'assistant', 'tool'])->default('user');
            $table->longText('content');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0.000000);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('is_streaming')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
