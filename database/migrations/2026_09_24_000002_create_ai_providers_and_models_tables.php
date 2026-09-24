<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('custom'); // nvidia_nim, open_router, groq, gemini, deepseek, ollama, lmstudio, etc.
            $table->string('base_url');
            $table->text('api_key_encrypted')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->integer('priority')->default(1);
            $table->enum('health_status', ['healthy', 'warning', 'offline'])->default('healthy')->index();
            $table->integer('latency_ms')->default(0);
            $table->decimal('failure_rate', 5, 2)->default(0.00);
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('model_providers')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider_model_id'); // e.g. nvidia/nemotron-3-super-120b-a12b or deepseek-coder
            $table->text('description')->nullable();
            $table->unsignedInteger('context_window')->default(128000);
            $table->unsignedInteger('max_tokens')->default(4096);
            $table->enum('category', ['free', 'paid', 'local'])->default('free')->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->enum('visibility', ['all', 'user_group', 'admin'])->default('all')->index();
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_streaming')->default(true);
            $table->boolean('supports_tools')->default(true);
            $table->timestamps();
        });

        Schema::create('model_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('model_providers')->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->decimal('input_price_per_1m', 12, 6)->default(0.000000);
            $table->decimal('output_price_per_1m', 12, 6)->default(0.000000);
            $table->decimal('cached_input_price_per_1m', 12, 6)->default(0.000000);
            $table->decimal('reasoning_price_per_1m', 12, 6)->default(0.000000);
            $table->string('currency', 10)->default('USD');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('coding_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('harness_type')->default('codex'); // claude, codex, opencode, pi, cline, hermes, dsh, grok, muse, aider
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('user_group_model', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->unique(['user_group_id', 'model_id']);
        });

        Schema::create('user_group_agent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('coding_agents')->cascadeOnDelete();
            $table->unique(['user_group_id', 'agent_id']);
        });

        Schema::create('fallback_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->foreignId('fallback_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->integer('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['primary_model_id', 'fallback_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fallback_models');
        Schema::dropIfExists('user_group_agent');
        Schema::dropIfExists('user_group_model');
        Schema::dropIfExists('coding_agents');
        Schema::dropIfExists('model_pricings');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('model_providers');
    }
};
