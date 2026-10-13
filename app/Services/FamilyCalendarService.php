<?php

namespace App\Services;

use App\Models\Event;
use App\Models\GuardianChildRelationship;
use App\Models\User;
use App\Support\MinorSafety;
use Illuminate\Support\Facades\DB;

class FamilyCalendarService
{
    public function relationships(User $guardian, bool $lock = false)
    {
        return GuardianChildRelationship::query()->where('guardian_user_id', $guardian->id)
            ->where('status', GuardianChildRelationship::STATUS_ACCEPTED)
            ->whereNull('revoked_at')->whereNull('declined_at')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('child')->get()
            ->filter(fn ($row) => MinorSafety::isUnderConsentAge($row->child) && MinorSafety::hasResolvedGuardianConsent($row->child)
                && ($row->child->clubs()->whereKey($row->club_id)->where(fn ($q) => $q->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active'))->exists()
                    || (! $row->child->clubs()->whereKey($row->club_id)->exists() && $row->child->teams()->where('club_id', $row->club_id)->exists())))
            ->values();
    }

    public function participantEvents(User $user)
    {
        return Event::query()->visibleTo($user)->where(function ($query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereHas('participantRecords', fn ($q) => $q->where('user_id', $user->id))
                ->orWhereHas('team.users', fn ($q) => $q->where('users.id', $user->id))
                ->orWhere(function ($q) use ($user): void {
                    $q->whereNull('team_id')->where('visibility', 'organization')
                        ->whereHas('club.users', fn ($members) => $members->where('users.id', $user->id));
                });
        });
    }

    public function authorizeChild(User $guardian, int $childId, int $clubId): User
    {
        $relationship = $this->relationships($guardian, DB::transactionLevel() > 0)->first(fn ($row) => $row->child_user_id === $childId && $row->club_id === $clubId);
        abort_unless($relationship, 403);

        return $relationship->child;
    }
}
