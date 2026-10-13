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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
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
            ['title' => 'Overdue approval']
        );

        $failingService = new class extends WorkAutomationJobService
        {
            public function recipients(Club $club, array $roles = []): Collection
            {
                throw new \RuntimeException('Injected delivery failure');
            }
        };
        $failingService->run($job->id);
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
        $this->travelTo(now()->setDate(2026, 10, 1));
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

    public function test_running_and_completed_jobs_are_not_executed_again(): void
    {
        Queue::fake();
        [$club, $owner] = $this->clubWithOwner();
        $service = app(WorkAutomationJobService::class);
        $job = $service->enqueue($club, 'reminder', 'reentrant', $owner);
        $nested = false;
        Event::listen('eloquent.creating: '.Notification::class, function () use ($service, $job, &$nested) {
            $nested = true;
            $this->assertSame('running', $service->run($job->id)->status);
        });
        try {
            $service->run($job->id);
            $service->run($job->id);
        } finally {
            Event::forget('eloquent.creating: '.Notification::class);
        }
        $this->assertTrue($nested);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $job->fresh()->attempts);
    }

    public function test_partial_delivery_retry_deduplicates_recipients_and_stale_requests(): void
    {
        Queue::fake();
        [$club, $owner, $member] = $this->clubWithOwner();
        $club->users()->updateExistingPivot($member->id, ['permission_overrides' => [ClubPermissions::MEMBERS_MANAGE => true]]);
        $service = app(WorkAutomationJobService::class);
        $job = $service->enqueue($club, 'reminder', 'partial', $owner, null, [ClubPermissions::MEMBERS_MANAGE]);
        $writes = 0;
        Event::listen('eloquent.creating: '.Notification::class, function () use (&$writes) {
            if (++$writes === 2) {
                throw new \RuntimeException('Injected second recipient failure');
            }
        });
        try {
            $service->run($job->id);
        } finally {
            Event::forget('eloquent.creating: '.Notification::class);
        }
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertDatabaseCount('notifications', 1);
        $oldToken = $job->execution_token;
        $staleModel = $job->fresh();
        $retried = $service->retry($job->fresh(), $owner, 1);
        $service->retry($staleModel, $owner, 1);
        Queue::assertPushed(RunWorkAutomationJob::class, 2);
        (new RunWorkAutomationJob($job->id, $oldToken))->failed(new \RuntimeException('Late callback'));
        $this->assertSame('queued', $job->fresh()->status);
        $service->run($job->id, $oldToken);
        $this->assertSame(1, $job->fresh()->attempts);
        $completed = $service->run($job->id, $retried->execution_token);
        $this->assertSame('completed', $completed->status);
        $this->assertSame(2, $completed->result['stored_notifications']);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('completed', $service->retry($job->fresh(), $owner, 1)->status);
    }

    public function test_request_key_conflicts_and_untrusted_payloads_are_rejected(): void
    {
        Queue::fake();
        [$club, $owner] = $this->clubWithOwner();
        Sanctum::actingAs($owner);
        $payload = ['kind' => 'reminder', 'idempotency_key' => 'conflict', 'payload' => ['title' => 'One']];
        $this->postJson($this->path($club), $payload)->assertAccepted();
        $payload['payload']['title'] = 'Different';
        $this->postJson($this->path($club), $payload)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        foreach ([['force_fail' => true], ['url' => 'javascript:alert(1)'], ['template' => 'policy_document_contract_review'], ['title' => ['bad']]] as $invalid) {
            $this->postJson($this->path($club), ['kind' => 'reminder', 'idempotency_key' => 'invalid', 'payload' => $invalid])->assertUnprocessable();
        }
        $this->postJson($this->path($club), ['kind' => 'reminder', 'idempotency_key' => 'invalid', 'recipient_roles' => ['not.a.permission']])->assertUnprocessable();
        $this->getJson($this->path($club).'?status=made-up')->assertUnprocessable();
        Queue::assertPushed(RunWorkAutomationJob::class, 1);
    }

    public function test_recipient_preview_includes_owner_without_pivot_and_zero_recipients_fail(): void
    {
        Queue::fake();
        [$club, $owner] = $this->clubWithOwner();
        $club->users()->detach($owner);
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/work-automation-recipients")->assertOk()->assertJsonPath('data.count', 1);
        $service = app(WorkAutomationJobService::class);
        $job = $service->enqueue($club, 'reminder', 'owner', $owner);
        $this->assertSame(1, $service->run($job->id)->result['stored_notifications']);
        $empty = $service->enqueue($club, 'reminder', 'empty');
        $emptyService = new class extends WorkAutomationJobService
        {
            public function recipients(Club $club, array $roles = []): Collection
            {
                return collect();
            }
        };
        $this->assertSame('no_recipients', $emptyService->run($empty->id)->error_code);
        $this->assertSame('failed', $empty->fresh()->status);
    }

    public function test_schedule_dispatch_is_due_only_and_command_failures_return_nonzero(): void
    {
        Queue::fake();
        $this->travelTo(now()->startOfMinute());
        [$club, $owner] = $this->clubWithOwner();
        Sanctum::actingAs($owner);
        $payload = ['kind' => 'reminder', 'idempotency_key' => 'scheduled', 'scheduled_at' => now()->addHour()->toIso8601String(), 'payload' => ['title' => 'Future']];
        $id = $this->postJson($this->path($club), $payload)->assertAccepted()->assertJsonPath('data.status', 'scheduled')->json('data.id');
        $this->artisan('airmius:dispatch-work-automation-jobs')->assertExitCode(0);
        Queue::assertNothingPushed();
        $this->travel(61)->minutes();
        $this->artisan('airmius:dispatch-work-automation-jobs')->assertExitCode(0);
        $this->artisan('airmius:dispatch-work-automation-jobs')->assertExitCode(0);
        Queue::assertPushed(RunWorkAutomationJob::class, 1);
        $this->assertSame('queued', WorkAutomationJob::findOrFail($id)->status);
        $this->postJson($this->path($club), $payload)->assertOk()->assertJsonPath('data.id', $id);

        $job = WorkAutomationJob::query()->create(['club_id' => $club->id, 'kind' => 'reminder', 'status' => 'scheduled', 'idempotency_key' => 'bad-queue', 'scheduled_at' => now()->subMinute(), 'execution_token' => 'dispatch-error']);
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('Injected unavailable queue'));
        $this->assertSame(1, Artisan::call('airmius:dispatch-work-automation-jobs'));
        $this->assertStringContainsString('Queue submission failed', Artisan::output());
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertSame('queue_dispatch_failed', $job->fresh()->error_code);
    }

    public function test_failed_initial_dispatch_is_persisted_and_retry_recovers(): void
    {
        [$club, $owner] = $this->clubWithOwner();
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('Unavailable queue'));
        $service = app(WorkAutomationJobService::class);
        $job = $service->enqueue($club, 'reminder', 'dispatch-failure', $owner);
        $this->assertSame('failed', $job->status);
        $this->assertSame('queue_dispatch_failed', $job->error_code);
        Queue::fake();
        $retried = $service->retry($job, $owner, 0);
        $this->assertSame('queued', $retried->status);
        Queue::assertPushed(RunWorkAutomationJob::class, 1);
        $this->assertSame('completed', $service->run($job->id)->status);
    }

    public function test_api_permissions_details_pagination_and_web_entry_are_club_scoped(): void
    {
        Queue::fake();
        [$club, $owner, $member] = $this->clubWithOwner();
        [$otherClub] = $this->clubWithOwner();
        $service = app(WorkAutomationJobService::class);
        $job = $service->enqueue($club, 'reminder', 'details', $owner, null, [], ['title' => 'Saved title', 'body' => 'Saved message']);
        Sanctum::actingAs($member);
        $this->postJson($this->path($club), ['kind' => 'reminder', 'idempotency_key' => 'unauthorized'])->assertForbidden();
        $this->postJson($this->path($club)."/{$job->id}/retry")->assertForbidden();
        $this->getJson($this->path($club)."/{$job->id}")->assertForbidden();
        $this->getJson('/api/v1/work-automation-clubs')->assertJsonCount(0, 'data');
        Sanctum::actingAs($owner);
        $this->getJson($this->path($otherClub))->assertForbidden();
        $this->getJson('/api/v1/work-automation-clubs')->assertJsonCount(1, 'data');
        $this->getJson($this->path($club)."/{$job->id}")->assertOk()->assertJsonPath('data.title', 'Saved title')->assertJsonPath('data.history.0.event', 'club.work_automation.queued');
        foreach (range(1, 26) as $i) {
            $service->enqueue($club, 'reminder', 'page-'.$i, $owner);
        }
        $this->getJson($this->path($club).'?page=2')->assertOk()->assertJsonPath('meta.total', 27)->assertJsonCount(2, 'data');
        $this->actingAs($owner)->get('/club-work-automation')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Dashboard/WorkAutomation/Index')->has('clubs', 1)->where('workAutomationAvailable', true));
    }

    public function test_document_editor_can_only_view_and_retry_document_jobs(): void
    {
        Queue::fake();
        [$club, $owner, $editor] = $this->clubWithOwner();
        $club->users()->updateExistingPivot($editor->id, ['permission_overrides' => [ClubPermissions::POLICY_DOCUMENTS_EDIT => true]]);
        $service = app(WorkAutomationJobService::class);
        $generic = $service->enqueue($club, 'reminder', 'generic', $owner);
        $document = $service->enqueue($club, 'reminder', 'document', $owner, null, [ClubPermissions::POLICY_DOCUMENTS_EDIT], ['template' => 'policy_document_contract_review']);
        $service->markFailed($document->id, $document->execution_token, 'queue_worker_failed', null);
        Sanctum::actingAs($editor);
        $this->getJson($this->path($club))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.can_create', false);
        $this->getJson($this->path($club)."/{$generic->id}")->assertNotFound();
        $this->postJson($this->path($club)."/{$generic->id}/retry")->assertForbidden();
        $this->postJson($this->path($club)."/{$document->id}/retry")->assertOk()->assertJsonPath('data.status', 'queued');
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
