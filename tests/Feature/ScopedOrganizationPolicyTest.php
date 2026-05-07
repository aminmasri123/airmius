<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopedOrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_owner_permissions_are_limited_to_the_managed_club(): void
    {
        $clubOwnerRole = Role::create(['name' => 'club_owner']);

        collect(['org.manage', 'team.update', 'team.delete', 'team.kick', 'clubs.delete', 'teams.delete'])
            ->each(fn (string $permission) => Permission::create(['name' => $permission]));

        $clubOwnerRole->givePermissionTo(['org.manage', 'team.update', 'team.delete', 'team.kick', 'clubs.delete', 'teams.delete']);

        $actor = User::factory()->create();
        $otherOwner = User::factory()->create();

        $actor->assignRole($clubOwnerRole);

        $managedClub = Club::factory()->create(['owner_id' => $actor->id]);
        $managedTeam = Team::factory()->create(['club_id' => $managedClub->id]);

        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $otherTeam = Team::factory()->create(['club_id' => $otherClub->id]);

        $otherClub->users()->syncWithoutDetaching([
            $actor->id => ['role' => 'member'],
        ]);
        $otherTeam->users()->syncWithoutDetaching([
            $actor->id => ['role' => 'Player'],
        ]);

        $this->assertTrue($actor->can('update', $managedClub));
        $this->assertTrue($actor->can('delete', $managedTeam));

        $this->assertFalse($actor->can('update', $otherClub));
        $this->assertFalse($actor->can('delete', $otherTeam));
        $this->assertFalse($actor->can('update', $otherTeam));
    }
}
