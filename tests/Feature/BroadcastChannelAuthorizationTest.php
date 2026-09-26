<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_channels_require_membership_or_the_matching_scoped_event_right(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $teamEditor = User::factory()->create();
        $legacyGlobalEditor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $otherTeam = Team::factory()->create(['club_id' => $otherClub->id]);

        $club->users()->attach([$member->id, $teamEditor->id], [
            'role' => 'member',
            'membership_status' => 'active',
        ]);
        $team->users()->attach($member->id, ['role' => 'Player']);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'realtime_event_editor',
            'name' => 'Termin-Echtzeitredaktion',
            'permissions' => [ClubPermissions::EVENTS_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $teamEditor->id,
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'scope_key' => 'team:'.$team->id,
            'assigned_by' => $owner->id,
        ]);

        Permission::findOrCreate('event.update', 'web');
        Permission::findOrCreate('event.create', 'web');
        $legacyGlobalEditor->givePermissionTo(['event.update', 'event.create']);

        $channels = Broadcast::getChannels();
        $teamChannel = $channels['events.team.{team}'];
        $clubChannel = $channels['events.club.{club}'];

        $this->assertTrue($teamChannel($member, $team));
        $this->assertTrue($clubChannel($member, $club));
        $this->assertTrue($teamChannel($teamEditor, $team));
        $this->assertFalse($teamChannel($teamEditor, $otherTeam));
        $this->assertFalse($clubChannel($teamEditor, $otherClub));
        $this->assertFalse($teamChannel($legacyGlobalEditor, $team));
        $this->assertFalse($clubChannel($legacyGlobalEditor, $club));
    }
}
