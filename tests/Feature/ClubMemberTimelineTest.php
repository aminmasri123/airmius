<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubMemberTimelineEntry;
use App\Models\User;
use App\Services\UserPrivacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMemberTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_store_honors_anniversaries_and_notes_for_club_people(): void
    {
        [$owner, $club, $member, $external] = $this->people();
        Sanctum::actingAs($owner);

        $honor = $this->postJson("/api/v1/clubs/{$club->id}/member-timeline", [
            'subject_type' => 'member',
            'subject_id' => $member->id,
            'type' => 'honor',
            'title' => 'Goldene Ehrennadel',
            'description' => 'Für besonderes Engagement',
            'occurred_on' => '2026-09-20',
        ])->assertCreated()->assertJsonPath('data.type', 'honor');
        $honorId = $honor->json('data.id');

        $this->postJson("/api/v1/clubs/{$club->id}/member-timeline", [
            'subject_type' => 'external_member',
            'subject_id' => $external->id,
            'type' => 'anniversary',
            'title' => '25 Jahre Vereinszugehörigkeit',
            'occurred_on' => '2026-08-01',
        ])->assertCreated()->assertJsonPath('data.subject_type', 'external_member');

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.management.member_timeline_entries');

        $this->deleteJson("/api/v1/clubs/{$club->id}/member-timeline/{$honorId}")
            ->assertNoContent();
        $this->assertDatabaseMissing('club_member_timeline_entries', ['id' => $honorId]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/member-timeline", [
            'subject_type' => 'member',
            'subject_id' => $member->id,
            'type' => 'note',
            'title' => 'Nicht erlaubt',
            'occurred_on' => '2026-09-20',
        ])->assertForbidden();
    }

    public function test_membership_changes_create_immutable_timeline_and_privacy_export_contains_it(): void
    {
        [$owner, $club, $member, $external] = $this->people();
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'paused',
            'joined_on' => '2020-01-02',
            'membership_ends_on' => '2026-12-31',
            'contribution_interval' => 'none',
            'sepa_mandate_active' => false,
        ])->assertOk();

        $registeredEntries = ClubMemberTimelineEntry::query()
            ->where('subject_type', 'member')
            ->where('subject_id', $member->id)
            ->get();
        $this->assertCount(3, $registeredEntries);
        $this->assertEqualsCanonicalizing(
            ['status_change', 'membership_date', 'membership_date'],
            $registeredEntries->pluck('type')->all(),
        );

        $statusEntry = $registeredEntries->firstWhere('type', 'status_change');
        $this->assertSame('active', $statusEntry->from_value);
        $this->assertSame('paused', $statusEntry->to_value);
        $this->deleteJson("/api/v1/clubs/{$club->id}/member-timeline/{$statusEntry->id}")
            ->assertUnprocessable();

        $this->putJson("/api/v1/clubs/{$club->id}/external-members/{$external->id}", [
            'email' => $external->email,
            'role' => 'member',
            'membership_status' => 'former',
            'contribution_interval' => 'none',
            'sepa_mandate_active' => false,
            'joined_on' => '2010-05-01',
            'membership_ends_on' => '2026-09-01',
        ])->assertOk();
        $this->assertSame(3, ClubMemberTimelineEntry::query()
            ->where('subject_type', 'external_member')
            ->where('subject_id', $external->id)
            ->count());

        $export = app(UserPrivacyExportService::class)->export($member->fresh());
        $this->assertCount(3, $export['club_member_timeline']);

        $member->delete();
        $this->assertDatabaseMissing('club_member_timeline_entries', [
            'subject_type' => 'member',
            'subject_id' => $member->id,
        ]);
    }

    private function people(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $external = ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'name' => 'Externe Person',
            'email' => 'timeline-external@example.test',
            'role' => 'member',
            'membership_status' => 'active',
        ]);

        return [$owner, $club, $member, $external];
    }
}
