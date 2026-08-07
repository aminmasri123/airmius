<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\File;
use App\Models\FileShare;
use App\Models\Folder;
use App\Models\Friendship;
use App\Models\Team;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FileManagerFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function grantUserPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }

    public function test_index_search_escapes_like_special_characters(): void
    {
        $user = User::factory()->create();
        $this->grantUserPermissions($user, ['file.view']);

        File::create([
            'user_id' => $user->id,
            'path' => 'private/report-percent.txt',
            'display_name' => 'report%.txt',
            'type' => 'text/plain',
            'size' => 128,
        ]);

        File::create([
            'user_id' => $user->id,
            'path' => 'private/report-plain.txt',
            'display_name' => 'report-plain.txt',
            'type' => 'text/plain',
            'size' => 128,
        ]);

        $this->actingAs($user)
            ->get(route('auth.files.index', ['search' => '%']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.total', 1)
                ->where('files.data.0.display_name', 'report%.txt')
            );
    }

    public function test_index_is_paginated_for_files(): void
    {
        $user = User::factory()->create();
        $this->grantUserPermissions($user, ['file.view']);

        for ($i = 1; $i <= 15; $i++) {
            File::create([
                'user_id' => $user->id,
                'path' => "private/file-{$i}.txt",
                'display_name' => sprintf('file-%02d.txt', $i),
                'type' => 'text/plain',
                'size' => 64 + $i,
            ]);
        }

        $this->actingAs($user)
            ->get(route('auth.files.index', ['per_page' => 12, 'files_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.current_page', 2)
                ->where('files.per_page', 12)
                ->where('files.total', 15)
                ->where('files.data.0.display_name', 'file-13.txt')
            );
    }

    public function test_unified_sort_and_page_size_control_files_and_folders(): void
    {
        $user = User::factory()->create();
        $this->grantUserPermissions($user, ['file.view']);

        Folder::create([
            'user_id' => $user->id,
            'name' => 'Alpha Folder',
            'parent_id' => null,
        ]);
        Folder::create([
            'user_id' => $user->id,
            'name' => 'Zulu Folder',
            'parent_id' => null,
        ]);

        File::create([
            'user_id' => $user->id,
            'path' => 'private/alpha.txt',
            'display_name' => 'alpha.txt',
            'type' => 'text/plain',
            'size' => 12,
        ]);
        File::create([
            'user_id' => $user->id,
            'path' => 'private/zeta.txt',
            'display_name' => 'zeta.txt',
            'type' => 'text/plain',
            'size' => 12,
        ]);

        $this->actingAs($user)
            ->get(route('auth.files.index', ['sort' => 'name-desc', 'per_page' => 12]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', 'name-desc')
                ->where('files.per_page', 12)
                ->where('folders.per_page', 12)
                ->where('files.data.0.display_name', 'zeta.txt')
                ->where('folders.data.0.name', 'Zulu Folder')
            );
    }

    public function test_file_sort_fallbacks_to_whitelisted_default_for_invalid_sort_parameter(): void
    {
        $user = User::factory()->create();
        $this->grantUserPermissions($user, ['file.view']);

        File::create([
            'user_id' => $user->id,
            'path' => 'private/c.txt',
            'display_name' => 'zeta.txt',
            'type' => 'text/plain',
            'size' => 12,
        ]);
        File::create([
            'user_id' => $user->id,
            'path' => 'private/b.txt',
            'display_name' => 'alpha.txt',
            'type' => 'text/plain',
            'size' => 12,
        ]);
        File::create([
            'user_id' => $user->id,
            'path' => 'private/b.txt',
            'display_name' => 'beta.txt',
            'type' => 'text/plain',
            'size' => 12,
        ]);

        $this->actingAs($user)
            ->get(route('auth.files.index', ['file_sort' => 'DROP TABLE']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.data.0.display_name', 'alpha.txt')
            );
    }

    public function test_deleting_folder_removes_nested_folders_and_files(): void
    {
        $user = User::factory()->create();
        $this->grantUserPermissions($user, ['file.view']);

        $root = Folder::create([
            'user_id' => $user->id,
            'name' => 'Root',
            'parent_id' => null,
        ]);

        $child = Folder::create([
            'user_id' => $user->id,
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        $rootFile = File::create([
            'user_id' => $user->id,
            'folder_id' => $root->id,
            'path' => 'private/root.txt',
            'display_name' => 'root.txt',
            'type' => 'text/plain',
            'size' => 32,
        ]);

        $childFile = File::create([
            'user_id' => $user->id,
            'folder_id' => $child->id,
            'path' => 'private/child.txt',
            'display_name' => 'child.txt',
            'type' => 'text/plain',
            'size' => 32,
        ]);

        $this->actingAs($user)
            ->delete(route('auth.folders.destroy', $root))
            ->assertRedirect();

        $this->assertDatabaseMissing('folders', ['id' => $root->id]);
        $this->assertDatabaseMissing('folders', ['id' => $child->id]);
        $this->assertDatabaseMissing('files', ['id' => $rootFile->id]);
        $this->assertDatabaseMissing('files', ['id' => $childFile->id]);
    }

    public function test_folder_sharing_creates_unique_copy_name_when_target_already_has_same_folder_name(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();

        $this->grantUserPermissions($owner, ['file.view', 'file.upload']);
        $this->grantUserPermissions($target, ['file.view']);

        $source = Folder::create([
            'user_id' => $owner->id,
            'name' => 'Projektordner',
            'parent_id' => null,
        ]);

        $targetExisting = Folder::create([
            'user_id' => $target->id,
            'name' => 'Projektordner',
            'parent_id' => null,
        ]);

        Friendship::create([
            'user_id' => $owner->id,
            'friend_id' => $target->id,
        ]);

        $this->actingAs($owner)
            ->post(route('auth.folders.share', $source), [
                'target_type' => 'user',
                'target_id' => $target->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('folders', [
            'user_id' => $target->id,
            'parent_id' => null,
            'name' => 'Projektordner (Kopie)',
        ]);

        $this->assertDatabaseMissing('folders', [
            'user_id' => $target->id,
            'parent_id' => null,
            'name' => 'Projektordner (Kopie) (Kopie)',
        ]);
    }

    public function test_api_personal_file_workspace_allows_owner_and_blocks_other_users(): void
    {
        Storage::fake(UploadStorage::disk());

        $owner = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($owner);

        $folderId = $this->postJson('/api/v1/files/folders', [
            'scope' => 'user',
            'name' => 'Trainingsplaene',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Trainingsplaene')
            ->json('data.id');

        $this->postJson('/api/v1/uploads', [
            'scope' => 'user',
            'folder_id' => $folderId,
            'file' => UploadedFile::fake()->image('plan.jpg', 24, 24),
        ])
            ->assertCreated()
            ->assertJsonPath('data.folder_id', $folderId)
            ->assertJsonPath('data.display_name', 'plan.jpg')
            ->assertJsonPath('data.preview_url', fn ($value) => str_ends_with((string) $value, "/api/v1/files/".File::query()->where('folder_id', $folderId)->value('id')."/preview"));

        $file = File::query()->where('folder_id', $folderId)->firstOrFail();

        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/files/folders/{$folderId}", [
            'name' => 'Fremder Name',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertForbidden();

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/files/folders/{$folderId}", [
            'name' => 'Trainingsplaene 2026',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Trainingsplaene 2026');

        $this->patchJson("/api/v1/uploads/{$file->id}", [
            'display_name' => 'plan-final.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'plan-final.jpg');

        $this->deleteJson("/api/v1/files/folders/{$folderId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('folders', ['id' => $folderId]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_api_file_preview_uses_file_permissions(): void
    {
        Storage::fake(UploadStorage::disk());

        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/uploads', [
            'scope' => 'user',
            'file' => UploadedFile::fake()->image('preview.jpg', 24, 24),
        ])->assertCreated();

        $file = File::query()->where('user_id', $owner->id)->firstOrFail();

        $this->get("/api/v1/files/{$file->id}/preview")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="preview.jpg"');

        Sanctum::actingAs($other);

        $this->get("/api/v1/files/{$file->id}/preview")->assertForbidden();
    }

    public function test_api_scoped_uploads_and_folder_actions_respect_file_permissions(): void
    {
        Storage::fake(UploadStorage::disk());

        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $club->users()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);
        $team->users()->attach($member->id, ['role' => 'player']);

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/files/upload-intents', [
            'scope' => 'club',
            'club_id' => $club->id,
            'file_name' => 'verein.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 128,
        ])->assertForbidden();

        $this->postJson('/api/v1/files/folders', [
            'scope' => 'team',
            'team_id' => $team->id,
            'name' => 'Teamordner',
        ])->assertForbidden();

        $this->postJson('/api/v1/uploads', [
            'scope' => 'club',
            'club_id' => $club->id,
            'file' => UploadedFile::fake()->image('verein.jpg', 24, 24),
        ])->assertForbidden();

        $this->grantUserPermissions($member, ['file.upload', 'file.view']);

        $folderId = $this->postJson('/api/v1/files/folders', [
            'scope' => 'team',
            'team_id' => $team->id,
            'name' => 'Teamordner',
        ])
            ->assertCreated()
            ->assertJsonPath('data.team_id', $team->id)
            ->json('data.id');

        $this->postJson('/api/v1/files/upload-intents', [
            'scope' => 'team',
            'team_id' => $team->id,
            'folder_id' => $folderId,
            'file_name' => 'team.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 128,
        ])
            ->assertCreated()
            ->assertJsonPath('data.upload.form_fields.team_id', $team->id)
            ->assertJsonPath('data.upload.form_fields.folder_id', $folderId);

        $this->postJson('/api/v1/uploads', [
            'scope' => 'team',
            'team_id' => $team->id,
            'folder_id' => $folderId,
            'file' => UploadedFile::fake()->image('team.jpg', 24, 24),
        ])
            ->assertCreated()
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.folder_id', $folderId);

        $file = File::query()->where('folder_id', $folderId)->firstOrFail();

        $this->patchJson("/api/v1/uploads/{$file->id}", [
            'display_name' => 'team-final.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'team-final.jpg');

        $this->patchJson("/api/v1/files/folders/{$folderId}", [
            'name' => 'Teamordner final',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Teamordner final');

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertForbidden();

        $this->deleteJson("/api/v1/files/folders/{$folderId}")
            ->assertForbidden();

        $this->grantUserPermissions($member, ['file.delete']);

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson("/api/v1/files/folders/{$folderId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertDatabaseMissing('folders', ['id' => $folderId]);
    }

    public function test_api_file_share_creates_an_expiring_hashed_download_link(): void
    {
        Storage::fake(UploadStorage::disk());

        $user = User::factory()->create();
        $file = File::create([
            'user_id' => $user->id,
            'path' => 'private/shareable.pdf',
            'display_name' => 'shareable.pdf',
            'type' => 'application/pdf',
            'size' => 128,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/uploads/{$file->id}/share", [
            'expires_in_days' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.file_id', $file->id)
            ->assertJsonPath('data.expires_at', fn ($value) => is_string($value));

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertStringContainsString('/shared-files/'.$token, $response->json('data.url'));
        $this->assertDatabaseHas('file_shares', [
            'file_id' => $file->id,
            'shared_by_user_id' => $user->id,
            'email' => strtolower($user->email),
            'token_hash' => hash('sha256', $token),
        ]);

        $this->postJson("/api/v1/uploads/{$file->id}/share", [
            'expires_in_days' => 31,
        ])->assertUnprocessable();
    }

    public function test_api_folder_share_copies_the_tree_only_to_a_friend(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $outsider = User::factory()->create();

        $source = Folder::create([
            'user_id' => $owner->id,
            'name' => 'Teamunterlagen',
            'parent_id' => null,
        ]);
        File::create([
            'user_id' => $owner->id,
            'folder_id' => $source->id,
            'path' => 'private/teamunterlagen.txt',
            'display_name' => 'teamunterlagen.txt',
            'type' => 'text/plain',
            'size' => 64,
        ]);
        Friendship::create(['user_id' => $owner->id, 'friend_id' => $target->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/files/folders/{$source->id}/share", [
            'target_id' => $target->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.shared', true)
            ->assertJsonPath('data.folder_id', $source->id)
            ->assertJsonPath('data.target_user_id', $target->id);

        $copiedFolder = Folder::query()
            ->where('user_id', $target->id)
            ->where('name', 'Teamunterlagen')
            ->firstOrFail();
        $this->assertDatabaseHas('files', [
            'user_id' => $target->id,
            'folder_id' => $copiedFolder->id,
            'path' => 'private/teamunterlagen.txt',
        ]);

        $this->postJson("/api/v1/files/folders/{$source->id}/share", [
            'target_id' => $outsider->id,
        ])->assertForbidden();
    }

    public function test_web_scoped_file_and_folder_actions_respect_permissions(): void
    {
        Storage::fake(UploadStorage::disk());

        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id]);

        $club->users()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        $this->actingAs($member)
            ->post(route('auth.folders.store'), [
                'scope' => 'club',
                'club_id' => $club->id,
                'name' => 'Vereinsordner',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->grantUserPermissions($member, ['file.upload', 'file.view']);

        $this->actingAs($member)
            ->post(route('auth.folders.store'), [
                'scope' => 'club',
                'club_id' => $club->id,
                'name' => 'Vereinsordner',
            ])
            ->assertRedirect();

        $folder = Folder::query()->where('club_id', $club->id)->firstOrFail();

        $this->actingAs($member)
            ->post(route('auth.files.store'), [
                'scope' => 'club',
                'club_id' => $club->id,
                'folder_id' => $folder->id,
                'file' => UploadedFile::fake()->image('satzung.jpg', 24, 24),
            ])
            ->assertRedirect();

        $file = File::query()->where('folder_id', $folder->id)->firstOrFail();

        $this->actingAs($member)
            ->put(route('auth.files.update', $file), [
                'display_name' => 'satzung-final.jpg',
            ])
            ->assertRedirect();

        $this->actingAs($member)
            ->delete(route('auth.files.destroy', $file))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->grantUserPermissions($member, ['file.delete']);

        $this->actingAs($member)
            ->delete(route('auth.files.destroy', $file))
            ->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }
}
