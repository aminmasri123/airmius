<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Folder;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
}
