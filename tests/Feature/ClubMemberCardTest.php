<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_receives_rotating_minimal_card_token(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($member);

        $first = $this->getJson("/api/v1/clubs/{$club->id}/member-card")
            ->assertOk()
            ->assertJsonPath('data.member.name', $member->name)
            ->assertJsonPath('data.member.membership_status', 'active')
            ->assertJsonPath('data.hidden_claims.0', 'email')
            ->assertJson(fn ($json) => $json
                ->whereType('data.token.qr_svg_data_uri', 'string')
                ->etc());

        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $first->json('data.token.qr_svg_data_uri'),
        );
        $this->assertStringContainsString(
            '<svg',
            base64_decode(str_replace('data:image/svg+xml;base64,', '', $first->json('data.token.qr_svg_data_uri'))),
        );

        $firstToken = $first->json('data.token.value');

        $secondToken = $this->postJson("/api/v1/clubs/{$club->id}/member-card/rotate")
            ->assertOk()
            ->json('data.token.value');

        $this->assertNotSame($firstToken, $secondToken);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $firstToken,
        ])->assertStatus(422);

        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $secondToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.member.id', $member->id);
    }

    public function test_manager_can_check_member_in_to_a_club_event_and_member_cannot_verify_cards(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $club->users()->attach($outsider->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'title' => 'Abendtraining',
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => now()->addHour(),
        ]);

        Sanctum::actingAs($member);
        $token = $this->getJson("/api/v1/clubs/{$club->id}/member-card")
            ->json('data.token.value');

        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $token,
            'event_id' => $event->id,
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $token,
            'event_id' => $event->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.member.id', $member->id);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'yes',
            'response_mode' => 'member_card',
            'check_in_method' => 'member_card',
        ]);
        $this->assertNotNull(
            EventParticipant::query()
                ->where('event_id', $event->id)
                ->where('user_id', $member->id)
                ->value('checked_in_at'),
        );
    }

    public function test_membership_manager_can_verify_a_card_without_an_event(): void
    {
        $owner = User::factory()->create();
        $cardholder = User::factory()->create();
        $scanner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($cardholder->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $club->users()->attach($scanner->id, [
            'role' => 'academy_manager',
            'roles' => ['academy_manager'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($cardholder);
        $token = $this->getJson("/api/v1/clubs/{$club->id}/member-card")
            ->assertOk()
            ->json('data.token.value');

        Sanctum::actingAs($scanner);
        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $token,
        ])
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.member.id', $cardholder->id)
            ->assertJsonPath('data.event', null);
    }

    public function test_active_member_can_customize_their_card_design(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);

        Sanctum::actingAs($member);

        $payload = [
            'accent_color' => '#34D399',
            'background_color' => '#111827',
            'text_color' => '#FFFFFF',
            'style' => 'sport',
            'show_profile_photo' => true,
            'show_member_number' => false,
        ];

        $this->putJson("/api/v1/clubs/{$club->id}/member-card/design", $payload)
            ->assertOk()
            ->assertJsonPath('data.design.accent_color', '#34D399')
            ->assertJsonPath('data.design.style', 'sport')
            ->assertJsonPath('data.design.show_member_number', false)
            ->assertJsonPath('data.club.name', $club->name);

        $this->getJson("/api/v1/clubs/{$club->id}/member-card")
            ->assertOk()
            ->assertJsonPath('data.design.background_color', '#111827')
            ->assertJsonPath('data.design.show_member_number', false);

        Sanctum::actingAs($outsider);

        $this->putJson("/api/v1/clubs/{$club->id}/member-card/design", $payload)
            ->assertForbidden();
    }

    public function test_department_event_editor_can_verify_cards_only_for_events_in_their_scope(): void
    {
        $owner = User::factory()->create();
        $cardholder = User::factory()->create();
        $scanner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$cardholder, $scanner] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ]);
        }
        $department = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Jugend',
        ]);
        $otherDepartment = ClubDepartment::query()->create([
            'club_id' => $club->id,
            'name' => 'Senioren',
        ]);
        $team = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
        ]);
        $otherTeam = Team::factory()->create([
            'club_id' => $club->id,
            'club_department_id' => $otherDepartment->id,
        ]);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'title' => 'Jugendtraining',
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => now()->addHour(),
        ]);
        $otherEvent = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $otherTeam->id,
            'title' => 'Seniorentraining',
            'type' => 'training',
            'visibility' => 'organization',
            'start_time' => now()->addHours(2),
        ]);
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'department_check_in',
            'name' => 'Abteilungs-Check-in',
            'permissions' => [ClubPermissions::EVENTS_EDIT],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $scanner->id,
            'scope_type' => 'department',
            'scope_id' => $department->id,
            'scope_key' => 'department:'.$department->id,
            'assigned_by' => $owner->id,
        ]);

        Sanctum::actingAs($cardholder);
        $token = $this->getJson("/api/v1/clubs/{$club->id}/member-card")
            ->assertOk()
            ->json('data.token.value');

        Sanctum::actingAs($scanner);
        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $token,
        ])->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $token,
            'event_id' => $otherEvent->id,
        ])->assertForbidden();
        $this->postJson("/api/v1/clubs/{$club->id}/member-card/verify", [
            'token' => $token,
            'event_id' => $event->id,
        ])->assertOk()
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.member.id', $cardholder->id);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id' => $cardholder->id,
            'check_in_method' => 'member_card',
        ]);
        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $otherEvent->id,
            'user_id' => $cardholder->id,
        ]);
    }
}
