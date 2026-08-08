<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\OrganizationJob;
use App\Models\OrganizationJobInterest;
use App\Models\User;
use App\Services\UserDataErasureService;
use App\Services\UserPrivacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RecruitingPipelineContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_interest_is_minimized_retained_and_visible_only_to_the_managing_club(): void
    {
        $owner = $this->manager();
        $otherManager = $this->manager();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'Nord Club']);
        $otherClub = Club::factory()->create(['owner_id' => $otherManager->id]);
        $job = $this->job($club, 'Athletiktrainer');
        $otherJob = $this->job($otherClub, 'Fremde Stelle');
        OrganizationJobInterest::query()->create([
            'organization_job_id' => $otherJob->id,
            'name' => 'Foreign Applicant',
            'email' => 'foreign@example.test',
            'status' => 'new',
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
        ]);

        $this->postJson("/api/v1/public/recruiting/jobs/{$job->id}/interest", [
            'name' => 'Amina Beispiel',
            'email' => 'amina@example.test',
        ], ['Idempotency-Key' => 'recruiting-pipeline-privacy-rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accepted_privacy');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->postJson("/api/v1/public/recruiting/jobs/{$job->id}/interest", [
                'name' => 'Amina Beispiel',
                'email' => 'amina@example.test',
                'phone' => '+49 123 456',
                'message' => 'Ich habe Interesse.',
                'accepted_privacy' => true,
            ], ['Idempotency-Key' => 'recruiting-pipeline-contract-0001'])
            ->assertCreated();

        $interest = OrganizationJobInterest::query()->where('organization_job_id', $job->id)->firstOrFail();
        $this->assertSame('new', $interest->status);
        $this->assertNotNull($interest->consent_at);
        $this->assertNotNull($interest->retention_expires_at);
        $this->assertNotSame('203.0.113.42', $interest->ip_address);
        $this->assertLessThanOrEqual(45, strlen((string) $interest->ip_address));
        $this->assertNull($interest->user_agent);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.applications.data.0.id', $interest->id)
            ->assertJsonPath('data.applications.data.0.email', 'amina@example.test')
            ->assertJsonMissing(['email' => 'foreign@example.test']);

        Sanctum::actingAs($otherManager);
        $this->getJson('/api/v1/recruiting-pipeline')
            ->assertOk()
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonMissing(['email' => 'amina@example.test']);
    }

    public function test_manager_can_progress_and_erase_an_application_with_minimized_audit(): void
    {
        $owner = $this->manager();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, 'Teamleitung');
        $interest = OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'name' => 'Applicant Secret',
            'email' => 'secret@example.test',
            'status' => 'new',
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/recruiting-pipeline/applications/{$interest->id}", [
            'status' => 'interview',
            'internal_note' => 'Gespräch am Dienstag.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'interview');

        $this->assertDatabaseHas('organization_job_interests', [
            'id' => $interest->id,
            'status' => 'interview',
            'status_changed_by' => $owner->id,
        ]);
        $activity = $this->app['db']->table('activities')
            ->where('type', 'club.recruiting.application_updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($activity);
        $this->assertStringNotContainsString('secret@example.test', (string) $activity->data);
        $this->assertStringNotContainsString('Applicant Secret', (string) $activity->data);

        $this->deleteJson("/api/v1/recruiting-pipeline/applications/{$interest->id}")
            ->assertOk();
        $this->assertDatabaseMissing('organization_job_interests', ['id' => $interest->id]);
    }

    public function test_pipeline_rejects_non_managers_and_prunes_expired_data(): void
    {
        $owner = $this->manager();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, 'Scout');
        $expired = OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'name' => 'Expired Person',
            'email' => 'expired@example.test',
            'status' => 'rejected',
            'consent_at' => now()->subYear(),
            'retention_expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/recruiting-pipeline')->assertForbidden();
        $this->putJson("/api/v1/recruiting-pipeline/applications/{$expired->id}", [
            'status' => 'hired',
        ])->assertForbidden();

        $this->artisan('airmius:prune-recruiting-interests')
            ->expectsOutput('Pruned 1 expired recruiting interests.')
            ->assertSuccessful();
        $this->assertDatabaseMissing('organization_job_interests', ['id' => $expired->id]);
    }

    public function test_web_pipeline_uses_server_filters_and_the_localized_inertia_page(): void
    {
        $owner = $this->manager(['language' => 'fr']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, 'Coach');
        OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'name' => 'Claire Martin',
            'email' => 'claire@example.test',
            'status' => 'contacted',
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
        ]);

        $this->actingAs($owner)
            ->get('/recruiting-pipeline?status=contacted&q=Claire')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Dashboard/Recruiting/Index')
                ->where('filters.status', 'contacted')
                ->where('filters.q', 'Claire')
                ->where('stats.total', 1)
                ->has('applications.data', 1));
    }

    public function test_recruiting_data_is_included_in_export_and_self_service_erasure(): void
    {
        $owner = $this->manager();
        $applicant = User::factory()->create(['email' => 'applicant@example.test']);
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $job = $this->job($club, 'Physiotherapie');
        $interest = OrganizationJobInterest::query()->create([
            'organization_job_id' => $job->id,
            'user_id' => $applicant->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'status' => 'reviewing',
            'internal_note' => 'Unterlagen werden geprüft.',
            'consent_at' => now(),
            'retention_expires_at' => now()->addMonths(6),
        ]);

        $export = app(UserPrivacyExportService::class)->export($applicant);
        $this->assertSame($interest->id, data_get($export, 'recruiting.applications.0.id'));
        $this->assertSame('Unterlagen werden geprüft.', data_get($export, 'recruiting.applications.0.internal_note'));

        app(UserDataErasureService::class)->erase($applicant, ['social_and_integrations']);
        $this->assertDatabaseMissing('organization_job_interests', ['id' => $interest->id]);
    }

    private function manager(array $attributes = []): User
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'club.jobs.manage',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create($attributes);
        $user->givePermissionTo($permission);

        return $user;
    }

    private function job(Club $club, string $title): OrganizationJob
    {
        return OrganizationJob::query()->create([
            'club_id' => $club->id,
            'created_by' => $club->owner_id,
            'title' => $title,
            'type' => 'professional',
            'description' => 'Eine klar beschriebene Stelle.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
