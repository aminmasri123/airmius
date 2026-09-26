<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubDepartment;
use App\Models\ClubRoleDefinition;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubAnnouncementPublisher;
use App\Support\ClubPermissions;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_management_summary_exposes_real_next_event_and_unread_announcements(): void
    {
        $owner = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Daily Club']);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Vorstandssitzung',
            'type' => 'meeting',
            'status' => 'scheduled',
            'visibility' => 'organization',
            'start_time' => now()->addDay(),
        ]);
        ClubAnnouncement::query()->create([
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'title' => 'Neue Satzung',
            'body' => 'Bitte lesen.',
            'audience_type' => 'all_members',
            'published_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.management.summary.next_event_id', $event->id)
            ->assertJsonPath('data.management.summary.next_event_title', 'Vorstandssitzung')
            ->assertJsonPath('data.management.summary.unread_announcements_count', 1);
    }

    public function test_draft_and_scheduled_announcement_are_hidden_until_published(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Schedule Club']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $draftId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Entwurf', 'body' => 'Noch nicht sichtbar', 'audience_type' => 'all_members', 'publication_mode' => 'draft',
        ])->assertCreated()->assertJsonPath('data.publication_status', 'draft')->json('data.id');
        $scheduledId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Geplant', 'body' => 'Später sichtbar', 'audience_type' => 'all_members',
            'publication_mode' => 'schedule', 'publish_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.publication_status', 'scheduled')->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId.'/read')->assertNotFound();
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$scheduledId.'/read')->assertNotFound();

        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId, [
            'title' => 'Jetzt da', 'body' => 'Sichtbar', 'audience_type' => 'all_members', 'publication_mode' => 'now',
        ])->assertOk()->assertJsonPath('data.publication_status', 'published');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertOk()->assertJsonCount(1, 'data');

        ClubAnnouncement::query()->findOrFail($scheduledId)->update(['published_at' => now()->subMinute()]);
        app(ClubAnnouncementPublisher::class)->publishDue();
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotNull(ClubAnnouncement::query()->findOrFail($scheduledId)->notified_at);
    }

    public function test_members_can_read_and_acknowledge_all_member_announcement(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Notice Club']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Sommerfest',
            'body' => 'Bitte meldet euch bis Freitag an.',
            'audience_type' => 'all_members',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Sommerfest')
            ->assertJsonPath('data.read_by_me', false)
            ->json('data.id');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $announcementId)
            ->assertJsonPath('data.0.read_by_me', false);

        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$announcementId.'/read')
            ->assertOk()
            ->assertJsonPath('data.announcement_id', $announcementId);

        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertJsonPath('data.0.read_by_me', true)
            ->assertJsonPath('data.0.read_count', 1);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertForbidden();
    }

    public function test_team_announcement_is_only_visible_to_team_members(): void
    {
        $owner = User::factory()->create();
        $teamMember = User::factory()->create();
        $clubMember = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Team Notice Club']);
        $club->users()->attach([
            $teamMember->id => ['role' => 'member', 'membership_status' => 'active'],
            $clubMember->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'U16']);
        $team->users()->attach($teamMember->id, ['role' => TeamRoles::PLAYER]);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Teamtreff',
            'body' => 'Treffpunkt ist 17:30 Uhr.',
            'audience_type' => 'team',
            'team_id' => $team->id,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($clubMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($teamMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()->assertJsonPath('data.0.id', $announcementId);
    }

    public function test_announcement_recipient_segment_snapshot_is_confidential_and_delivery_is_deduplicated(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'segment-member@example.test']);
        $paused = User::factory()->create(['email' => 'paused-member@example.test']);
        $former = User::factory()->create(['email' => 'former-member@example.test']);
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Segment Club']);
        $club->users()->attach([
            $member->id => ['role' => 'member', 'membership_status' => 'active'],
            $paused->id => ['role' => 'member', 'membership_status' => 'paused'],
            $former->id => ['role' => 'member', 'membership_status' => 'former'],
        ]);

        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Vertraulich gelöst',
            'body' => 'Bitte nur aktive Mitglieder benachrichtigen.',
            'audience_type' => 'all_members',
        ])->assertCreated()
            ->assertJsonPath('data.recipient_snapshot.count', 2)
            ->assertJsonMissing(['segment-member@example.test'])
            ->assertJsonMissing(['paused-member@example.test'])
            ->assertJsonMissing(['former-member@example.test']);

        $announcement = ClubAnnouncement::query()->findOrFail($response->json('data.id'));
        $this->assertSame(2, $announcement->recipient_snapshot_count);
        $this->assertNotEmpty($announcement->recipient_snapshot_hash);
        $this->assertNotNull($announcement->recipient_snapshot_at);

        app(ClubAnnouncementPublisher::class)->publishDue();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'type' => 'club.announcement',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $paused->id,
            'type' => 'club.announcement',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $former->id,
            'type' => 'club.announcement',
        ]);
        $this->assertSame(1, Notification::query()->where('type', 'club.announcement')->where('user_id', $member->id)->count());

        $payload = Notification::query()->where('user_id', $member->id)->firstOrFail()->data;
        $this->assertArrayNotHasKey('recipients', $payload);
        $this->assertStringNotContainsString('segment-member@example.test', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_team_segment_requires_active_club_membership_before_visibility_and_delivery(): void
    {
        $owner = User::factory()->create();
        $activeTeamMember = User::factory()->create();
        $pausedTeamMember = User::factory()->create();
        $clubOnlyMember = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Protected Team Segment']);
        $club->users()->attach([
            $activeTeamMember->id => ['role' => 'member', 'membership_status' => 'active'],
            $pausedTeamMember->id => ['role' => 'member', 'membership_status' => 'paused'],
            $clubOnlyMember->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach([
            $activeTeamMember->id => ['role' => TeamRoles::PLAYER],
            $pausedTeamMember->id => ['role' => TeamRoles::PLAYER],
        ]);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Nur aktives Team',
            'body' => 'Keine Lecks an pausierte Teamzuordnungen.',
            'audience_type' => 'team',
            'team_id' => $team->id,
        ])->assertCreated()
            ->assertJsonPath('data.recipient_snapshot.count', 1)
            ->json('data.id');

        Sanctum::actingAs($pausedTeamMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertForbidden();

        Sanctum::actingAs($clubOnlyMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($activeTeamMember);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $announcementId)
            ->assertJsonPath('data.0.recipient_snapshot.count', 1);

        $this->assertDatabaseHas('notifications', ['user_id' => $activeTeamMember->id, 'type' => 'club.announcement']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $pausedTeamMember->id, 'type' => 'club.announcement']);
    }

    public function test_announcement_edit_publish_and_delete_are_separated_and_team_scoped(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Scoped Notice Club']);
        $club->users()->attach($actor->id, [
            'role' => 'financial_controller', 'roles' => ['financial_controller'], 'membership_status' => 'active',
        ]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false]);
        $otherDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false]);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $otherDepartment->id]);
        $editor = $this->announcementRole($club, 'announcement_editor', [ClubPermissions::ANNOUNCEMENTS_EDIT]);
        $publisher = $this->announcementRole($club, 'announcement_publisher', [ClubPermissions::ANNOUNCEMENTS_PUBLISH]);
        $deleter = $this->announcementRole($club, 'announcement_deleter', [ClubPermissions::ANNOUNCEMENTS_DELETE]);

        Sanctum::actingAs($owner);
        $this->assignAnnouncementRole($club, $actor, $editor, $team);

        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.can_edit_announcements', true)
            ->assertJsonPath('data.can_publish_announcements', false)
            ->assertJsonPath('data.can_delete_announcements', false);
        $draft = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Team-Entwurf',
            'body' => 'Noch nicht freigegeben.',
            'audience_type' => 'team',
            'team_id' => $team->id,
            'publication_mode' => 'draft',
        ])->assertCreated()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_publish', false)
            ->assertJsonPath('data.can_delete', false);
        $draftId = $draft->json('data.id');
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Sofort',
            'body' => 'Nicht erlaubt.',
            'audience_type' => 'team',
            'team_id' => $team->id,
            'publication_mode' => 'now',
        ])->assertForbidden();
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Fremder Entwurf',
            'body' => 'Nicht erlaubt.',
            'audience_type' => 'team',
            'team_id' => $otherTeam->id,
            'publication_mode' => 'draft',
        ])->assertForbidden();
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId.'/publish')->assertForbidden();
        $this->putJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId, [
            'title' => 'Überarbeiteter Entwurf',
            'body' => 'Weiterhin nicht freigegeben.',
            'audience_type' => 'team',
            'team_id' => $team->id,
            'publication_mode' => 'draft',
        ])->assertOk()->assertJsonPath('data.title', 'Überarbeiteter Entwurf');
        $this->putJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId, [
            'title' => 'Verschoben',
            'body' => 'Nicht erlaubt.',
            'audience_type' => 'team',
            'team_id' => $otherTeam->id,
            'publication_mode' => 'draft',
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->assignAnnouncementRole($club, $actor, $publisher, $team);

        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.can_edit_announcements', false)
            ->assertJsonPath('data.can_publish_announcements', true)
            ->assertJsonPath('data.can_delete_announcements', false);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $draftId)
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_publish', true);
        $this->putJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId, [
            'title' => 'Manipuliert',
            'body' => 'Nicht erlaubt.',
            'audience_type' => 'team',
            'team_id' => $team->id,
            'publication_mode' => 'now',
        ])->assertForbidden();
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.publication_status', 'published');

        Sanctum::actingAs($owner);
        $cleanDraft = ClubAnnouncement::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Löschbarer Entwurf',
            'body' => 'Entwurf',
            'audience_type' => 'team',
        ]);
        $otherDraft = ClubAnnouncement::query()->create([
            'club_id' => $club->id,
            'team_id' => $otherTeam->id,
            'user_id' => $owner->id,
            'title' => 'Fremder Entwurf',
            'body' => 'Entwurf',
            'audience_type' => 'team',
        ]);
        $this->assignAnnouncementRole($club, $actor, $deleter, $team);

        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/clubs/'.$club->id)
            ->assertOk()
            ->assertJsonPath('data.can_edit_announcements', false)
            ->assertJsonPath('data.can_publish_announcements', false)
            ->assertJsonPath('data.can_delete_announcements', true);
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/announcements/'.$draftId)->assertStatus(409);
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/announcements/'.$otherDraft->id)->assertForbidden();
        $this->deleteJson('/api/v1/clubs/'.$club->id.'/announcements/'.$cleanDraft->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('club_announcements', ['id' => $cleanDraft->id]);
        $this->assertDatabaseHas('club_announcements', ['id' => $otherDraft->id]);
    }

    public function test_published_announcement_can_be_withdrawn_and_is_no_longer_visible_or_readable(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::query()->create(['owner_id' => $owner->id, 'name' => 'Withdraw Club']);
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $announcementId = $this->postJson('/api/v1/clubs/'.$club->id.'/announcements', [
            'title' => 'Ergebnis',
            'body' => 'Vorläufiges Ergebnis.',
            'audience_type' => 'all_members',
            'content_type' => 'result',
            'publication_mode' => 'now',
        ])->assertCreated()
            ->assertJsonPath('data.workflow_status', 'published')
            ->assertJsonPath('data.content_type', 'result')
            ->json('data.id');

        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$announcementId.'/withdraw')
            ->assertOk()
            ->assertJsonPath('data.publication_status', 'withdrawn')
            ->assertJsonPath('data.workflow_status', 'withdrawn');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/clubs/'.$club->id.'/announcements')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/clubs/'.$club->id.'/announcements/'.$announcementId.'/read')
            ->assertNotFound();

        $this->assertDatabaseHas('club_announcements', [
            'id' => $announcementId,
            'workflow_status' => 'withdrawn',
            'withdrawn_by' => $owner->id,
        ]);
    }

    private function announcementRole(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function assignAnnouncementRole(Club $club, User $actor, ClubRoleDefinition $role, Team $team): void
    {
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$actor->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $role->id,
                'scope_type' => 'team',
                'scope_id' => $team->id,
            ]],
        ])->assertOk();
    }
}
