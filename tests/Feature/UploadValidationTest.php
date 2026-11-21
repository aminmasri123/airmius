<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Club;
use App\Models\Folder;
use App\Models\User;
use App\Support\UploadStorage;
use App\Support\ClubPermissions;
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

    public function test_club_owner_can_list_files_without_a_duplicate_membership_record(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->detach($owner->id);
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/uploads?scope=club&club_id='.$club->id)->assertOk();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/uploads?scope=club&club_id='.$club->id)->assertNotFound();
    }

    public function test_policy_uploads_are_private_scoped_and_filed_by_type(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->syncWithoutDetaching([$owner->id => ['role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active']]);
        Sanctum::actingAs($owner);
        foreach (['statutes' => 'Satzungen', 'regulation' => 'Ordnungen', 'contribution_model' => 'Beitragsordnungen', 'statutes' => 'Satzungen'] as $type => $name) {
            $id = $this->postJson('/api/v1/uploads', ['scope' => 'club', 'club_id' => $club->id,
                'policy_document_type' => $type, 'file' => UploadedFile::fake()->create('document.pdf', 4, 'application/pdf')])
                ->assertCreated()->json('data.id');
            $file = File::findOrFail($id);
            $folder = Folder::findOrFail($file->folder_id);
            $this->assertSame($name, $folder->name);
            $this->assertEquals($club->id, $folder->club_id);
            $this->assertSame('Satzungen und Ordnungen', $folder->parent->name);
            $this->assertTrue(UploadStorage::isPrivate($file->path));
            Storage::disk(UploadStorage::disk($file->path))->assertExists($file->path);
        }
        $this->assertSame(4, Folder::where('club_id', $club->id)->count());
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::POLICY_DOCUMENTS_EDIT => false]]);
        Sanctum::actingAs($member);
        $this->postJson('/api/v1/uploads', ['scope' => 'club', 'club_id' => $club->id,
            'policy_document_type' => 'statutes', 'file' => UploadedFile::fake()->create('denied.pdf', 4, 'application/pdf')])->assertForbidden();
        $this->assertSame(3, File::where('club_id', $club->id)->count());
    }

    public function test_api_upload_flow_covers_size_type_success_and_abort(): void
    {
        Storage::fake(UploadStorage::disk());
        Storage::fake(UploadStorage::privateDisk());

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
        Storage::disk(UploadStorage::disk($file->path))->assertExists($file->path);

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        Storage::disk(UploadStorage::disk($file->path))->assertMissing($file->path);
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
