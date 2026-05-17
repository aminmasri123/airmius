<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAreaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_assign_high_risk_permissions(): void
    {
        Permission::create(['name' => 'users.assign_roles', 'guard_name' => 'web']);
        Permission::create(['name' => 'system.manage', 'guard_name' => 'web']);

        $managerRole = Role::create(['name' => 'role_manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo('users.assign_roles');

        $user = User::factory()->create();
        $user->assignRole($managerRole);

        $targetRole = Role::create(['name' => 'team_helper', 'guard_name' => 'web']);

        $response = $this
            ->actingAs($user)
            ->put(route('roles.update', $targetRole), [
                'description' => 'Team helper',
                'permissions' => ['system.manage'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('authorization');
        $this->assertFalse($targetRole->fresh()->hasPermissionTo('system.manage'));
    }

    public function test_unverified_admin_user_cannot_open_admin_area(): void
    {
        Permission::create(['name' => 'system.manage', 'guard_name' => 'web']);

        $role = Role::create(['name' => 'system_operator', 'guard_name' => 'web']);
        $role->givePermissionTo('system.manage');

        $user = User::factory()->unverified()->create();
        $user->assignRole($role);

        $this
            ->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }
}
