<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubVolunteerProfile;
use App\Models\OrganizationJob;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationJobPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_edit_publish_and_delete_rights_are_independent(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $publisher = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $publisher, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $this->assign($club, $owner, $editor, 'job_editor', [ClubPermissions::JOBS_EDIT]);
        $this->assign($club, $owner, $publisher, 'job_publisher', [ClubPermissions::JOBS_PUBLISH]);
        $this->assign($club, $owner, $deleter, 'job_deleter', [ClubPermissions::JOBS_DELETE]);

        $draft = $this->payload('Jugendkoordination', false);
        $this->actingAs($editor)
            ->post(route('auth.clubs.jobs.store', $club), $draft)
            ->assertRedirect();
        $job = OrganizationJob::query()->where('title', 'Jugendkoordination')->firstOrFail();

        $this->actingAs($editor)
            ->put(route('auth.organization-jobs.update', $job), $this->payload('Jugendkoordination', true))
            ->assertRedirect();
        $this->assertFalse($job->fresh()->is_published);

        $this->actingAs($publisher)
            ->put(route('auth.organization-jobs.update', $job), $this->payload('Jugendkoordination', true))
            ->assertRedirect();
        $this->assertFalse($job->fresh()->is_published);

        $this->actingAs($deleter)
            ->delete(route('auth.organization-jobs.destroy', $job))
            ->assertRedirect();
        $this->assertDatabaseMissing('organization_jobs', ['id' => $job->id]);
    }

    public function test_combined_editor_and_publisher_can_publish_without_a_legacy_global_role(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($editor->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $this->assign($club, $owner, $editor, 'job_editor_publisher', [
            ClubPermissions::JOBS_EDIT,
            ClubPermissions::JOBS_PUBLISH,
        ]);

        $this->actingAs($editor)
            ->post(route('auth.clubs.jobs.store', $club), $this->payload('Platzpflege', true))
            ->assertRedirect();

        $this->assertDatabaseHas('organization_jobs', [
            'club_id' => $club->id,
            'title' => 'Platzpflege',
            'is_published' => true,
        ]);
    }

    public function test_editor_can_publish_service_task_with_schedule_qualifications_shift_need_and_commitment(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($editor->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $this->assign($club, $owner, $editor, 'job_editor_publisher', [
            ClubPermissions::JOBS_EDIT,
            ClubPermissions::JOBS_PUBLISH,
        ]);

        $this->actingAs($editor)
            ->post(route('auth.clubs.jobs.store', $club), array_merge($this->payload('Pflichtdienst Heimspiel', true), [
                'location' => 'Sporthalle Nord',
                'starts_at' => '2026-10-03 09:00:00',
                'ends_at' => '2026-10-03 13:00:00',
                'required_qualifications' => ['Erste Hilfe', 'Kiosk'],
                'shift_slots_required' => 4,
                'commitment_type' => OrganizationJob::COMMITMENT_MANDATORY,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('organization_jobs', [
            'club_id' => $club->id,
            'title' => 'Pflichtdienst Heimspiel',
            'location' => 'Sporthalle Nord',
            'shift_slots_required' => 4,
            'commitment_type' => OrganizationJob::COMMITMENT_MANDATORY,
            'is_published' => true,
        ]);
        $job = OrganizationJob::query()->where('title', 'Pflichtdienst Heimspiel')->firstOrFail();
        $this->assertSame(['Erste Hilfe', 'Kiosk'], $job->required_qualifications);
        $this->assertSame('2026-10-03 09:00:00', $job->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_published_service_task_matches_only_same_club_visible_volunteer_profiles(): void
    {
        $owner = User::factory()->create();
        $matchingMember = User::factory()->create();
        $privateMember = User::factory()->create();
        $otherClubMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherOwner = User::factory()->create();
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        foreach ([$matchingMember, $privateMember] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $otherClub->users()->attach($otherClubMember->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);

        ClubVolunteerProfile::query()->create([
            'club_id' => $club->id,
            'user_id' => $matchingMember->id,
            'skills' => ['Erste Hilfe', 'Kiosk'],
            'interests' => ['Heimspiel'],
            'visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS,
        ]);
        ClubVolunteerProfile::query()->create([
            'club_id' => $club->id,
            'user_id' => $privateMember->id,
            'skills' => ['Erste Hilfe', 'Kiosk'],
            'visibility' => ClubVolunteerProfile::VISIBILITY_PRIVATE,
        ]);
        ClubVolunteerProfile::query()->create([
            'club_id' => $otherClub->id,
            'user_id' => $otherClubMember->id,
            'skills' => ['Erste Hilfe', 'Kiosk'],
            'visibility' => ClubVolunteerProfile::VISIBILITY_CLUB_MANAGERS,
        ]);
        $job = OrganizationJob::query()->create(array_merge($this->payload('Schicht Kiosk', true), [
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'required_qualifications' => ['Erste Hilfe', 'Kiosk'],
            'published_at' => now(),
        ]));

        $this->assertSame([$matchingMember->id], $job->matchingVolunteerProfiles()->pluck('user_id')->all());
    }

    public function test_job_actions_remain_scoped_to_the_jobs_own_club(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $otherClub->users()->attach($editor->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $this->assign($otherClub, $otherOwner, $editor, 'other_job_editor', [
            ClubPermissions::JOBS_EDIT,
            ClubPermissions::JOBS_PUBLISH,
        ]);
        $job = OrganizationJob::query()->create(array_merge($this->payload('Fremder Dienst', false), [
            'club_id' => $club->id,
            'created_by' => $owner->id,
        ]));

        $this->actingAs($editor)
            ->put(route('auth.organization-jobs.update', $job), $this->payload('Fremder Dienst manipuliert', true))
            ->assertRedirect();

        $this->assertSame('Fremder Dienst', $job->fresh()->title);
        $this->assertFalse($job->fresh()->is_published);
    }

    private function payload(string $title, bool $published): array
    {
        return [
            'title' => $title,
            'type' => 'volunteer',
            'description' => 'Eine klar beschriebene Aufgabe.',
            'is_published' => $published,
            'shift_slots_required' => 1,
            'commitment_type' => OrganizationJob::COMMITMENT_VOLUNTARY,
        ];
    }

    private function assign(Club $club, User $owner, User $user, string $key, array $permissions): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
