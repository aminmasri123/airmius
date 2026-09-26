<?php

namespace App\Services;

use App\Jobs\RunWorkAutomationJob;
use App\Models\Club;
use App\Models\User;
use App\Models\WorkAutomationJob;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorkAutomationJobService
{
    public function enqueue(
        Club $club,
        string $kind,
        string $idempotencyKey,
        ?User $actor = null,
        ?Model $subject = null,
        array $recipientRoles = [],
        array $payload = []
    ): WorkAutomationJob {
        $job = DB::transaction(function () use ($club, $kind, $idempotencyKey, $actor, $subject, $recipientRoles, $payload) {
            $job = WorkAutomationJob::query()->firstOrCreate(
                ['club_id' => $club->id, 'idempotency_key' => $idempotencyKey],
                [
                    'created_by' => $actor?->id,
                    'kind' => $kind,
                    'status' => WorkAutomationJob::STATUS_QUEUED,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject?->getKey(),
                    'recipient_roles' => array_values(array_unique($recipientRoles)),
                    'payload' => $payload,
                    'queued_at' => now(),
                ]
            );

            if ($job->wasRecentlyCreated) {
                ClubAuditLog::record($club, $actor, 'club.work_automation.queued', $job, [
                    'job_id' => $job->id,
                    'kind' => $kind,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            return $job;
        });

        if ($job->wasRecentlyCreated) {
            RunWorkAutomationJob::dispatch($job->id);
        }

        return $job;
    }

    public function run(int $jobId): WorkAutomationJob
    {
        $job = DB::transaction(function () use ($jobId) {
            $job = WorkAutomationJob::query()->whereKey($jobId)->lockForUpdate()->firstOrFail();

            if ($job->status === WorkAutomationJob::STATUS_COMPLETED) {
                return $job;
            }

            if ($job->status !== WorkAutomationJob::STATUS_QUEUED) {
                return $job;
            }

            $job->forceFill([
                'status' => WorkAutomationJob::STATUS_RUNNING,
                'attempts' => $job->attempts + 1,
                'started_at' => now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return $job;
        });

        if ($job->status !== WorkAutomationJob::STATUS_RUNNING) {
            return $job;
        }

        try {
            $payload = $job->payload ?? [];
            if (($payload['force_fail'] ?? false) === true) {
                throw new \RuntimeException('Work automation delivery failed.');
            }

            $delivered = $this->deliverNotifications($job, $payload);

            $job->forceFill([
                'status' => WorkAutomationJob::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            ClubAuditLog::record($job->club, null, 'club.work_automation.completed', $job, [
                'job_id' => $job->id,
                'kind' => $job->kind,
                'delivered_notifications' => $delivered,
            ]);
        } catch (Throwable $exception) {
            $job->forceFill([
                'status' => WorkAutomationJob::STATUS_FAILED,
                'failed_at' => now(),
                'error_code' => 'local_delivery_failed',
                'error_message' => substr($exception->getMessage(), 0, 255),
            ])->save();

            ClubAuditLog::record($job->club, null, 'club.work_automation.failed', $job, [
                'job_id' => $job->id,
                'kind' => $job->kind,
                'error_code' => 'local_delivery_failed',
            ]);
        }

        return $job->fresh();
    }

    private function deliverNotifications(WorkAutomationJob $job, array $payload): int
    {
        $club = $job->club;
        if (! $club) {
            return 0;
        }

        $roles = $job->recipient_roles ?: [];
        $recipients = $club->users()
            ->wherePivot('membership_status', '!=', 'former')
            ->get()
            ->filter(function (User $user) use ($club, $roles): bool {
                if ($roles === []) {
                    return ClubPermissions::allows($club, $user, ClubPermissions::GOVERNANCE_EDIT)
                        || ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_MANAGE);
                }

                foreach ($roles as $permission) {
                    if (ClubPermissions::allows($club, $user, $permission)) {
                        return true;
                    }
                }

                return false;
            });

        $type = 'club.work_automation.'.($payload['template'] ?? $job->kind);
        $body = $this->notificationBody($payload);
        $delivered = 0;

        foreach ($recipients as $recipient) {
            AppNotification::send($recipient, $type, [
                'title' => $payload['title'] ?? 'Arbeitsautomation',
                'body' => $body,
                'url' => $payload['url'] ?? "/clubs/{$club->id}",
                'club_id' => $club->id,
                'job_id' => $job->id,
                'kind' => $job->kind,
            ]);
            $delivered++;
        }

        return $delivered;
    }

    private function notificationBody(array $payload): string
    {
        if (($payload['template'] ?? null) === 'policy_document_contract_review') {
            $parts = [
                'Vertragsprüfung fällig',
                $payload['title'] ?? null,
                isset($payload['version_label']) ? 'Version '.$payload['version_label'] : null,
                isset($payload['review_at']) ? 'Review: '.$payload['review_at'] : null,
                isset($payload['contract_ends_on']) ? 'Laufzeit bis '.$payload['contract_ends_on'] : null,
                isset($payload['cancellation_notice_days']) ? 'Kündigungsfrist '.$payload['cancellation_notice_days'].' Tage' : null,
            ];

            return implode(' · ', array_values(array_filter($parts)));
        }

        return $payload['body'] ?? 'Eine Arbeitsautomation wurde verarbeitet.';
    }

    public function retry(WorkAutomationJob $job, User $actor): WorkAutomationJob
    {
        if ($job->status !== WorkAutomationJob::STATUS_FAILED) {
            throw ValidationException::withMessages([
                'job' => __('validation.in', ['attribute' => 'job']),
            ]);
        }

        $job->forceFill([
            'status' => WorkAutomationJob::STATUS_QUEUED,
            'retried_by' => $actor->id,
            'retry_queued_at' => now(),
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ])->save();

        ClubAuditLog::record($job->club, $actor, 'club.work_automation.retry_queued', $job, [
            'job_id' => $job->id,
            'kind' => $job->kind,
            'attempts' => $job->attempts,
        ]);

        RunWorkAutomationJob::dispatch($job->id);

        return $job->fresh();
    }
}
