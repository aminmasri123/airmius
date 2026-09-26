<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPolicyDocument;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Event;
use App\Models\File;
use App\Models\Folder;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileFolderPermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_and_folder_scope_columns_cover_members_teams_and_events_but_not_project_contract_or_knowledge_domains(): void
    {
        foreach (['files', 'folders'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'user_id'), "{$table} must retain member/user scoping.");
            $this->assertTrue(Schema::hasColumn($table, 'club_id'), "{$table} must retain club scoping.");
            $this->assertTrue(Schema::hasColumn($table, 'team_id'), "{$table} must retain team scoping.");
            $this->assertTrue(Schema::hasColumn($table, 'event_id'), "{$table} must retain event scoping.");

            $this->assertFalse(Schema::hasColumn($table, 'organization_job_id'), "{$table} has no project/job scope yet.");
            $this->assertFalse(Schema::hasColumn($table, 'operating_contract_id'), "{$table} has no contract scope yet.");
            $this->assertFalse(Schema::hasColumn($table, 'learning_course_id'), "{$table} has no knowledge-article scope yet.");
        }
    }

    public function test_team_file_roles_keep_view_export_and_share_download_boundaries_separate(): void
    {
        Storage::fake(UploadStorage::disk());

        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $exporter = User::factory()->create();
        $sharer = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        Storage::disk(UploadStorage::disk())->put('audit/team-plan.pdf', 'team plan');
        $file = File::query()->create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'path' => 'audit/team-plan.pdf',
            'display_name' => 'team-plan.pdf',
            'type' => 'application/pdf',
            'size' => 9,
        ]);

        $this->attachMember($club, $viewer, $this->role($club, 'file_viewer', [ClubPermissions::FILES_VIEW]), 'team', $team->id);
        $this->attachMember($club, $exporter, $this->role($club, 'file_exporter', [ClubPermissions::FILES_EXPORT]), 'team', $team->id);
        $this->attachMember($club, $sharer, $this->role($club, 'file_sharer', [ClubPermissions::FILES_SHARE]), 'team', $team->id);

        $this->assertTrue(Gate::forUser($viewer)->allows('view', $file));
        $this->assertFalse(Gate::forUser($viewer)->allows('download', $file));
        $this->actingAs($viewer)->get(route('auth.files.download', $file))->assertForbidden();

        $this->assertTrue(Gate::forUser($exporter)->allows('download', $file));
        $this->assertFalse(Gate::forUser($exporter)->allows('share', $file));
        $this->actingAs($exporter)->get(route('auth.files.download', $file))->assertOk();

        $this->assertTrue(Gate::forUser($sharer)->allows('share', $file));
        $this->assertFalse(Gate::forUser($sharer)->allows('download', $file));
        $this->actingAs($sharer)->get(route('auth.files.download', $file))->assertForbidden();
    }

    public function test_event_file_download_follows_event_visibility_and_blocks_unrelated_members(): void
    {
        Storage::fake(UploadStorage::disk());

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($member->id, ['role' => 'player']);
        $event = Event::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Team Briefing',
            'type' => 'training',
            'visibility' => 'private',
            'start_time' => now()->addDay(),
        ]);
        Storage::disk(UploadStorage::disk())->put('audit/event-briefing.pdf', 'briefing');
        $file = File::query()->create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'event_id' => $event->id,
            'path' => 'audit/event-briefing.pdf',
            'display_name' => 'event-briefing.pdf',
            'type' => 'application/pdf',
            'size' => 8,
        ]);

        $this->assertTrue(Gate::forUser($member)->allows('download', $file));
        $this->actingAs($member)->get(route('auth.files.download', $file))->assertOk();

        $this->assertFalse(Gate::forUser($outsider)->allows('download', $file));
        $this->actingAs($outsider)->get(route('auth.files.download', $file))->assertForbidden();
    }

    public function test_policy_document_download_requires_document_download_permission(): void
    {
        Storage::fake(UploadStorage::disk());

        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $downloader = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => false]);
        Storage::disk(UploadStorage::disk())->put('audit/policy.pdf', 'policy');
        $file = File::query()->create([
            'user_id' => $owner->id,
            'club_id' => $club->id,
            'path' => 'audit/policy.pdf',
            'display_name' => 'policy.pdf',
            'type' => 'application/pdf',
            'size' => 6,
        ]);
        $document = ClubPolicyDocument::query()->create([
            'club_id' => $club->id,
            'file_id' => $file->id,
            'created_by' => $owner->id,
            'type' => 'regulation',
            'title' => 'Interne Richtlinie',
            'version_label' => '1.0',
            'valid_from' => now()->toDateString(),
            'is_public' => false,
        ]);

        $this->attachMember($club, $viewer, $this->role($club, 'policy_viewer', [ClubPermissions::POLICY_DOCUMENTS_VIEW]));
        $club->users()->updateExistingPivot($viewer->id, [
            'permission_overrides' => [ClubPermissions::POLICY_DOCUMENTS_DOWNLOAD => false],
        ]);
        $this->attachMember($club, $downloader, $this->role($club, 'policy_downloader', [
            ClubPermissions::POLICY_DOCUMENTS_VIEW,
            ClubPermissions::POLICY_DOCUMENTS_DOWNLOAD,
        ]));

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonPath('data.can_download', false);
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}/download")->assertNotFound();

        Sanctum::actingAs($downloader);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonPath('data.can_download', true);
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}/download")->assertOk();
    }

    private function role(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => $key,
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function attachMember(
        Club $club,
        User $user,
        ClubRoleDefinition $role,
        string $scopeType = 'club',
        ?int $scopeId = null,
    ): void {
        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active'],
        ]);

        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'club_role_definition_id' => $role->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
    }
}
