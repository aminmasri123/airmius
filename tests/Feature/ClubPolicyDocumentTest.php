<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubPolicyDocument;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\File;
use App\Models\User;
use App\Models\WorkAutomationJob;
use App\Support\ClubPermissions;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClubPolicyDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_versioned_documents_from_existing_club_files_with_private_audit(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'satzung-2026.pdf');
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", [
            'type' => 'statutes',
            'title' => 'Vereinssatzung',
            'version_label' => '2026.1',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_public' => true,
            'notes' => 'Beschlossen durch die Mitgliederversammlung.',
            'file_id' => $file->id,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'statutes')
            ->assertJsonPath('data.version_label', '2026.1')
            ->assertJsonPath('data.status', 'current')
            ->assertJsonPath('data.file.id', $file->id);

        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_download', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.types.2', 'contribution_model');

        $activity = Activity::query()->where('type', 'club.policy_document.created')->firstOrFail();
        $this->assertSame(['entity_type' => 'policy_document'], $activity->data);
        $this->assertStringNotContainsString('Vereinssatzung', json_encode($activity->data));
    }

    public function test_versions_of_the_same_document_cannot_overlap_but_adjacent_versions_can_follow(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'ordnung.pdf');
        Sanctum::actingAs($owner);
        $base = [
            'type' => 'regulation',
            'title' => 'Geschäftsordnung',
            'file_id' => $file->id,
            'is_public' => false,
            'notes' => null,
        ];

        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", $base + [
            'version_label' => '1',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ])->assertCreated();
        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", $base + [
            'version_label' => '2-overlap',
            'valid_from' => '2026-12-31',
            'valid_until' => '2027-12-31',
        ])->assertUnprocessable()->assertJsonValidationErrors('valid_from');
        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", $base + [
            'version_label' => '2',
            'valid_from' => '2027-01-01',
            'valid_until' => null,
        ])->assertCreated();

        $this->assertDatabaseCount('club_policy_documents', 2);
    }

    public function test_visibility_download_permissions_and_club_boundaries_are_enforced(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $internalFile = $this->clubFile($club, $owner, 'intern.pdf');
        $publicFile = $this->clubFile($club, $owner, 'public.pdf');
        $internal = $this->document($club, $internalFile, false, 'Interne Ordnung');
        $public = $this->document($club, $publicFile, true, 'Öffentliche Satzung', 'statutes');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonPath('data.documents.0.id', $public->id)
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_download', false);
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$internal->id}/download")
            ->assertNotFound();
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$public->id}/download")
            ->assertOk()
            ->assertDownload('public.pdf');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()->assertJsonCount(2, 'data.documents');
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$internal->id}/download")
            ->assertOk()->assertDownload('intern.pdf');
        $this->deleteJson("/api/v1/clubs/{$club->id}/policy-documents/{$internal->id}")
            ->assertForbidden();

        $club->users()->updateExistingPivot($member->id, ['membership_status' => 'former']);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()->assertJsonCount(1, 'data.documents');
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$internal->id}/download")
            ->assertNotFound();

        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $foreignFile = $this->clubFile($foreignClub, $foreignClub->owner, 'fremd.pdf');
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", [
            'type' => 'regulation', 'title' => 'Fremd', 'version_label' => '1',
            'valid_from' => '2026-01-01', 'valid_until' => null,
            'is_public' => false, 'file_id' => $foreignFile->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('file_id');
        $foreignDocument = $this->document($foreignClub, $foreignFile, false, 'Fremd');
        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$foreignDocument->id}", [
            'type' => 'regulation', 'title' => 'Fremd', 'version_label' => '2',
            'valid_from' => '2027-01-01', 'valid_until' => null,
            'is_public' => false, 'file_id' => $internalFile->id,
        ])->assertNotFound();
    }

    public function test_update_and_delete_preserve_the_reusable_club_file(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'beitragsmodell.pdf');
        $document = $this->document($club, $file, false, 'Beitragsmodell', 'contribution_model');
        Sanctum::actingAs($owner);
        $futureStart = now()->addYear()->startOfYear()->format('Y-m-d');

        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}", [
            'type' => 'contribution_model', 'title' => 'Beitragsmodell', 'version_label' => '2027',
            'valid_from' => $futureStart, 'valid_until' => null,
            'is_public' => true, 'notes' => null, 'file_id' => $file->id,
        ])->assertOk()->assertJsonPath('data.status', 'upcoming');

        $this->deleteJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}")
            ->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseHas('files', ['id' => $file->id]);
        Storage::disk(UploadStorage::disk())->assertExists($file->path);
        $this->assertDatabaseHas('activities', ['type' => 'club.policy_document.deleted']);
    }

    public function test_referenced_file_cannot_be_deleted_until_all_document_versions_are_removed(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'geschuetzt.pdf');
        $document = $this->document($club, $file, false, 'Geschützte Ordnung');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('file.delete', 'web');
        $owner->givePermissionTo('file.delete');

        $this->assertFalse(Gate::forUser($owner)->allows('delete', $file));

        $document->delete();
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $file));
    }

    public function test_contribution_rules_link_only_to_covering_models_and_keep_historical_links(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'beitragsmodell-2026.pdf');
        $document = $this->document($club, $file, false, 'Beitragsmodell 2026', 'contribution_model');
        $document->update(['valid_until' => '2026-12-31']);
        Sanctum::actingAs($owner);

        $payload = [
            'club_policy_document_id' => $document->id,
            'name' => 'Jahresbeitrag 2026',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'billing_interval' => 'yearly',
            'amount' => 120,
            'factor_key' => 'standard',
        ];
        $this->postJson("/api/v1/clubs/{$club->id}/membership/contribution-rules", $payload)
            ->assertCreated()
            ->assertJsonPath('data.contribution_rules.0.club_policy_document_id', $document->id)
            ->assertJsonPath('data.contribution_rules.0.policy_document.version_label', '1')
            ->assertJsonPath('data.contribution_policy_documents.0.id', $document->id);

        $this->postJson("/api/v1/clubs/{$club->id}/membership/contribution-rules", [
            ...$payload,
            'name' => 'Unbegrenzte Regel',
            'valid_until' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_policy_document_id');

        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $otherFile = $this->clubFile($otherClub, $otherClub->owner, 'fremdes-modell.pdf');
        $otherDocument = $this->document($otherClub, $otherFile, false, 'Fremdes Modell', 'contribution_model');
        $this->postJson("/api/v1/clubs/{$club->id}/membership/contribution-rules", [
            ...$payload,
            'club_policy_document_id' => $otherDocument->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_policy_document_id');

        $this->deleteJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('policy_document');
        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}", [
            'type' => 'contribution_model',
            'title' => $document->title,
            'version_label' => $document->version_label,
            'valid_from' => '2026-02-01',
            'valid_until' => '2026-12-31',
            'is_public' => false,
            'file_id' => $file->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('valid_from');
        $this->assertDatabaseHas('club_contribution_rules', [
            'club_id' => $club->id,
            'club_policy_document_id' => $document->id,
            'name' => 'Jahresbeitrag 2026',
        ]);
    }

    public function test_policy_document_edit_download_and_delete_permissions_are_separated(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $downloader = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$editor, $downloader, $deleter] as $member) {
            $club->users()->attach($member->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::POLICY_DOCUMENTS_DOWNLOAD => false],
            ]);
        }
        $this->assign($club, $editor, $this->role($club, 'document_editor', [ClubPermissions::POLICY_DOCUMENTS_EDIT]), $owner);
        $this->assign($club, $downloader, $this->role($club, 'document_downloader', [ClubPermissions::POLICY_DOCUMENTS_DOWNLOAD]), $owner);
        $this->assign($club, $deleter, $this->role($club, 'document_deleter', [ClubPermissions::POLICY_DOCUMENTS_DELETE]), $owner);
        $club->users()->updateExistingPivot($downloader->id, ['permission_overrides' => null]);
        $file = $this->clubFile($club, $owner, 'rechte.pdf');
        $document = $this->document($club, $file, false, 'Rechteordnung');
        $payload = [
            'type' => 'regulation', 'title' => 'Rechteordnung neu', 'version_label' => '2',
            'valid_from' => '2026-01-01', 'valid_until' => null,
            'is_public' => false, 'notes' => null, 'file_id' => $file->id,
        ];

        Sanctum::actingAs($editor);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_download', false)->assertJsonPath('data.can_delete', false);
        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}", $payload)->assertOk();
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}/download")->assertNotFound();
        $this->deleteJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}")->assertForbidden();

        Sanctum::actingAs($downloader);
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}/download")
            ->assertOk()->assertDownload('rechte.pdf');
        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}", $payload)->assertForbidden();

        Sanctum::actingAs($deleter);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_download', false)->assertJsonPath('data.can_delete', true);
        $this->deleteJson("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}")->assertOk();
    }

    public function test_contract_runtime_cancellation_notice_and_review_job_are_managed_with_document(): void
    {
        Queue::fake();
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'sponsorvertrag.pdf');
        Sanctum::actingAs($owner);

        $documentId = $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", [
            'type' => 'regulation',
            'title' => 'Sponsorvertrag Hauptpartner',
            'version_label' => '2026',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'contract_starts_on' => '2026-01-01',
            'contract_ends_on' => '2026-12-31',
            'cancellation_notice_days' => 90,
            'review_at' => '2026-09-30',
            'is_public' => false,
            'notes' => null,
            'file_id' => $file->id,
        ])->assertCreated()
            ->assertJsonPath('data.contract_starts_on', '2026-01-01')
            ->assertJsonPath('data.contract_ends_on', '2026-12-31')
            ->assertJsonPath('data.cancellation_notice_days', 90)
            ->assertJsonPath('data.review_at', '2026-09-30')
            ->assertJsonPath('data.review_job_id', 1)
            ->json('data.id');

        $this->assertDatabaseHas('work_automation_jobs', [
            'club_id' => $club->id,
            'kind' => WorkAutomationJob::KIND_REMINDER,
            'subject_type' => (new ClubPolicyDocument())->getMorphClass(),
            'subject_id' => $documentId,
            'idempotency_key' => "policy-document:{$documentId}:review:2026-09-30",
        ]);

        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonPath('data.documents.0.review_at', '2026-09-30')
            ->assertJsonPath('data.documents.0.review_job_id', 1);
    }

    public function test_contract_employment_and_compensation_documents_are_versioned_private_and_audited(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'is_listed' => true]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::POLICY_DOCUMENTS_VIEW => false],
        ]);
        $file = $this->clubFile($club, $owner, 'beschaeftigung-verguetung-2026.pdf');
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", [
            'type' => 'employment_model',
            'title' => 'Beschaeftigungsmodell Trainer',
            'version_label' => '2026.1',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_public' => true,
            'notes' => 'Honorar- und Vertragsdetails duerfen nicht oeffentlich sein.',
            'file_id' => $file->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_public');

        $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", [
            'type' => 'employment_model',
            'title' => 'Beschaeftigungsmodell Trainer',
            'version_label' => '2026.1',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'contract_starts_on' => '2026-01-01',
            'contract_ends_on' => '2026-12-31',
            'cancellation_notice_days' => 60,
            'is_public' => false,
            'notes' => 'Honorar- und Vertragsdetails duerfen nicht oeffentlich sein.',
            'file_id' => $file->id,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'employment_model')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.contract_ends_on', '2026-12-31');

        $document = ClubPolicyDocument::query()->where('type', 'employment_model')->firstOrFail();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonCount(0, 'data.documents');
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}/download")
            ->assertNotFound();

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/policy-documents")
            ->assertOk()
            ->assertJsonCount(0, 'data.documents');
        $this->get("/api/v1/clubs/{$club->id}/policy-documents/{$document->id}/download")
            ->assertNotFound();

        $activity = Activity::query()->where('type', 'club.policy_document.created')->latest('id')->firstOrFail();
        $this->assertSame(['entity_type' => 'policy_document'], $activity->data);
        $this->assertStringNotContainsString('Honorar', json_encode($activity->data));
        $this->assertStringNotContainsString('Beschaeftigungsmodell', json_encode($activity->data));
    }

    public function test_policy_document_lifecycle_publishes_with_classification_retention_checksum_and_immutability(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $file = $this->clubFile($club, $owner, 'satzung-final.pdf');
        Sanctum::actingAs($owner);

        $documentId = $this->postJson("/api/v1/clubs/{$club->id}/policy-documents", [
            'type' => 'statutes',
            'title' => 'Satzung',
            'version_label' => '2026-final',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'workflow_status' => 'published',
            'classification' => 'public',
            'retention_until' => '2036-12-31',
            'is_public' => true,
            'notes' => null,
            'file_id' => $file->id,
        ])->assertCreated()
            ->assertJsonPath('data.workflow_status', 'published')
            ->assertJsonPath('data.classification', 'public')
            ->assertJsonPath('data.retention_until', '2036-12-31')
            ->assertJsonPath('data.published_by', $owner->id)
            ->json('data.id');

        $document = ClubPolicyDocument::query()->findOrFail($documentId);
        $this->assertNotNull($document->published_at);
        $this->assertNotEmpty($document->publication_checksum);

        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$documentId}", [
            'type' => 'statutes',
            'title' => 'Satzung geändert',
            'version_label' => '2026-final',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'workflow_status' => 'published',
            'classification' => 'public',
            'retention_until' => '2036-12-31',
            'is_public' => true,
            'notes' => null,
            'file_id' => $file->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->putJson("/api/v1/clubs/{$club->id}/policy-documents/{$documentId}", [
            'type' => 'statutes',
            'title' => 'Satzung',
            'version_label' => '2026-final',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'workflow_status' => 'archived',
            'classification' => 'public',
            'retention_until' => '2036-12-31',
            'is_public' => true,
            'notes' => 'Archiviert nach neuer Version.',
            'file_id' => $file->id,
        ])->assertOk()
            ->assertJsonPath('data.workflow_status', 'archived')
            ->assertJsonPath('data.is_public', false);

        $this->assertDatabaseHas('activities', ['type' => 'club.policy_document.lifecycle.updated']);
    }

    private function clubFile(Club $club, User $user, string $name): File
    {
        $path = "clubs/{$club->id}/{$name}";
        Storage::disk(UploadStorage::disk())->put($path, 'PDF');

        return File::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'display_name' => $name,
            'path' => $path,
            'type' => 'application/pdf',
            'size' => 3,
        ]);
    }

    private function document(
        Club $club,
        File $file,
        bool $public,
        string $title,
        string $type = 'regulation'
    ): ClubPolicyDocument {
        return ClubPolicyDocument::query()->create([
            'club_id' => $club->id,
            'file_id' => $file->id,
            'created_by' => $club->owner_id,
            'type' => $type,
            'title' => $title,
            'version_label' => '1',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_public' => $public,
        ]);
    }

    private function role(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id, 'key' => $key, 'name' => str_replace('_', ' ', $key),
            'permissions' => $permissions, 'is_active' => true,
        ]);
    }

    private function assign(Club $club, User $user, ClubRoleDefinition $role, User $owner): void
    {
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id, 'club_role_definition_id' => $role->id,
            'user_id' => $user->id, 'scope_type' => 'club', 'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
