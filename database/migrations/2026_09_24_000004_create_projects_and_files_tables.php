<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('workspace_path'); // isolated workspace folder e.g. workspaces/{uuid}
            $table->json('settings')->nullable();
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'editor', 'viewer'])->default('editor');
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('path', 500); // relative path within workspace e.g. src/index.ts
            $table->string('filename');
            $table->string('extension', 50)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('storage_path')->nullable(); // disk storage location
            $table->boolean('is_directory')->default(false);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('project_files')->cascadeOnDelete();
            $table->index(['project_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
