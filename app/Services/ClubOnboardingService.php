<?php

namespace App\Services;

use App\Models\Club;
use App\Support\ClubRoles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClubOnboardingService
{
    public const VERSION = '2026-08-09.club-onboarding.v1';

    /**
     * Build onboarding summaries in a fixed number of aggregate queries.
     *
     * @param  Collection<int, Club>  $clubs
     * @return Collection<int, array<string, mixed>>
     */
    public function forClubs(Collection $clubs): Collection
    {
        $clubs = $clubs->values();
        $clubIds = $clubs->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($clubIds === []) {
            return collect();
        }

        $teamCounts = $this->counts('teams', $clubIds);
        $memberCounts = $this->counts('club_user', $clubIds);
        $externalMemberCounts = $this->counts('club_external_members', $clubIds);
        $membershipTypeCounts = $this->counts('club_membership_types', $clubIds, fn ($query) => $query->where('is_active', true));
        $eventCounts = $this->counts('events', $clubIds);
        $postCounts = $this->counts('posts', $clubIds);
        $announcementCounts = $this->counts('club_announcements', $clubIds);
        $fileCounts = $this->counts('files', $clubIds);
        $elevatedCounts = DB::table('club_user')
            ->whereIn('club_id', $clubIds)
            ->where(function ($query): void {
                $query->whereIn('role', ClubRoles::ELEVATED);
                foreach (ClubRoles::ELEVATED as $role) {
                    $query->orWhere('roles', 'like', '%"'.$role.'"%');
                }
            })
            ->selectRaw('club_id, COUNT(*) as aggregate')
            ->groupBy('club_id')
            ->pluck('aggregate', 'club_id');

        return $clubs->mapWithKeys(function (Club $club) use (
            $teamCounts,
            $memberCounts,
            $externalMemberCounts,
            $membershipTypeCounts,
            $eventCounts,
            $postCounts,
            $announcementCounts,
            $fileCounts,
            $elevatedCounts,
        ): array {
            $clubId = (int) $club->id;
            $peopleCount = (int) ($memberCounts[$clubId] ?? 0) + (int) ($externalMemberCounts[$clubId] ?? 0);
            $steps = [
                $this->step($club, 'profile', filled($club->name) && filled($club->sport_type) && filled($club->country) && filled($club->city), 'profile'),
                $this->step($club, 'verification', $club->verification_status === 'verified', 'verification'),
                $this->step($club, 'roles', (int) ($elevatedCounts[$clubId] ?? 0) >= 2, 'roles'),
                $this->step($club, 'team', (int) ($teamCounts[$clubId] ?? 0) > 0, 'teams'),
                $this->step(
                    $club,
                    'membership',
                    (bool) $club->membership_requests_enabled && (int) ($membershipTypeCounts[$clubId] ?? 0) > 0,
                    'memberships',
                ),
                $this->step($club, 'members', $peopleCount >= 2, 'members'),
                $this->step($club, 'event', (int) ($eventCounts[$clubId] ?? 0) > 0, 'events'),
                $this->step(
                    $club,
                    'communication',
                    (int) ($postCounts[$clubId] ?? 0) + (int) ($announcementCounts[$clubId] ?? 0) > 0,
                    'feed',
                ),
                $this->step($club, 'documents', (int) ($fileCounts[$clubId] ?? 0) > 0, 'files'),
            ];
            $completed = collect($steps)->where('done', true)->count();

            return [$clubId => [
                'version' => self::VERSION,
                'title' => __('club_onboarding.title'),
                'subtitle' => __('club_onboarding.subtitle'),
                'completion_percent' => (int) round(($completed / count($steps)) * 100),
                'completed_steps' => $completed,
                'total_steps' => count($steps),
                'open_steps' => count($steps) - $completed,
                'progress_label' => __('club_onboarding.progress', ['completed' => $completed, 'total' => count($steps)]),
                'steps' => $steps,
            ]];
        });
    }

    /** @return array<string, mixed> */
    public function forClub(Club $club): array
    {
        return $this->forClubs(collect([$club]))->get((int) $club->id, []);
    }

    /** @return array<string, mixed> */
    private function step(Club $club, string $key, bool $done, string $action): array
    {
        return [
            'key' => $key,
            'done' => $done,
            'status_label' => $done ? __('club_onboarding.complete') : __('club_onboarding.open'),
            'title' => __('club_onboarding.steps.'.$key.'.title'),
            'description' => __('club_onboarding.steps.'.$key.'.description'),
            'action' => $action,
            'action_label' => __('club_onboarding.actions.'.$action),
            'action_path' => $this->actionPath($club, $action),
        ];
    }

    private function actionPath(Club $club, string $action): string
    {
        return match ($action) {
            'profile', 'verification' => route('auth.clubs.show', ['club' => $club->id], false),
            'roles', 'memberships', 'members' => route('auth.club-memberships.index', ['club_id' => $club->id], false),
            'teams' => route('auth.teams.index', ['club_id' => $club->id], false),
            'events' => route('auth.events.index', ['club_id' => $club->id], false),
            'feed' => route('auth.feed.index', ['club_id' => $club->id], false),
            'files' => route('auth.files.index', ['club_id' => $club->id], false),
            default => route('auth.club-cockpit.index', [], false),
        };
    }

    /**
     * @param  array<int, int>  $clubIds
     * @return Collection<int, int>
     */
    private function counts(string $table, array $clubIds, ?callable $scope = null): Collection
    {
        $query = DB::table($table)->whereIn('club_id', $clubIds);
        if ($scope) {
            $scope($query);
        }

        return $query
            ->selectRaw('club_id, COUNT(*) as aggregate')
            ->groupBy('club_id')
            ->pluck('aggregate', 'club_id');
    }
}
