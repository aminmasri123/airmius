<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubDeletionFinancialReview;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubSubscription;
use App\Models\User;
use App\Notifications\ClubDeletionChanged;
use App\Support\ClubAuditLog;
use App\Support\ClubRoles;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClubDeletionService
{
    public function status(Club $club, ?User $actor = null): array
    {
        $financial = $this->latestFinancialReview($club);
        $hasFinancialRecords = $this->hasFinancialRecords($club);
        $currentExport = $hasFinancialRecords && $this->financialExportCurrent($club, $financial);
        $archiveConfirmed = $currentExport && $financial->archive_confirmed_at !== null;
        $blocker = $this->subscriptionBlocker($club)
            ?? ($hasFinancialRecords && ! $archiveConfirmed ? __('club_deletion.finance_retention') : null);
        $waitingForSchedule = $club->deletion_blocked_at && $club->deletion_scheduled_at?->lt(now()->addDays(7));

        return [
            'requested_at' => $club->deletion_requested_at?->toIso8601String(),
            'scheduled_at' => $blocker || $waitingForSchedule ? null : $club->deletion_scheduled_at?->toIso8601String(),
            'blocked' => $club->deletion_requested_at !== null && $blocker !== null,
            'blocker' => $blocker,
            'review_status' => match (true) {
                ! $hasFinancialRecords => 'not_required',
                ! $currentExport => 'export_required',
                ! $archiveConfirmed => 'archive_required',
                default => 'passed',
            },
            'confirmation' => __('club_deletion.confirmation'),
            'grace_days' => 30,
            'requires_password' => $actor ? app(ClubDeletionIdentityService::class)->requiresPassword($actor) : true,
            'financial_exported_at' => $currentExport ? $financial->exported_at?->toIso8601String() : null,
            'archive_confirmed_at' => $archiveConfirmed ? $financial->archive_confirmed_at->toIso8601String() : null,
            'financial_revoked_at' => $financial?->revoked_at?->toIso8601String(),
            'has_financial_records' => $hasFinancialRecords,
            'can_request' => $actor && (int) $actor->id === (int) $club->owner_id && ! $club->deletion_requested_at,
        ];
    }

    public function recordFinancialExport(Club $club, User $actor, string $fingerprint): void
    {
        DB::transaction(function () use ($club, $actor, $fingerprint): void {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->authorizeOwner($club, $actor);
            if (! hash_equals($fingerprint, app(ClubFinancialClosingExportService::class)->fingerprint($club))) {
                throw ValidationException::withMessages(['export' => __('club_deletion.export_required')]);
            }
            $previous = $this->latestFinancialReview($club);
            if ($previous && ! $previous->revoked_at && ! $previous->rejected_at
                && (int) $previous->owner_id === (int) $actor->id
                && hash_equals($previous->fingerprint, $fingerprint)) {
                return;
            }
            ClubDeletionFinancialReview::query()->create([
                'club_id' => $club->id,
                'club_id_snapshot' => $club->id,
                'owner_id' => $actor->id,
                'fingerprint' => $fingerprint,
                'exported_at' => now(),
            ]);
            if ($club->deletion_scheduled_at && ! $club->deletion_blocked_at) {
                $club->forceFill(['deletion_blocked_at' => now()])->save();
            }
            ClubAuditLog::record($club, $actor, 'club.deletion.financial_exported', $club);
        });
    }

    public function confirmExternalArchive(Club $club, User $actor): void
    {
        DB::transaction(function () use ($club, $actor): void {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->authorizeOwner($club, $actor);
            $review = $this->latestFinancialReview($club);
            if (! $this->financialExportCurrent($club, $review)) {
                throw ValidationException::withMessages(['archive' => __('club_deletion.export_required')]);
            }
            $review->forceFill(['archive_confirmed_at' => now()])->save();
            ClubAuditLog::record($club, $actor, 'club.deletion.external_archive_confirmed', $club, ['review_id' => $review->id]);
        });
    }

    private function latestFinancialReview(Club $club): ?ClubDeletionFinancialReview
    {
        return ClubDeletionFinancialReview::query()->where('club_id', $club->id)->latest('id')->first();
    }

    private function authorizeOwner(Club $club, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $club);
        abort_unless((int) $club->owner_id === (int) $actor->id, 403);
    }

    public function request(Club $club, User $actor, string $confirmation, string $code): Club
    {
        return DB::transaction(function () use ($club, $actor, $confirmation, $code) {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->authorizeOwner($club, $actor);
            $phrases = array_map(fn ($locale) => __('club_deletion.confirmation', [], $locale), ['de', 'en']);
            if (! in_array(trim($confirmation), $phrases, true)) {
                throw ValidationException::withMessages(['confirmation' => __('club_deletion.wrong_confirmation')]);
            }
            if ($club->deletion_scheduled_at) {
                return $club;
            }
            app(ClubDeletionIdentityService::class)->consumeCode($club, $actor, $code);
            $blocked = $this->blocker($club) !== null;
            $club->forceFill([
                'deletion_requested_at' => now(),
                'deletion_scheduled_at' => now()->addDays(30),
                'deletion_requested_by' => $actor->id,
                'deletion_owner_id' => $club->owner_id,
                'deletion_reminded_at' => null,
                'deletion_blocked_at' => $blocked ? now() : null,
            ])->save();
            ClubAuditLog::record($club, $actor, 'club.deletion.requested', $club, [
                'scheduled_at' => $club->deletion_scheduled_at->toIso8601String(),
            ]);
            $this->notify($club, $blocked ? 'requested_pending' : 'requested');

            return $club;
        });
    }

    public function cancel(Club $club, User $actor): Club
    {
        return DB::transaction(function () use ($club, $actor) {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->authorizeOwner($club, $actor);
            if (! $club->deletion_scheduled_at) {
                return $club;
            }
            $this->latestFinancialReview($club)?->forceFill(['revoked_at' => now()])->save();
            $club->forceFill(array_fill_keys([
                'deletion_requested_at', 'deletion_scheduled_at', 'deletion_requested_by',
                'deletion_reminded_at', 'deletion_blocked_at',
                'deletion_owner_id',
            ], null))->save();
            ClubAuditLog::record($club, $actor, 'club.deletion.cancelled', $club);
            $this->notify($club, 'cancelled');

            return $club;
        });
    }

    public function process(int $id): void
    {
        DB::transaction(function () use ($id) {
            $club = Club::query()->lockForUpdate()->find($id);
            if (! $club?->deletion_scheduled_at) {
                return;
            }
            if ((int) $club->deletion_owner_id !== (int) $club->owner_id) {
                $this->cancel($club, $club->owner);

                return;
            }
            if ($this->blocker($club)) {
                if (! $club->deletion_blocked_at) {
                    $club->forceFill(['deletion_blocked_at' => now()])->save();
                    $this->notify($club, 'blocked');
                }

                return;
            }
            if ($club->deletion_blocked_at) {
                $earliest = now()->addDays(7);
                $rescheduled = $club->deletion_scheduled_at->lt($earliest);
                $club->forceFill([
                    'deletion_scheduled_at' => $rescheduled ? $earliest : $club->deletion_scheduled_at,
                    'deletion_blocked_at' => null,
                    'deletion_reminded_at' => $rescheduled ? now() : $club->deletion_reminded_at,
                ])->save();
                $this->notify($club, 'scheduled');
            }
            if ($club->deletion_scheduled_at->isFuture()) {
                if (! $club->deletion_reminded_at && $club->deletion_scheduled_at->lte(now()->addDays(7))) {
                    $this->notify($club, 'reminder');
                    $club->forceFill(['deletion_reminded_at' => now()])->save();
                }

                return;
            }
            $sendCompleted = $this->notificationSender($club, 'completed');
            app(ClubDataErasureService::class)->erase($club);
            DB::afterCommit($sendCompleted);
        });
    }

    public function blocker(Club $club): ?string
    {
        if ($subscription = $this->subscriptionBlocker($club)) {
            return $subscription;
        }
        if ($this->hasFinancialRecords($club)) {
            $review = $this->latestFinancialReview($club);
            if (! $this->financialExportCurrent($club, $review) || ! $review->archive_confirmed_at) {
                return __('club_deletion.finance_retention');
            }
        }

        return null;
    }

    private function subscriptionBlocker(Club $club): ?string
    {
        return ClubSubscription::where('club_id', $club->id)->whereNotNull('provider_subscription_id')
            ->whereNotIn('status', ['cancelled', 'expired'])->exists()
            ? __('club_deletion.subscription')
            : null;
    }

    private function hasFinancialRecords(Club $club): bool
    {
        return app(ClubFinancialClosingExportService::class)->hasFinancialRecords($club);
    }

    private function financialExportCurrent(Club $club, ?ClubDeletionFinancialReview $review): bool
    {
        return $review && $review->exported_at && ! $review->revoked_at && ! $review->rejected_at
            && (int) $review->owner_id === (int) $club->owner_id
            && $this->financialFingerprintMatches($club, $review);
    }

    private function financialFingerprintMatches(Club $club, ClubDeletionFinancialReview $review): bool
    {
        try {
            return hash_equals($review->fingerprint, app(ClubFinancialClosingExportService::class)->fingerprint($club));
        } catch (Throwable) {
            return false;
        }
    }

    private function notify(Club $club, string $event): void
    {
        DB::afterCommit($this->notificationSender($club, $event));
    }

    private function notificationSender(Club $club, string $event): Closure
    {
        $members = $club->users();
        ClubRoles::whereAny($members, ['owner']);
        $board = ClubGovernanceAssignment::query()->where('club_id', $club->id)
            ->whereHas('body', fn ($query) => $query->where('type', 'board'))
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->with(['user', 'externalMember'])->get();
        $recipients = $members->get()->concat([$club->owner])->concat($board->pluck('user'))->filter()->unique('id');
        $url = $event === 'completed' ? url('/dashboard') : route('auth.clubs.show', $club->id);
        $replace = ['club' => $club->name, 'date' => $club->deletion_scheduled_at?->utc()->format('Y-m-d H:i').' UTC'];
        $externalEmails = $board->pluck('externalMember.email')->filter()->unique()->diff($recipients->pluck('email'));
        $send = function () use ($recipients, $externalEmails, $event, $replace, $url): void {
            foreach ($recipients as $user) {
                $locale = $user->language === 'de' ? 'de' : 'en';
                $title = __('club_deletion.'.$event.'_title', [], $locale);
                $body = __('club_deletion.'.$event.'_body', $replace, $locale);
                $user->notify(new ClubDeletionChanged($title, $body, $url, __('club_deletion.open', [], $locale), $event));
            }
            foreach ($externalEmails as $email) {
                Notification::route('mail', $email)->notify(new ClubDeletionChanged(
                    __('club_deletion.'.$event.'_title'), __('club_deletion.'.$event.'_body', $replace), $url, __('club_deletion.open'), $event,
                ));
            }
        };

        return $send;
    }
}
