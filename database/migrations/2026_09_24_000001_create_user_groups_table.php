<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_token_limit')->default(10000000);
            $table->unsignedBigInteger('daily_token_limit')->default(1000000);
            $table->unsignedBigInteger('weekly_token_limit')->default(5000000);
            $table->unsignedInteger('request_limit_per_minute')->default(60);
            $table->unsignedInteger('concurrent_session_limit')->default(5);
            $table->unsignedInteger('max_projects')->default(10);
            $table->unsignedBigInteger('max_storage_bytes')->default(104857600); // 100 MB
            $table->unsignedBigInteger('max_file_size_bytes')->default(10485760);  // 10 MB
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Add foreign key constraint to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('user_group_id')->references('id')->on('user_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_group_id']);
        });
        Schema::dropIfExists('user_groups');
    }
};
