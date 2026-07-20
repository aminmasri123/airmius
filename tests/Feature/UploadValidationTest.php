<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_upload_flow_covers_size_type_success_and_abort(): void
    {
        Storage::fake(UploadStorage::disk());

        $user = User::factory()->create();
        $this->grantUserPermissions($user, ['file.upload', 'file.view', 'file.delete']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/files/upload-intents', [
            'scope' => 'user',
            'file_name' => 'zu-gross.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => (51200 * 1024) + 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['size_bytes']);

        $this->postJson('/api/v1/uploads', [
            'scope' => 'user',
            'file' => UploadedFile::fake()->create('malware.exe', 4, 'application/x-msdownload'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->postJson('/api/v1/files/upload-intents', [
            'scope' => 'user',
            'file_name' => 'training-plan.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.upload.endpoint', '/api/v1/uploads')
            ->assertJsonPath('data.file.file_name', 'training-plan.pdf')
            ->assertJsonPath('data.file.max_size_kb', 51200);

        $uploadedId = $this->postJson('/api/v1/uploads', [
            'scope' => 'user',
            'file' => UploadedFile::fake()->create('training-plan.pdf', 4, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'training-plan.pdf')
            ->assertJsonPath('data.user_id', $user->id)
            ->json('data.id');

        $file = File::query()->findOrFail($uploadedId);
        Storage::disk(UploadStorage::disk())->assertExists($file->path);

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        Storage::disk(UploadStorage::disk())->assertMissing($file->path);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    private function grantUserPermissions(User $user, array $permissions): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
