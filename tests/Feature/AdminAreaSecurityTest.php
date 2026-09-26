<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_platform_admin_without_confirmed_two_factor_is_redirected_to_settings(): void
    {
        [$user] = $this->platformAdmin();

        $this
            ->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('auth.settings', ['tab' => 'security']))
            ->assertSessionHas('error', AdminTwoFactor::MESSAGE);
    }

    public function test_platform_admin_with_confirmed_two_factor_can_open_admin_area(): void
    {
        [$user] = $this->platformAdmin([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertOk();
    }

    public function test_platform_admin_api_requires_confirmed_two_factor(): void
    {
        [$user] = $this->platformAdmin();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/commerce')
            ->assertForbidden()
            ->assertJsonPath('code', AdminTwoFactor::ERROR_CODE)
            ->assertJsonPath('message', AdminTwoFactor::MESSAGE);
    }

    public function test_platform_admin_mutations_require_fresh_password_step_up(): void
    {
        [$user] = $this->platformAdmin([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.sports.store'), [
                'name' => 'Sensitive Sport',
                'slug' => 'sensitive-sport',
                'category' => 'team',
            ])
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('error', AdminTwoFactor::STEP_UP_MESSAGE);
    }

    public function test_platform_admin_api_requires_fresh_mobile_step_up_token(): void
    {
        [$user] = $this->platformAdmin([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/commerce')
            ->assertForbidden()
            ->assertJsonPath('code', AdminTwoFactor::STEP_UP_ERROR_CODE)
            ->assertJsonPath('message', AdminTwoFactor::STEP_UP_MESSAGE);

        Sanctum::actingAs($user, ['*', AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);

        $this->getJson('/api/v1/admin/commerce')->assertOk();
    }

    private function platformAdmin(array $attributes = []): array
    {
        Permission::findOrCreate('system.manage', 'web');
        Permission::findOrCreate('commerce.manage', 'web');

        $role = Role::findOrCreate('super_admin', 'web');
        $role->givePermissionTo(['system.manage', 'commerce.manage']);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return [$user, $role];
    }
}
