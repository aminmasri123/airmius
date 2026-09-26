<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubGovernanceBody;
use App\Models\ClubGovernanceMeeting;
use App\Models\ClubGovernanceMeetingDecision;
use App\Models\ClubYearPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubGovernanceMeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_meeting_with_governance_year_invitation_and_eligibility_snapshot(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mira Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Robin Gast',
            'email' => 'robin@example.test',
            'role' => 'member',
            'membership_status' => 'active',
        ]);
        $body = ClubGovernanceBody::query()->create([
            'club_id' => $club->id,
            'type' => 'board',
            'name' => 'Vorstand',
            'is_public' => false,
        ]);
        $year = ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'business',
            'name' => 'Vereinsjahr 2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        Sanctum::actingAs($owner);

        $meeting = $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings", [
            'type' => 'general_assembly',
            'title' => ' Mitgliederversammlung 2026 ',
            'status' => 'invited',
            'club_governance_body_id' => $body->id,
            'club_year_period_id' => $year->id,
            'scheduled_at' => '2026-05-15T18:00:00Z',
            'location_name' => 'Vereinsheim',
            'participant_scope' => 'club_members',
            'motions_due_on' => '2026-05-01',
            'invitation_sent_at' => '2026-04-15T09:00:00Z',
            'agenda_items' => [['title' => 'Entlastung', 'description' => 'Bericht und Aussprache']],
            'materials' => [['title' => 'Bericht', 'url' => 'https://example.test/bericht.pdf']],
            'decision_templates' => [['title' => 'Entlastung Vorstand', 'majority_rule' => 'simple', 'quorum' => 10]],
            'recipients' => [
                ['user_id' => $member->id, 'attendance_eligible' => true, 'voting_eligible' => true, 'delivery_status' => 'delivered', 'delivered_at' => '2026-04-15T09:05:00Z'],
                ['club_external_member_id' => $external->id, 'attendance_eligible' => true, 'voting_eligible' => false, 'delivery_status' => 'sent'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Mitgliederversammlung 2026')
            ->assertJsonPath('data.governance_body.name', 'Vorstand')
            ->assertJsonPath('data.year_period.name', 'Vereinsjahr 2026')
            ->assertJsonPath('data.eligibility.attendance_eligible', 2)
            ->assertJsonPath('data.eligibility.voting_eligible', 1)
            ->assertJsonPath('data.recipients.0.person.name', 'Mira Mitglied')
            ->assertJsonPath('data.versions.0.version', 1)
            ->json('data');

        $this->assertDatabaseHas('club_governance_meeting_versions', [
            'club_governance_meeting_id' => $meeting['id'],
            'version' => 1,
            'status' => 'invited',
        ]);

        $this->assertDatabaseHas('club_governance_meeting_recipients', [
            'club_governance_meeting_id' => $meeting['id'],
            'user_id' => $member->id,
            'attendance_eligible' => true,
            'voting_eligible' => true,
            'delivery_status' => 'delivered',
        ]);

        $activity = Activity::query()->where('type', 'club.governance.meeting.created')->latest('id')->firstOrFail();
        $this->assertSame('meeting', $activity->data['entity_type']);
        $this->assertSame(2, $activity->data['recipient_count']);
        $this->assertStringNotContainsString('Entlastung', json_encode($activity->data));
    }

    public function test_governance_members_can_read_but_not_create_meetings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $meeting = ClubGovernanceMeeting::query()->create([
            'club_id' => $club->id,
            'type' => 'board',
            'title' => 'Vorstandssitzung',
            'status' => 'draft',
            'participant_scope' => 'body',
        ]);
        $meeting->recipients()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'attendance_eligible' => true,
            'voting_eligible' => true,
            'delivery_status' => 'pending',
        ]);
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}/governance/meetings")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Vorstandssitzung')
            ->assertJsonPath('data.0.eligibility.voting_eligible', 1)
            ->assertJsonMissingPath('data.0.recipients');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings", [
            'type' => 'board',
            'title' => 'Nicht erlaubt',
            'status' => 'draft',
            'participant_scope' => 'body',
            'recipients' => [['user_id' => $member->id, 'attendance_eligible' => true, 'voting_eligible' => true, 'delivery_status' => 'pending']],
        ])->assertForbidden();
    }

    public function test_cross_club_body_year_and_recipients_are_rejected(): void
    {
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreign = Club::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignMember = User::factory()->create();
        $foreign->users()->attach($foreignMember->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $foreignBody = ClubGovernanceBody::query()->create([
            'club_id' => $foreign->id,
            'type' => 'board',
            'name' => 'Fremdvorstand',
            'is_public' => false,
        ]);
        $foreignYear = ClubYearPeriod::query()->create([
            'club_id' => $foreign->id,
            'type' => 'business',
            'name' => 'Fremdes Jahr',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings", [
            'type' => 'board',
            'title' => 'Manipuliert',
            'status' => 'draft',
            'club_governance_body_id' => $foreignBody->id,
            'club_year_period_id' => $foreignYear->id,
            'participant_scope' => 'body',
            'recipients' => [['user_id' => $foreignMember->id, 'attendance_eligible' => true, 'voting_eligible' => true, 'delivery_status' => 'pending']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['club_governance_body_id', 'club_year_period_id', 'recipients.0.user_id']);
    }

    public function test_owner_updates_governance_meeting_as_new_version_and_tracks_delivery_status(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Alex Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        Sanctum::actingAs($owner);

        $meeting = ClubGovernanceMeeting::query()->create([
            'club_id' => $club->id,
            'type' => 'general_assembly',
            'title' => 'Mitgliederversammlung',
            'status' => 'draft',
            'scheduled_at' => '2026-05-15 18:00:00',
            'participant_scope' => 'club_members',
            'motions_due_on' => '2026-05-01',
            'agenda_items' => [['title' => 'Bericht']],
            'materials' => [['title' => 'Einladung', 'url' => 'https://example.test/einladung.pdf']],
            'decision_templates' => [['title' => 'Entlastung Vorstand', 'majority_rule' => 'simple']],
        ]);
        $meeting->createVersion($owner->id);
        $recipient = $meeting->recipients()->create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'attendance_eligible' => true,
            'voting_eligible' => true,
            'delivery_status' => 'pending',
        ]);

        $this->putJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}", [
            'type' => 'general_assembly',
            'title' => 'Mitgliederversammlung aktualisiert',
            'status' => 'invited',
            'scheduled_at' => '2026-05-16T18:00:00Z',
            'location_name' => 'Saal 1',
            'participant_scope' => 'club_members',
            'motions_due_on' => '2026-05-02',
            'invitation_sent_at' => '2026-04-15T09:00:00Z',
            'agenda_items' => [['title' => 'Bericht'], ['title' => 'Wahlen']],
            'materials' => [['title' => 'Einladung v2', 'url' => 'https://example.test/einladung-v2.pdf']],
            'decision_templates' => [['title' => 'Wahlleitung bestimmen', 'majority_rule' => 'simple', 'quorum' => 5]],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Mitgliederversammlung aktualisiert')
            ->assertJsonPath('data.versions.0.version', 2)
            ->assertJsonPath('data.versions.1.version', 1);

        $this->assertDatabaseHas('club_governance_meeting_versions', [
            'club_governance_meeting_id' => $meeting->id,
            'version' => 2,
            'status' => 'invited',
        ]);

        $this->patchJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/recipients/{$recipient->id}/delivery", [
            'delivery_status' => 'delivered',
            'response_status' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('data.delivery_status', 'delivered')
            ->assertJsonPath('data.response_status', 'accepted')
            ->assertJsonPath('data.person.name', 'Alex Mitglied');

        $this->assertNotNull($recipient->fresh()->delivered_at);
    }

    public function test_delivery_status_update_rejects_recipient_from_other_club_meeting(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $meeting = ClubGovernanceMeeting::query()->create([
            'club_id' => $club->id,
            'type' => 'board',
            'title' => 'Vorstand',
            'status' => 'draft',
            'participant_scope' => 'body',
        ]);
        $foreignMeeting = ClubGovernanceMeeting::query()->create([
            'club_id' => $foreignClub->id,
            'type' => 'board',
            'title' => 'Fremd',
            'status' => 'draft',
            'participant_scope' => 'body',
        ]);
        $foreignRecipient = $foreignMeeting->recipients()->create([
            'club_id' => $foreignClub->id,
            'attendance_eligible' => true,
            'voting_eligible' => false,
            'delivery_status' => 'pending',
        ]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/recipients/{$foreignRecipient->id}/delivery", [
            'delivery_status' => 'delivered',
        ])->assertNotFound();
    }

    public function test_open_named_governance_decision_tracks_quorum_majority_abstention_and_locks_corrections(): void
    {
        $owner = User::factory()->create();
        $voterA = User::factory()->create(['name' => 'A Mitglied']);
        $voterB = User::factory()->create(['name' => 'B Mitglied']);
        $voterC = User::factory()->create(['name' => 'C Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $voterA->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $voterB->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
            $voterC->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);
        $meeting = ClubGovernanceMeeting::query()->create([
            'club_id' => $club->id,
            'type' => 'general_assembly',
            'title' => 'MV',
            'status' => 'held',
            'participant_scope' => 'club_members',
        ]);
        $meeting->createVersion($owner->id);
        $recipientA = $meeting->recipients()->create(['club_id' => $club->id, 'user_id' => $voterA->id, 'attendance_eligible' => true, 'voting_eligible' => true, 'delivery_status' => 'delivered']);
        $recipientB = $meeting->recipients()->create(['club_id' => $club->id, 'user_id' => $voterB->id, 'attendance_eligible' => true, 'voting_eligible' => true, 'delivery_status' => 'delivered']);
        $recipientC = $meeting->recipients()->create(['club_id' => $club->id, 'user_id' => $voterC->id, 'attendance_eligible' => true, 'voting_eligible' => true, 'delivery_status' => 'delivered']);
        Sanctum::actingAs($owner);

        $decisionId = $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions", [
            'type' => 'motion',
            'voting_mode' => 'named',
            'title' => 'Beitrag anpassen',
            'majority_rule' => 'simple',
            'quorum' => 67,
        ])->assertCreated()
            ->assertJsonPath('data.voting_mode', 'named')
            ->assertJsonPath('data.eligible_voters', 3)
            ->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/vote", [
            'recipient_id' => $recipientA->id,
            'choice' => 'yes',
        ])->assertOk()->assertJsonPath('data.person.name', 'A Mitglied');
        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/vote", [
            'recipient_id' => $recipientB->id,
            'choice' => 'no',
        ])->assertOk();
        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/vote", [
            'recipient_id' => $recipientC->id,
            'choice' => 'abstain',
        ])->assertOk();

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/close")
            ->assertOk()
            ->assertJsonPath('data.result.quorum_reached', true)
            ->assertJsonPath('data.result.abstentions', 1)
            ->assertJsonPath('data.result.valid_votes', 2)
            ->assertJsonPath('data.result.outcome', 'rejected')
            ->assertJsonPath('data.votes.0.person.name', 'A Mitglied');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/vote", [
            'recipient_id' => $recipientB->id,
            'choice' => 'yes',
        ])->assertUnprocessable();

        $this->assertNotNull(ClubGovernanceMeetingDecision::query()->findOrFail($decisionId)->correction_locked_at);
    }

    public function test_secret_contract_ballot_does_not_store_reconstructable_voter_identity(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create(['name' => 'Secret Mitglied']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($voter->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $meeting = ClubGovernanceMeeting::query()->create([
            'club_id' => $club->id,
            'type' => 'general_assembly',
            'title' => 'MV',
            'status' => 'held',
            'participant_scope' => 'club_members',
        ]);
        $meeting->createVersion($owner->id);
        $recipient = $meeting->recipients()->create([
            'club_id' => $club->id,
            'user_id' => $voter->id,
            'attendance_eligible' => true,
            'voting_eligible' => true,
            'delivery_status' => 'delivered',
        ]);
        $file = \App\Models\File::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'display_name' => 'Sponsoringvertrag.pdf',
            'path' => 'club/policy/sponsoringvertrag.pdf',
            'type' => 'application/pdf',
            'size' => 1200,
        ]);
        $document = \App\Models\ClubPolicyDocument::query()->create([
            'club_id' => $club->id,
            'file_id' => $file->id,
            'created_by' => $owner->id,
            'type' => 'contract_register',
            'title' => 'Sponsoringvertrag',
            'version_label' => 'v1',
            'valid_from' => now()->toDateString(),
            'workflow_status' => 'approved',
            'classification' => 'confidential',
        ]);
        Sanctum::actingAs($owner);

        $decisionId = $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions", [
            'type' => 'motion',
            'voting_mode' => 'secret',
            'title' => 'Vertrag freigeben',
            'majority_rule' => 'simple',
            'club_policy_document_id' => $document->id,
            'external_review' => ['status' => 'pending', 'provider' => 'Kanzlei', 'reference' => 'EXT-42'],
        ])->assertCreated()
            ->assertJsonPath('data.voting_mode', 'secret')
            ->assertJsonPath('data.policy_document.id', $document->id)
            ->assertJsonPath('data.external_review.provider', 'Kanzlei')
            ->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/vote", [
            'recipient_id' => $recipient->id,
            'choice' => 'yes',
        ])->assertOk()
            ->assertJsonPath('data.recipient_id', null)
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.person.name', null);

        $vote = \App\Models\ClubGovernanceMeetingDecisionVote::query()->where('club_governance_meeting_decision_id', $decisionId)->sole();
        $this->assertNull($vote->club_governance_meeting_recipient_id);
        $this->assertNull($vote->user_id);
        $this->assertNull($vote->person_name);
        $this->assertNull($vote->recipient_snapshot);
        $this->assertNotNull($vote->ballot_hash);

        $this->postJson("/api/v1/clubs/{$club->id}/governance/meetings/{$meeting->id}/decisions/{$decisionId}/close")
            ->assertOk()
            ->assertJsonPath('data.votes', []);
    }
}
