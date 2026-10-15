<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubDeletionFinancialReview;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubSubscription;
use App\Models\SupportTicket;
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
        $review = $actor ? $this->openReview($club, $actor) : null;
        $financial = $this->latestFinancialReview($club);
        if ($review && $financial && $review->updated_at->lt($financial->exported_at)) {
            $review = null;
        }

        return [
            'requested_at' => $club->deletion_requested_at?->toIso8601String(),
            'scheduled_at' => $club->deletion_scheduled_at?->toIso8601String(),
            'blocked' => $club->deletion_blocked_at !== null,
            'blocker' => $this->blocker($club),
            'confirmation' => __('club_deletion.confirmation'),
            'grace_days' => 30,
            'requires_password' => $actor ? app(ClubDeletionIdentityService::class)->requiresPassword($actor) : true,
            'review_ticket_id' => $review?->id,
            'review_requested_at' => $review?->created_at?->toIso8601String(),
            'financial_exported_at' => $financial && ! $financial->revoked_at ? $financial->exported_at?->toIso8601String() : null,
            'archive_confirmed_at' => $financial && ! $financial->revoked_at ? $financial->archive_confirmed_at?->toIso8601String() : null,
            'financial_approved_at' => $financial && ! $financial->revoked_at ? $financial->approved_at?->toIso8601String() : null,
            'financial_rejected_at' => $financial?->rejected_at?->toIso8601String(),
            'financial_revoked_at' => $financial?->revoked_at?->toIso8601String(),
            'has_financial_records' => $this->hasFinancialRecords($club),
            'can_request' => $actor && $this->requestBlocker($club, $actor) === null,
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
            ClubDeletionFinancialReview::query()->create([
                'club_id' => $club->id,
                'club_id_snapshot' => $club->id,
                'owner_id' => $actor->id,
                'fingerprint' => $fingerprint,
                'exported_at' => now(),
            ]);
            ClubAuditLog::record($club, $actor, 'club.deletion.financial_exported', $club);
        });
    }

    public function confirmExternalArchive(Club $club, User $actor): void
    {
        DB::transaction(function () use ($club, $actor): void {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->authorizeOwner($club, $actor);
            $review = $this->latestFinancialReview($club);
            if (! $review || $review->revoked_at
                || $review->fingerprint !== app(ClubFinancialClosingExportService::class)->fingerprint($club)) {
                throw ValidationException::withMessages(['archive' => __('club_deletion.export_required')]);
            }
            $review->forceFill(['archive_confirmed_at' => now()])->save();
            ClubAuditLog::record($club, $actor, 'club.deletion.external_archive_confirmed', $club, ['review_id' => $review->id]);
        });
    }

    public function decideFinancialReview(Club $club, User $actor, bool $approve, string $note): void
    {
        abort_unless($actor->hasRole('super_admin'), 403);
        DB::transaction(function () use ($club, $actor, $approve, $note): void {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $review = $this->latestFinancialReview($club);
            if ($approve && ClubSubscription::where('club_id', $club->id)->whereNotNull('provider_subscription_id')
                ->whereNotIn('status', ['cancelled', 'expired'])->exists()) {
                throw ValidationException::withMessages(['club' => __('club_deletion.subscription')]);
            }
            if (! $review || ! $review->archive_confirmed_at || $review->revoked_at || (int) $review->owner_id !== (int) $club->owner_id
                || $review->fingerprint !== app(ClubFinancialClosingExportService::class)->fingerprint($club)
                || ! SupportTicket::query()->where('user_id', $club->owner_id)
                    ->where('report_source', 'club_deletion_review')
                    ->where('affected_person_reference', 'club:'.$club->id)
                    ->where('updated_at', '>=', $review->exported_at)->exists()) {
                throw ValidationException::withMessages(['club' => __('club_deletion.review_not_ready')]);
            }
            $review->forceFill([
                'approved_at' => $approve ? now() : null,
                'approved_by' => $approve ? $actor->id : null,
                'rejected_at' => $approve ? null : now(),
                'review_note' => $note,
            ])->save();
            ClubAuditLog::record($club, $actor, $approve ? 'club.deletion.financial_review_approved' : 'club.deletion.financial_review_rejected', $club, [
                'review_id' => $review->id,
            ]);
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

    public function requestReview(Club $club, User $actor): SupportTicket
    {
        return DB::transaction(function () use ($club, $actor): SupportTicket {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->authorizeOwner($club, $actor);
            $blocker = $this->blocker($club);
            if ($blocker === null) {
                throw ValidationException::withMessages(['club' => __('club_deletion.review_not_needed')]);
            }
            if ($existing = $this->openReview($club, $actor)) {
                $financial = $this->latestFinancialReview($club);
                if ($financial && $existing->updated_at->lt($financial->exported_at)) {
                    $existing->touch();
                }

                return $existing;
            }

            return SupportTicket::query()->create([
                'user_id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
                'subject' => __('club_deletion.review_subject'),
                'message' => __('club_deletion.review_message', [
                    'club' => $club->name,
                    'id' => $club->id,
                    'blocker' => $blocker,
                ]),
                'category' => 'privacy',
                'priority' => 'high',
                'status' => 'open',
                'is_confidential' => true,
                'allow_follow_up' => true,
                'report_source' => 'club_deletion_review',
                'affected_person_reference' => 'club:'.$club->id,
                ...app(SupportSlaService::class)->initialAttributes('high'),
            ]);
        });
    }

    private function openReview(Club $club, User $actor): ?SupportTicket
    {
        return SupportTicket::query()->where('user_id', $actor->id)
            ->where('report_source', 'club_deletion_review')
            ->where('affected_person_reference', 'club:'.$club->id)
            ->whereIn('status', ['open', 'in_progress', 'waiting_user'])
            ->latest('id')->first();
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
            if ($blocker = $this->requestBlocker($club, $actor)) {
                throw ValidationException::withMessages(['club' => $blocker]);
            }
            $club->forceFill([
                'deletion_requested_at' => now(),
                'deletion_scheduled_at' => now()->addDays(30),
                'deletion_requested_by' => $actor->id,
                'deletion_owner_id' => $club->owner_id,
                'deletion_reminded_at' => null,
                'deletion_blocked_at' => null,
            ])->save();
            ClubAuditLog::record($club, $actor, 'club.deletion.requested', $club, [
                'scheduled_at' => $club->deletion_scheduled_at->toIso8601String(),
            ]);
            $this->notify($club, 'requested');

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
            if ($club->deletion_scheduled_at->isFuture()) {
                if (! $club->deletion_reminded_at && $club->deletion_scheduled_at->lte(now()->addDays(7))) {
                    $this->notify($club, 'reminder');
                    $club->forceFill(['deletion_reminded_at' => now()])->save();
                }

                return;
            }
            if ($this->blocker($club)) {
                if (! $club->deletion_blocked_at) {
                    $this->notify($club, 'blocked');
                    $club->forceFill(['deletion_blocked_at' => now()])->save();
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
        if (ClubSubscription::where('club_id', $club->id)->whereNotNull('provider_subscription_id')
            ->whereNotIn('status', ['cancelled', 'expired'])->exists()) {
            return __('club_deletion.subscription');
        }

        if ($this->hasFinancialRecords($club) && ! $this->financialReviewValid($club)) {
            return __('club_deletion.finance_retention');
        }

        return null;
    }

    private function requestBlocker(Club $club, User $actor): ?string
    {
        if (ClubSubscription::where('club_id', $club->id)->whereNotNull('provider_subscription_id')
            ->whereNotIn('status', ['cancelled', 'expired'])->exists()) {
            return __('club_deletion.subscription');
        }
        if (! $this->hasFinancialRecords($club)) {
            return null;
        }
        $review = $this->latestFinancialReview($club);
        if (! $review || ! $review->archive_confirmed_at || $review->revoked_at || (int) $review->owner_id !== (int) $actor->id
            || ! $this->financialFingerprintMatches($club, $review)) {
            return __('club_deletion.archive_required');
        }
        if (! SupportTicket::query()->where('user_id', $actor->id)
            ->where('report_source', 'club_deletion_review')
            ->where('affected_person_reference', 'club:'.$club->id)
            ->where('updated_at', '>=', $review->exported_at)->exists()) {
            return __('club_deletion.review_required');
        }

        return null;
    }

    private function hasFinancialRecords(Club $club): bool
    {
        return app(ClubFinancialClosingExportService::class)->hasFinancialRecords($club);
    }

    private function financialReviewValid(Club $club): bool
    {
        $review = $this->latestFinancialReview($club);

        return $review && $review->approved_at && $review->archive_confirmed_at && ! $review->revoked_at
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
        ($this->notificationSender($club, $event))();
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
