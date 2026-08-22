<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
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
}
