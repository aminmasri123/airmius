<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;
use App\Models\WorkAutomationJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class WorkAutomationAccess
{
    public static function canCreate(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_MANAGE)
            || ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_MANAGE);
    }

    public static function canView(Club $club, User $user): bool
    {
        return self::canCreate($club, $user)
            || ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_EDIT);
    }

    public static function visibleJobs(Builder $query, Club $club, User $user): Builder
    {
        return $query->where('club_id', $club->id)
            ->when(! self::canCreate($club, $user), fn (Builder $query) => $query
                ->where('payload->template', 'policy_document_contract_review'));
    }

    public static function canRetry(WorkAutomationJob $job, User $user): bool
    {
        return self::canCreate($job->club, $user)
            || (($job->payload['template'] ?? null) === 'policy_document_contract_review'
                && ClubPermissions::allows($job->club, $user, ClubPermissions::POLICY_DOCUMENTS_EDIT));
    }

    public static function clubs(User $user): Collection
    {
        return Club::query()
            ->when(! $user->hasAnyRole(Roles::FULL_ACCESS), fn (Builder $query) => $query->linkedToUser($user))
            ->orderBy('name')->get()
            ->filter(fn (Club $club) => self::canView($club, $user))
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'can_create' => self::canCreate($club, $user),
            ])->values();
    }
}
