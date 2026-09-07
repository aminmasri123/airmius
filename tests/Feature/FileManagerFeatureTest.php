<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\File;
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

    public function test_file_access_rights_summary_matches_effective_policy_on_web_and_api(): void
    {
        $owner = User::factory()->create();
        $this->grantUserPermissions($owner, ['file.view']);

        $personalFile = File::create([
            'user_id' => $owner->id,
            'path' => 'private/rights.txt',
            'display_name' => 'rights.txt',
            'type' => 'text/plain',
            'size' => 64,
        ]);

        $this->actingAs($owner)
            ->get(route('auth.files.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.data.0.id', $personalFile->id)
                ->where('files.data.0.access_rights.scope', 'personal')
                ->where('files.data.0.access_rights.rights.read.allowed', true)
                ->where('files.data.0.access_rights.rights.edit.allowed', true)
                ->where('files.data.0.access_rights.rights.share.allowed', true)
                ->where('files.data.0.access_rights.rights.delete.allowed', true)
                ->where('files.data.0.access_rights.rights.read.audience', 'owner')
            );

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/files?scope=user')
            ->assertOk()
            ->assertJsonPath('data.files.0.access_rights.scope', 'personal')
            ->assertJsonPath('data.files.0.access_rights.rights.read.allowed', true)
            ->assertJsonPath('data.files.0.access_rights.rights.edit.allowed', true)
            ->assertJsonPath('data.files.0.access_rights.rights.delete.allowed', true);
    }

    public function test_scoped_file_access_rights_show_audience_and_current_user_actions(): void
    {
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

        $file = File::create([
            'user_id' => $member->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'path' => 'private/team-rights.txt',
            'display_name' => 'team-rights.txt',
            'type' => 'text/plain',
            'size' => 64,
        ]);

        Sanctum::actingAs($member);

        $this->getJson("/api/v1/files?scope=team&team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.files.0.id', $file->id)
            ->assertJsonPath('data.files.0.access_rights.scope', 'team')
            ->assertJsonPath('data.files.0.access_rights.rights.read.audience', 'team_members')
            ->assertJsonPath('data.files.0.access_rights.rights.read.allowed', true)
            ->assertJsonPath('data.files.0.access_rights.rights.edit.allowed', false)
            ->assertJsonPath('data.files.0.access_rights.rights.share.allowed', true)
            ->assertJsonPath('data.files.0.access_rights.rights.delete.allowed', false);

        $this->grantUserPermissions($member, ['file.upload', 'file.delete']);

        $this->getJson("/api/v1/files?scope=team&team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.files.0.access_rights.rights.edit.allowed', true)
            ->assertJsonPath('data.files.0.access_rights.rights.delete.allowed', true);
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
            ->assertJsonPath('data.preview_url', fn ($value) => str_ends_with((string) $value, '/api/v1/files/'.File::query()->where('folder_id', $folderId)->value('id').'/preview'));

        $file = File::query()->where('folder_id', $folderId)->firstOrFail();

        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/files/folders/{$folderId}", [
            'name' => 'Fremder Name',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertForbidden();

        $this->deleteJson("/api/v1/files/folders/{$folderId}")->assertForbidden();
        $this->patchJson("/api/v1/uploads/{$file->id}", [
            'display_name' => 'Unauthorized rename.jpg',
        ])->assertForbidden();
        $this->getJson("/api/v1/files/{$file->id}/preview")->assertForbidden();
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $this->withHeader('X-App-Locale', $locale)->postJson('/api/v1/uploads', [
                'scope' => 'user',
                'folder_id' => $folderId,
                'file' => UploadedFile::fake()->image('unauthorized.jpg', 24, 24),
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('folder_id')
                ->assertJsonPath('errors.folder_id.0', trans('file_manager.folder_scope_mismatch', locale: $locale));
        }
        $this->withHeader('X-App-Locale', 'de');
        $this->assertDatabaseHas('folders', ['id' => $folderId, 'user_id' => $owner->id, 'name' => 'Trainingsplaene']);
        $this->assertDatabaseHas('files', ['id' => $file->id, 'user_id' => $owner->id, 'display_name' => 'plan.jpg']);
        $this->assertSame(1, File::query()->where('folder_id', $folderId)->count());

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

    public function test_api_file_share_copies_file_only_to_a_friend(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $outsider = User::factory()->create();
        $file = File::create([
            'user_id' => $user->id,
            'path' => 'private/shareable.pdf',
            'display_name' => 'shareable.pdf',
            'type' => 'application/pdf',
            'size' => 128,
        ]);
        Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/uploads/{$file->id}/share", [
            'target_user_id' => $friend->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.file_id', $file->id)
            ->assertJsonPath('data.shared', true)
            ->assertJsonPath('data.target_user_id', $friend->id);

        $this->assertDatabaseHas('files', [
            'user_id' => $friend->id,
            'path' => $file->path,
            'display_name' => $file->display_name,
        ]);

        $this->postJson("/api/v1/uploads/{$file->id}/share", [
            'target_user_id' => $outsider->id,
        ])->assertForbidden();
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

    public function test_club_owner_can_publish_a_team_document_without_becoming_a_team_member(): void
    {
        Storage::fake(UploadStorage::disk());

        $owner = User::factory()->create();
        $teamMember = User::factory()->create();
        $clubOnlyMember = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        $club->users()->syncWithoutDetaching([
            $clubOnlyMember->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);
        $team->users()->attach($teamMember->id, ['role' => 'Player']);
        $this->grantUserPermissions($owner, ['file.upload']);

        $this->actingAs($owner)
            ->get(route('auth.files.index', [
                'scope' => 'team',
                'team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('teams.0.id', $team->id)
                ->where('teams.0.name', $team->name)
            );

        Sanctum::actingAs($owner);
        $fileId = $this->postJson('/api/v1/uploads', [
            'scope' => 'team',
            'team_id' => $team->id,
            'file' => UploadedFile::fake()->create('uc31-teamrichtlinie.txt', 2, 'text/plain'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.access_rights.rights.read.audience', 'team_members')
            ->json('data.id');

        $this->getJson("/api/v1/files?scope=team&team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.files.0.id', $fileId)
            ->assertJsonPath('data.available_teams.0.id', $team->id);

        Sanctum::actingAs($teamMember);
        $this->getJson("/api/v1/files?scope=team&team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.files.0.id', $fileId);

        foreach ([$clubOnlyMember, $outsider] as $hiddenViewer) {
            Sanctum::actingAs($hiddenViewer);
            $this->getJson("/api/v1/files?scope=team&team_id={$team->id}")
                ->assertNotFound();
            $this->getJson("/api/v1/files/{$fileId}/preview")
                ->assertForbidden();
        }
    }

    public function test_web_team_upload_uses_json_and_updates_the_open_list_inline(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Auth/Dashboard/Files/Index.vue'));

        $this->assertStringContainsString("window.axios.post(route('api.v1.uploads.store')", $source);
        $this->assertStringContainsString('localFiles.value = [uploaded, ...localFiles.value.filter', $source);
        $this->assertStringContainsString('localFilesTotal.value += 1', $source);
        $this->assertStringNotContainsString("uploadForm.post(route('auth.files.store')", $source);
    }
}
