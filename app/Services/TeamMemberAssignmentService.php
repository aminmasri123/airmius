<?php

namespace App\Services;

use App\Models\ClubTrainingGroup;
use App\Models\ClubYearPeriod;
use App\Models\Team;
use App\Models\TeamMemberAssignment;
use App\Support\TeamRoles;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TeamMemberAssignmentService
{
    public function create(Team $team, array $data): TeamMemberAssignment
    {
        $team->loadMissing('club');

        $payload = $this->normalize($team, $data);
        $this->assertClubBoundaries($team, $payload);
        $this->assertRole($payload['role']);
        $this->assertStatus($payload['status']);
        $this->assertValidDateRange($payload);
        $this->assertNoDuplicateTeamRoleAssignment($payload);

        return TeamMemberAssignment::query()->create($payload);
    }

    private function normalize(Team $team, array $data): array
    {
        return [
            'club_id' => (int) $team->club_id,
            'team_id' => (int) $team->id,
            'user_id' => (int) Arr::get($data, 'user_id'),
            'sport_year_period_id' => Arr::get($data, 'sport_year_period_id', $team->sport_year_period_id),
            'club_training_group_id' => Arr::get($data, 'club_training_group_id'),
            'role' => trim((string) Arr::get($data, 'role', TeamRoles::PLAYER)),
            'position' => $this->nullableString(Arr::get($data, 'position')),
            'jersey_number' => $this->nullableString(Arr::get($data, 'jersey_number')),
            'status' => trim((string) Arr::get($data, 'status', TeamMemberAssignment::STATUS_ACTIVE)),
            'is_guest_participation' => (bool) Arr::get($data, 'is_guest_participation', false),
            'valid_from' => (string) Arr::get($data, 'valid_from'),
            'valid_until' => Arr::get($data, 'valid_until'),
            'metadata' => Arr::get($data, 'metadata'),
        ];
    }

    private function assertClubBoundaries(Team $team, array $payload): void
    {
        if ($payload['user_id'] <= 0) {
            $this->fail('user_id', 'A team member assignment needs a user.');
        }

        $isClubMember = $team->club->users()->whereKey($payload['user_id'])->exists();
        if (! $isClubMember && ! $payload['is_guest_participation']) {
            $this->fail('user_id', 'The assigned user must belong to the club unless the assignment is marked as guest participation.');
        }

        if ($payload['sport_year_period_id']) {
            $period = ClubYearPeriod::query()->find($payload['sport_year_period_id']);
            if (! $period || (int) $period->club_id !== (int) $team->club_id || $period->type !== 'sport') {
                $this->fail('sport_year_period_id', 'The sport year period must belong to the same club.');
            }
        }

        if ($payload['club_training_group_id']) {
            $group = ClubTrainingGroup::query()->find($payload['club_training_group_id']);
            if (! $group || (int) $group->club_id !== (int) $team->club_id) {
                $this->fail('club_training_group_id', 'The training group must belong to the same club.');
            }
        }
    }

    private function assertRole(string $role): void
    {
        if (! in_array($role, TeamRoles::TEAM_ASSIGNABLE_ROLES, true)) {
            $this->fail('role', 'The team role is not assignable.');
        }
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, TeamMemberAssignment::STATUSES, true)) {
            $this->fail('status', 'The team member status is invalid.');
        }
    }

    private function assertValidDateRange(array $payload): void
    {
        if ($payload['valid_from'] === '') {
            $this->fail('valid_from', 'A validity start date is required.');
        }

        if ($payload['valid_until'] && $payload['valid_until'] < $payload['valid_from']) {
            $this->fail('valid_until', 'The validity end date must be after the start date.');
        }
    }

    private function assertNoDuplicateTeamRoleAssignment(array $payload): void
    {
        $exists = TeamMemberAssignment::query()
            ->where('team_id', $payload['team_id'])
            ->where('user_id', $payload['user_id'])
            ->where('role', $payload['role'])
            ->where('sport_year_period_id', $payload['sport_year_period_id'])
            ->overlapping($payload['valid_from'], $payload['valid_until'])
            ->exists();

        if ($exists) {
            $this->fail('user_id', 'The user already has this team role in the selected season and validity window.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
