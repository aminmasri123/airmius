<?php

namespace App\Services;

use App\Exceptions\NoWorkAutomationRecipients;
use App\Jobs\RunWorkAutomationJob;
use App\Models\Club;
use App\Models\User;
use App\Models\WorkAutomationJob;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\WorkAutomationAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        array $payload = [],
        ?CarbonInterface $scheduledAt = null
    ): WorkAutomationJob {
        if ($scheduledAt === null && ($payload['template'] ?? null) === 'policy_document_contract_review' && isset($payload['review_at'])) {
            $scheduledAt = Carbon::parse($payload['review_at'], config('app.timezone'))->startOfDay();
        }
        $job = DB::transaction(function () use ($club, $kind, $idempotencyKey, $actor, $subject, $recipientRoles, $payload, $scheduledAt) {
            $job = WorkAutomationJob::query()->firstOrCreate(
                ['club_id' => $club->id, 'idempotency_key' => $idempotencyKey],
                [
                    'created_by' => $actor?->id,
                    'kind' => $kind,
                    'status' => $scheduledAt?->isFuture() ? WorkAutomationJob::STATUS_SCHEDULED : WorkAutomationJob::STATUS_QUEUED,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject?->getKey(),
                    'recipient_roles' => array_values(array_unique($recipientRoles)),
                    'payload' => $payload,
                    'queued_at' => now(),
                    'execution_token' => (string) Str::uuid(),
                    'scheduled_at' => $scheduledAt,
                ]
            );

            if ($job->kind !== $kind
                || $job->subject_type !== $subject?->getMorphClass()
                || $job->subject_id != $subject?->getKey()
                || $this->canonical($job->recipient_roles ?? []) !== $this->canonical(array_values(array_unique($recipientRoles)))
                || ($subject === null && $this->canonical($job->payload ?? []) !== $this->canonical($payload))
                || $job->scheduled_at?->getTimestamp() !== $scheduledAt?->getTimestamp()) {
                throw ValidationException::withMessages(['idempotency_key' => 'This request key already belongs to a different job.']);
            }

            if ($subject !== null && ! $job->wasRecentlyCreated && (int) $job->attempts === 0
                && in_array($job->status, [WorkAutomationJob::STATUS_SCHEDULED, WorkAutomationJob::STATUS_QUEUED], true)) {
                $job->forceFill(['payload' => $payload])->save();
            }

            if ($job->wasRecentlyCreated) {
                ClubAuditLog::record($club, $actor, 'club.work_automation.queued', $job, [
                    'job_id' => $job->id,
                    'kind' => $kind,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            return $job;
        });

        if ($job->wasRecentlyCreated && $job->status === WorkAutomationJob::STATUS_QUEUED) {
            $this->dispatch($job);
        }

        return $job->refresh();
    }

    public function run(int $jobId, ?string $executionToken = null): WorkAutomationJob
    {
        [$job, $claimed] = DB::transaction(function () use ($jobId, $executionToken) {
            $job = WorkAutomationJob::query()->whereKey($jobId)->lockForUpdate()->firstOrFail();

            if ($job->status !== WorkAutomationJob::STATUS_QUEUED
                || ($executionToken !== null && $job->execution_token !== $executionToken)) {
                return [$job, false];
            }

            $job->forceFill([
                'status' => WorkAutomationJob::STATUS_RUNNING,
                'attempts' => $job->attempts + 1,
                'started_at' => now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return [$job, true];
        });

        if (! $claimed) {
            return $job;
        }

        try {
            $payload = $job->payload ?? [];
            $result = $this->deliverNotifications($job, $payload);
            DB::transaction(function () use ($job, $result) {
                $current = WorkAutomationJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== WorkAutomationJob::STATUS_RUNNING || $current->execution_token !== $job->execution_token) {
                    return;
                }
                $current->forceFill([
                    'status' => WorkAutomationJob::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'result' => $result,
                ])->save();
                ClubAuditLog::record($current->club, null, 'club.work_automation.completed', $current, [
                    'job_id' => $current->id,
                    'kind' => $current->kind,
                    ...$result,
                ]);
            });
        } catch (Throwable $exception) {
            $this->markFailed($job->id, $job->execution_token, $exception instanceof NoWorkAutomationRecipients ? 'no_recipients' : 'local_delivery_failed', $exception);
        }

        return $job->fresh();
    }

    private function deliverNotifications(WorkAutomationJob $job, array $payload): array
    {
        $club = $job->club;
        if (! $club) {
            throw new \RuntimeException('Club no longer exists.');
        }

        $recipients = $this->recipients($club, $job->recipient_roles ?: []);
        if ($recipients->isEmpty()) {
            throw new NoWorkAutomationRecipients('No authorized recipients.');
        }

        $type = 'club.work_automation.'.($payload['template'] ?? $job->kind);
        $body = $this->notificationBody($payload);
        $delivered = 0;
        $skipped = 0;

        foreach ($recipients as $recipient) {
            $notification = AppNotification::send($recipient, $type, [
                'title' => $payload['title'] ?? 'Arbeitsautomation',
                'body' => $body,
                'url' => $payload['url'] ?? "/club-work-automation?club_id={$club->id}",
                'club_id' => $club->id,
                'job_id' => $job->id,
                'kind' => $job->kind,
            ], ['dedupe_key' => 'work-automation:'.$job->club_id.':'.$job->id]);
            $notification ? $delivered++ : $skipped++;
        }

        return ['stored_notifications' => $delivered, 'skipped_notifications' => $skipped];
    }

    public function recipients(Club $club, array $roles = []): Collection
    {
        $members = $club->users()
            ->where(fn ($query) => $query->whereNull('club_user.membership_status')
                ->orWhereIn('club_user.membership_status', ['active', 'non_member']))
            ->get();
        if ($club->owner && ! $members->contains('id', $club->owner_id)) {
            $members->push($club->owner);
        }

        return $members
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
            })->unique('id')->values();
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

    public function retry(WorkAutomationJob $job, User $actor, ?int $expectedAttempts = null): WorkAutomationJob
    {
        [$job, $queued] = DB::transaction(function () use ($job, $actor, $expectedAttempts) {
            $job = WorkAutomationJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            abort_unless(WorkAutomationAccess::canRetry($job, $actor), 403);
            if (($expectedAttempts !== null && $expectedAttempts !== (int) $job->attempts)
                || in_array($job->status, [WorkAutomationJob::STATUS_QUEUED, WorkAutomationJob::STATUS_RUNNING], true)) {
                return [$job, false];
            }
            if ($job->status !== WorkAutomationJob::STATUS_FAILED) {
                throw ValidationException::withMessages(['job' => __('validation.in', ['attribute' => 'job'])]);
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
                'execution_token' => (string) Str::uuid(),
                'result' => null,
                'dispatched_at' => null,
            ])->save();

            ClubAuditLog::record($job->club, $actor, 'club.work_automation.retry_queued', $job, [
                'job_id' => $job->id,
                'kind' => $job->kind,
                'attempts' => $job->attempts,
            ]);

            return [$job, true];
        });
        if ($queued) {
            $this->dispatch($job);
        }

        return $job->fresh();
    }

    private function dispatch(WorkAutomationJob $job): void
    {
        DB::afterCommit(function () use ($job) {
            $this->dispatchPending($job->id);
        });
    }

    public function dispatchPending(int $jobId): bool
    {
        $executionToken = null;
        try {
            return DB::transaction(function () use ($jobId, &$executionToken) {
                $job = WorkAutomationJob::query()->whereKey($jobId)->lockForUpdate()->first();
                if (! $job || $job->dispatched_at !== null) {
                    return true;
                }
                if ($job->status === WorkAutomationJob::STATUS_SCHEDULED && $job->scheduled_at?->lessThanOrEqualTo(now())) {
                    $job->forceFill(['status' => WorkAutomationJob::STATUS_QUEUED, 'queued_at' => now()])->save();
                }
                if ($job->status !== WorkAutomationJob::STATUS_QUEUED) {
                    return true;
                }
                $executionToken = $job->execution_token;
                // The database queue insert and dispatch receipt commit together.
                RunWorkAutomationJob::dispatch($job->id, $executionToken);
                $job->forceFill(['dispatched_at' => now()])->save();

                return true;
            });
        } catch (Throwable $exception) {
            // The transaction may have restored a due job to scheduled.
            WorkAutomationJob::query()->whereKey($jobId)->where('status', WorkAutomationJob::STATUS_SCHEDULED)
                ->update(['status' => WorkAutomationJob::STATUS_QUEUED]);
            $this->markFailed($jobId, $executionToken, 'queue_dispatch_failed', $exception);

            return false;
        }
    }

    public function markFailed(int $jobId, ?string $executionToken, string $code, ?Throwable $exception): void
    {
        DB::transaction(function () use ($jobId, $executionToken, $code, $exception) {
            $job = WorkAutomationJob::query()->whereKey($jobId)->lockForUpdate()->first();
            if (! $job || $job->execution_token !== $executionToken
                || ! in_array($job->status, [WorkAutomationJob::STATUS_QUEUED, WorkAutomationJob::STATUS_RUNNING], true)) {
                return;
            }
            $job->forceFill([
                'status' => WorkAutomationJob::STATUS_FAILED,
                'failed_at' => now(),
                'error_code' => $code,
                'error_message' => $exception ? substr($exception->getMessage(), 0, 255) : 'Queue worker failed.',
            ])->save();
            ClubAuditLog::record($job->club, null, 'club.work_automation.failed', $job, [
                'job_id' => $job->id, 'kind' => $job->kind, 'error_code' => $code,
            ]);
        });
    }

    private function canonical(array $value): string
    {
        $normalize = function (array $items) use (&$normalize): array {
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $item = $normalize($item);
                }
            }
            unset($item);
            if (array_is_list($items)) {
                sort($items);
            } else {
                ksort($items);
            }

            return $items;
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR);
    }
}
