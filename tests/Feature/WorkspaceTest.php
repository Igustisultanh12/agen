<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Project\WorkspaceService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    protected WorkspaceService $workspaceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspaceService = app(WorkspaceService::class);
        Storage::fake('local');
    }

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/projects', [
                'name' => 'Demo App',
                'description' => 'A test workspace project',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'uuid', 'name', 'workspace_path']);

        $this->assertDatabaseHas('projects', [
            'name' => 'Demo App',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_create_and_read_file_in_workspace(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'File Project',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $createResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/projects/{$project->id}/files", [
                'path' => 'src/index.ts',
                'content' => 'console.log("Hello from Antigravity!");',
            ]);

        $createResp->assertStatus(201);
        $fileId = $createResp->json('file.id');

        $readResp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/projects/{$project->id}/files/{$fileId}/content");

        $readResp->assertStatus(200)
            ->assertJson([
                'name' => 'index.ts',
                'content' => 'console.log("Hello from Antigravity!");',
            ]);
    }

    public function test_path_traversal_is_blocked_with_error(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Traverse Project',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/projects/{$project->id}/files", [
                'path' => '../../etc/passwd',
                'content' => 'malicious content',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'Path traversal or invalid path detected.']);
    }

    public function test_disallowed_extension_is_blocked(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Security Project',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/projects/{$project->id}/files", [
                'path' => 'payload.exe',
                'content' => 'binary code',
            ]);

        $response->assertStatus(422);
    }
}
