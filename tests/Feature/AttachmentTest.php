<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    public function test_user_can_upload_chat_attachment(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/chat/upload-attachment', [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'size',
                'extension',
                'mime_type',
                'url',
                'storage_path',
                'is_image',
            ]);

        $this->assertEquals('document.pdf', $response->json('name'));
        $this->assertEquals('pdf', $response->json('extension'));
        $this->assertFalse($response->json('is_image'));

        Storage::disk('public')->assertExists($response->json('storage_path'));
    }

    public function test_user_can_upload_image_attachment(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $image = UploadedFile::fake()->create('diagram.png', 100, 'image/png');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/chat/upload-attachment', [
                'file' => $image,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('is_image', true);

        $this->assertNotNull($response->json('base64'));
        $this->assertStringStartsWith('data:image/', $response->json('base64'));
    }
}
