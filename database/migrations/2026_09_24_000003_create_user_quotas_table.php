<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_token_limit')->default(10000000);
            $table->unsignedBigInteger('daily_token_limit')->default(1000000);
            $table->unsignedBigInteger('weekly_token_limit')->default(5000000);
            $table->unsignedBigInteger('used_input_tokens')->default(0);
            $table->unsignedBigInteger('used_output_tokens')->default(0);
            $table->unsignedBigInteger('used_total_tokens')->default(0);
            $table->decimal('used_cost', 12, 6)->default(0.000000);
            $table->timestamp('daily_reset_at')->nullable();
            $table->timestamp('monthly_reset_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quotas');
    }
};
