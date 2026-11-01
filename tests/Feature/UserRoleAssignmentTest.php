<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserRoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $manage = Permission::findOrCreate('users.assign_roles', 'web');
        $read = Permission::findOrCreate('users.view', 'web');
        $read->update(['description' => 'Benutzer ansehen']);
        $role = Role::create(['name' => 'test_reader', 'guard_name' => 'web']);
        $role->givePermissionTo($read);
        $actor = User::factory()->create();
        $actor->givePermissionTo([$manage, $read]);

        return [$actor, User::factory()->create(), $role];
    }

    public function test_api_assignment_notifies_with_descriptions_and_does_not_duplicate_on_noop(): void
    {
        [$actor, $target, $role] = $this->fixtures();
        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/admin/platform/role-assignments?user_id='.$target->id)
            ->assertOk()->assertJsonPath('data.users.data.0.id', $target->id);
        $this->patchJson('/api/v1/admin/platform/role-assignments/'.$target->id, [
            'roles' => [$role->name], 'expected_roles' => [],
        ])->assertOk();
        $notice = Notification::where('user_id', $target->id)->sole();
        $this->assertSame('security.roles_changed', $notice->type);
        $this->assertStringContainsString('Benutzer ansehen', $notice->data['body']);
        $this->assertSame(['test_reader'], $notice->data['roles']);
        $this->assertSame('users.view', $notice->data['added_permissions'][0]['name']);
        $this->patchJson('/api/v1/admin/platform/role-assignments/'.$target->id, [
            'roles' => [$role->name], 'expected_roles' => [$role->name],
        ])->assertOk();
        $this->assertSame(1, Notification::where('user_id', $target->id)->count());
    }

    public function test_web_endpoint_revokes_and_notifies(): void
    {
        [$actor, $target, $role] = $this->fixtures();
        $target->assignRole($role);
        $this->actingAs($actor, 'web')->patchJson('/admin/role-assignments/'.$target->id, [
            'roles' => [], 'expected_roles' => [$role->name],
        ])->assertOk();
        $this->assertFalse($target->fresh()->hasRole($role->name));
        $this->assertFalse($target->fresh()->can('users.view'));
        $this->assertSame('users.view', Notification::where('user_id', $target->id)->sole()->data['removed_permissions'][0]['name']);
    }

    public function test_stale_edits_and_self_assignment_are_rejected(): void
    {
        [$actor, $target, $role] = $this->fixtures();
        $target->assignRole($role);
        Sanctum::actingAs($actor);
        $this->patchJson('/api/v1/admin/platform/role-assignments/'.$target->id, [
            'roles' => [], 'expected_roles' => [],
        ])->assertStatus(409);
        $this->patchJson('/api/v1/admin/platform/role-assignments/'.$actor->id, [
            'roles' => [$role->name], 'expected_roles' => [],
        ])->assertForbidden();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_role_manager_cannot_grant_protected_or_foreign_guard_or_stronger_roles(): void
    {
        [$actor, $target] = $this->fixtures();
        Role::findOrCreate('super_admin', 'web');
        Role::create(['name' => 'foreign_role', 'guard_name' => 'other']);
        $dangerous = Permission::findOrCreate('system.manage', 'web');
        $strong = Role::create(['name' => 'strong_role', 'guard_name' => 'web']);
        $strong->givePermissionTo($dangerous);
        Sanctum::actingAs($actor);
        foreach (['super_admin' => 422, 'foreign_role' => 422, 'strong_role' => 403] as $name => $status) {
            $this->patchJson('/api/v1/admin/platform/role-assignments/'.$target->id, [
                'roles' => [$name], 'expected_roles' => [],
            ])->assertStatus($status);
        }
        $this->assertDatabaseCount('notifications', 0);
        $this->assertCount(0, $target->fresh()->roles);
    }

    public function test_unauthorized_user_cannot_search_or_assign(): void
    {
        [, $target, $role] = $this->fixtures();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/platform/role-assignments')->assertForbidden();
        $this->patchJson('/api/v1/admin/platform/role-assignments/'.$target->id, [
            'roles' => [$role->name], 'expected_roles' => [],
        ])->assertForbidden();
    }

    public function test_permission_changes_notify_members_and_keep_permissions_from_other_roles(): void
    {
        [$actor, $target, $role] = $this->fixtures();
        $other = Role::create(['name' => 'other_reader', 'guard_name' => 'web']);
        $other->givePermissionTo('users.view');
        $target->assignRole([$role, $other]);
        $second = User::factory()->create();
        $second->assignRole($role);
        app(UserRoleAssignmentService::class)->changePermissions($role, []);
        $this->assertTrue($target->fresh()->can('users.view'));
        $this->assertFalse($second->fresh()->can('users.view'));
        $this->assertSame(0, Notification::where('user_id', $target->id)->count());
        $this->assertSame('users.view', Notification::where('user_id', $second->id)->sole()->data['removed_permissions'][0]['name']);
    }

    public function test_existing_web_user_editor_also_notifies(): void
    {
        [$actor, $target, $role] = $this->fixtures();
        $actor->givePermissionTo(Permission::findOrCreate('users.edit', 'web'));
        $this->actingAs($actor, 'web')->put('/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'profile_visibility' => 'private',
            'roles' => [$role->name],
        ])->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole($role->name));
        $this->assertSame('security.roles_changed', Notification::where('user_id', $target->id)->sole()->type);
    }

    public function test_new_admin_is_informed_that_two_factor_setup_is_required(): void
    {
        [$actor, $target] = $this->fixtures();
        $admin = Role::findOrCreate('super_admin', 'web');
        $actor->assignRole($admin);
        app(UserRoleAssignmentService::class)->assign($actor, $target, ['super_admin']);
        $notice = Notification::where('user_id', $target->id)->sole();
        $this->assertTrue($notice->data['admin_two_factor_setup_required']);
        $this->assertStringContainsString('Zwei-Faktor-Authentifizierung eingerichtet und bestätigt', $notice->data['body']);
    }

    public function test_web_and_api_role_permission_editors_notify_assigned_people(): void
    {
        [$actor, $target, $role] = $this->fixtures();
        $target->assignRole($role);
        $this->actingAs($actor, 'web')->put('/admin/roles/'.$role->id, [
            'permissions' => [],
        ])->assertRedirect();
        $this->assertSame('users.view', Notification::where('user_id', $target->id)->sole()->data['removed_permissions'][0]['name']);

        Sanctum::actingAs($actor);
        $this->patchJson('/api/v1/admin/platform/roles/'.$role->id, [
            'permissions' => ['users.view'],
        ])->assertOk();
        $notices = Notification::where('user_id', $target->id)->orderBy('id')->get();
        $this->assertCount(2, $notices);
        $this->assertSame('users.view', $notices->last()->data['added_permissions'][0]['name']);
    }
}
