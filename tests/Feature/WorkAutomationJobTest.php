<?php

namespace Tests\Feature;

use App\Jobs\RunWorkAutomationJob;
use App\Models\Club;
use App\Models\Notification;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WorkAutomationJob;
use App\Services\WorkAutomationJobService;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkAutomationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_enqueue_is_idempotent_and_dispatches_queue_once(): void
    {
        Queue::fake();
        [$club, $owner] = $this->clubWithOwner();
        Sanctum::actingAs($owner);

        $payload = [
            'kind' => 'reminder',
            'idempotency_key' => 'task:42:due-reminder:2026-09-26',
            'recipient_roles' => ['members.manage'],
            'payload' => ['template' => 'due_task'],
        ];

        $first = $this->postJson($this->path($club), $payload)
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->json('data.id');

        $this->postJson($this->path($club), $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertDatabaseCount('work_automation_jobs', 1);
        Queue::assertPushed(RunWorkAutomationJob::class, 1);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.work_automation.queued',
        ]);
    }

    public function test_failed_escalation_is_visible_and_manual_retry_is_permission_checked(): void
    {
        Queue::fake();
        [$club, $owner] = $this->clubWithOwner();

        $job = app(WorkAutomationJobService::class)->enqueue(
            $club,
            WorkAutomationJob::KIND_ESCALATION,
            'approval:77:overdue-escalation:2026-09-26',
            $owner,
            null,
            [ClubPermissions::FINANCE_MANAGE],
            ['force_fail' => true]
        );

        Queue::fake(false);
        app(WorkAutomationJobService::class)->run($job->id);
        Queue::fake();

        Sanctum::actingAs($owner);
        $this->getJson($this->path($club).'?status=failed')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.error_code', 'local_delivery_failed');

        $this->postJson($this->path($club)."/{$job->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.attempts', 1);

        Queue::assertPushed(RunWorkAutomationJob::class, 1);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.work_automation.retry_queued',
        ]);
    }

    public function test_work_automation_jobs_are_tenant_bound_and_require_management_permission(): void
    {
        Queue::fake();
        [$club, $owner, $member] = $this->clubWithOwner();
        [$foreignClub] = $this->clubWithOwner();
        $foreignJob = WorkAutomationJob::query()->create([
            'club_id' => $foreignClub->id,
            'kind' => WorkAutomationJob::KIND_REMINDER,
            'status' => WorkAutomationJob::STATUS_FAILED,
            'idempotency_key' => 'foreign-key',
            'queued_at' => now(),
            'failed_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $this->getJson($this->path($club))->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson($this->path($club)."/{$foreignJob->id}/retry")->assertNotFound();
    }

    public function test_policy_document_contract_review_job_notifies_authorized_document_managers(): void
    {
        Queue::fake();
        [$club, $owner, $member] = $this->clubWithOwner();

        $job = app(WorkAutomationJobService::class)->enqueue(
            $club,
            WorkAutomationJob::KIND_REMINDER,
            'policy-document:99:review:2026-09-30',
            $owner,
            null,
            [ClubPermissions::POLICY_DOCUMENTS_EDIT],
            [
                'template' => 'policy_document_contract_review',
                'title' => 'Sponsorvertrag Hauptpartner',
                'version_label' => '2026',
                'review_at' => '2026-09-30',
                'contract_ends_on' => '2026-12-31',
                'cancellation_notice_days' => 90,
                'url' => "/clubs/{$club->id}",
            ]
        );

        app(WorkAutomationJobService::class)->run($job->id);

        $this->assertDatabaseHas('work_automation_jobs', [
            'id' => $job->id,
            'status' => WorkAutomationJob::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'club.work_automation.policy_document_contract_review',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $member->id,
            'type' => 'club.work_automation.policy_document_contract_review',
        ]);

        $notification = Notification::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame("/clubs/{$club->id}", $notification->data['url']);
        $this->assertStringContainsString('Sponsorvertrag Hauptpartner', $notification->data['body']);
    }

    private function path(Club $club): string
    {
        return "/api/v1/clubs/{$club->id}/work-automation-jobs";
    }

    private function clubWithOwner(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->syncWithoutDetaching([
            $member->id => ['role' => 'member', 'roles' => ['member']],
        ]);

        SubscriptionPlan::firstOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'target_actor' => 'verein',
            'is_active' => true,
        ]);

        return [$club, $owner, $member];
    }
}
