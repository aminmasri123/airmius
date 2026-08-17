<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClubManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_see_clubs_outside_their_workspace(): void
    {
        $admin = $this->platformUser('admin');
        $foreignOwner = User::factory()->create();
        Club::factory()->create([
            'name' => 'Foreign Athletics Club',
            'owner_id' => $foreignOwner->id,
            'verification_status' => 'verified',
            'city' => 'Berlin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clubs.index', ['query' => 'Foreign Athletics']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/Clubs/Index')
                ->where('canDeleteClubs', false)
                ->has('clubs.data', 1)
                ->where('clubs.data.0.name', 'Foreign Athletics Club')
                ->where('clubs.data.0.owner.id', $foreignOwner->id)
                ->where('summary.total', 1)
            );
    }

    public function test_super_admin_can_delete_a_foreign_club_after_exact_name_confirmation(): void
    {
        $superAdmin = $this->platformUser('super_admin');
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Delete Me FC',
            'owner_id' => $foreignOwner->id,
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('admin.clubs.destroy', $club), [
                'confirmation_name' => 'Delete Me FC',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseHas('users', ['id' => $foreignOwner->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $foreignOwner->id,
            'type' => 'club.deleted_by_platform',
        ]);
    }

    public function test_club_deletion_requires_super_admin_and_exact_name(): void
    {
        $admin = $this->platformUser('admin');
        $superAdmin = $this->platformUser('super_admin');
        $club = Club::factory()->create([
            'name' => 'Protected Club',
            'owner_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.clubs.destroy', $club), [
                'confirmation_name' => 'Protected Club',
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->from(route('admin.clubs.index'))
            ->delete(route('admin.clubs.destroy', $club), [
                'confirmation_name' => 'Wrong name',
            ])
            ->assertRedirect(route('admin.clubs.index'))
            ->assertSessionHasErrors('confirmation_name');

        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    private function platformUser(string $roleName): User
    {
        $permission = Permission::findOrCreate('system.manage', 'web');
        $role = Role::findOrCreate($roleName, 'web');
        $role->givePermissionTo($permission);

        $user = User::factory()->create([
            'two_factor_secret' => 'encrypted-test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
