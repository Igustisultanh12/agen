<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('model_providers')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('coding_agents')->nullOnDelete();
            $table->string('internal_request_id')->index();
            $table->string('provider_request_id')->nullable()->index();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0.000000);
            $table->decimal('actual_cost', 12, 6)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();
            $table->enum('usage_source', ['provider', 'fcc', 'estimated'])->default('estimated');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['model_id', 'created_at']);
            $table->index(['provider_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('ai_request_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usage_log_id')->constrained('ai_usage_logs')->cascadeOnDelete();
            $table->string('internal_request_id')->index();
            $table->foreignId('provider_id')->constrained('model_providers')->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->unsignedInteger('latency_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('usage_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('model_providers')->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->cascadeOnDelete();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0.000000);
            $table->timestamps();

            $table->unique(['date', 'user_id', 'provider_id', 'model_id'], 'usage_daily_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_daily_stats');
        Schema::dropIfExists('ai_request_attempts');
        Schema::dropIfExists('ai_usage_logs');
    }
};
