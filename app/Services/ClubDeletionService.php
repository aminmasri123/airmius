<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubGovernanceAssignment;
use App\Models\ClubSepaBatch;
use App\Models\ClubSubscription;
use App\Models\User;
use App\Notifications\ClubDeletionChanged;
use App\Support\ClubAuditLog;
use App\Support\ClubRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ClubDeletionService
{
    public function status(Club $club): array
    {
        return [
            'requested_at' => $club->deletion_requested_at?->toIso8601String(),
            'scheduled_at' => $club->deletion_scheduled_at?->toIso8601String(),
            'blocked' => $club->deletion_blocked_at !== null,
            'blocker' => $this->blocker($club),
            'confirmation' => __('club_deletion.confirmation'),
            'grace_days' => 30,
        ];
    }

    public function request(Club $club, User $actor, string $confirmation): Club
    {
        return DB::transaction(function () use ($club, $actor, $confirmation) {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            Gate::forUser($actor)->authorize('delete', $club);
            $phrases = array_map(fn ($locale) => __('club_deletion.confirmation', [], $locale), ['de', 'en']);
            if (! in_array(trim($confirmation), $phrases, true)) {
                throw ValidationException::withMessages(['confirmation' => __('club_deletion.wrong_confirmation')]);
            }
            if ($club->deletion_scheduled_at) {
                return $club;
            }
            if ($blocker = $this->blocker($club)) {
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
            Gate::forUser($actor)->authorize('delete', $club);
            if (! $club->deletion_scheduled_at) {
                return $club;
            }
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
            $this->notify($club, 'completed');
            app(ClubDataErasureService::class)->erase($club);
        });
    }

    public function blocker(Club $club): ?string
    {
        if (ClubSepaBatch::where('club_id', $club->id)->exists()) {
            return __('club_deletion.retention');
        }
        foreach (['invoices', 'payments', 'bank_transactions', 'club_finance_entries'] as $table) {
            if (DB::table($table)->where('club_id', $club->id)->exists()) {
                return __('club_deletion.finance_retention');
            }
        }
        if (ClubSubscription::where('club_id', $club->id)->whereNotNull('provider_subscription_id')
            ->whereNotIn('status', ['cancelled', 'expired'])->exists()) {
            return __('club_deletion.subscription');
        }

        return null;
    }

    private function notify(Club $club, string $event): void
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
        foreach ($recipients as $user) {
            $locale = $user->language === 'de' ? 'de' : 'en';
            $title = __('club_deletion.'.$event.'_title', [], $locale);
            $body = __('club_deletion.'.$event.'_body', $replace, $locale);
            $user->notify(new ClubDeletionChanged($title, $body, $url, __('club_deletion.open', [], $locale), $event));
        }
        foreach ($board->pluck('externalMember.email')->filter()->unique()->diff($recipients->pluck('email')) as $email) {
            Notification::route('mail', $email)->notify(new ClubDeletionChanged(
                __('club_deletion.'.$event.'_title'), __('club_deletion.'.$event.'_body', $replace), $url, __('club_deletion.open'), $event,
            ));
        }
    }
}
