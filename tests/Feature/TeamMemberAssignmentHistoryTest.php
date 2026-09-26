<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubTrainingGroup;
use App\Models\ClubYearPeriod;
use App\Models\Team;
use App\Models\TeamMemberAssignment;
use App\Models\User;
use App\Services\TeamMemberAssignmentService;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamMemberAssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_jersey_status_guest_flag_and_role_are_historized_by_season(): void
    {
        [$club, $team, $member, $season] = $this->teamContext();

        $assignment = $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'role' => TeamRoles::PLAYER,
            'position' => 'Sturm',
            'jersey_number' => '9',
            'status' => TeamMemberAssignment::STATUS_ACTIVE,
            'is_guest_participation' => false,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('team_member_assignments', [
            'id' => $assignment->id,
            'club_id' => $club->id,
            'team_id' => $team->id,
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'role' => TeamRoles::PLAYER,
            'position' => 'Sturm',
            'jersey_number' => '9',
            'status' => TeamMemberAssignment::STATUS_ACTIVE,
            'is_guest_participation' => false,
        ]);
    }

    public function test_same_role_in_same_team_and_season_must_not_overlap(): void
    {
        [, $team, $member, $season] = $this->teamContext();

        $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'role' => TeamRoles::PLAYER,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'role' => TeamRoles::PLAYER,
            'valid_from' => '2026-12-01',
            'valid_until' => '2027-01-31',
        ]);
    }

    public function test_same_role_can_continue_after_previous_validity_window(): void
    {
        [, $team, $member, $season] = $this->teamContext();

        $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'role' => TeamRoles::PLAYER,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
        ]);

        $next = $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'role' => TeamRoles::PLAYER,
            'valid_from' => '2027-01-01',
            'valid_until' => '2027-07-31',
        ]);

        $this->assertSame('2027-01-01', $next->valid_from->toDateString());
    }

    public function test_parallel_training_group_assignments_are_allowed_with_distinct_roles(): void
    {
        [$club, $team, $member, $season] = $this->teamContext();
        $first = $this->trainingGroup($club, 'Athletik', $season);
        $second = $this->trainingGroup($club, 'Technik', $season);

        $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'club_training_group_id' => $first->id,
            'role' => TeamRoles::PLAYER,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-08-31',
        ]);

        $this->service()->create($team, [
            'user_id' => $member->id,
            'sport_year_period_id' => $season->id,
            'club_training_group_id' => $second->id,
            'role' => TeamRoles::COACH,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-08-31',
        ]);

        $this->assertDatabaseCount('team_member_assignments', 2);
    }

    public function test_season_and_training_group_must_stay_inside_team_club(): void
    {
        [, $team, $member] = $this->teamContext();
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()->create()->id]);
        $foreignSeason = $this->season($foreignClub, 'Fremde Saison');
        $foreignGroup = $this->trainingGroup($foreignClub, 'Fremde Gruppe', $foreignSeason);

        foreach ([
            ['sport_year_period_id' => $foreignSeason->id],
            ['club_training_group_id' => $foreignGroup->id],
        ] as $invalid) {
            try {
                $this->service()->create($team, [
                    'user_id' => $member->id,
                    'role' => TeamRoles::PLAYER,
                    'valid_from' => '2026-08-01',
                    ...$invalid,
                ]);
                $this->fail('Expected a club boundary validation error.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_non_club_member_requires_guest_participation_flag(): void
    {
        [, $team] = $this->teamContext();
        $guest = User::factory()->create();

        try {
            $this->service()->create($team, [
                'user_id' => $guest->id,
                'role' => TeamRoles::PLAYER,
                'valid_from' => '2026-08-01',
            ]);
            $this->fail('Expected non-club member validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user_id', $exception->errors());
        }

        $assignment = $this->service()->create($team, [
            'user_id' => $guest->id,
            'role' => TeamRoles::PLAYER,
            'is_guest_participation' => true,
            'valid_from' => '2026-08-01',
        ]);

        $this->assertTrue($assignment->is_guest_participation);
    }

    private function teamContext(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member']);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $season = $this->season($club, 'Saison 2026');

        return [$club, $team, $member, $season];
    }

    private function season(Club $club, string $name): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'sport',
            'name' => $name,
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-07-31',
        ]);
    }

    private function trainingGroup(Club $club, string $name, ClubYearPeriod $season): ClubTrainingGroup
    {
        return ClubTrainingGroup::query()->create([
            'club_id' => $club->id,
            'sport_year_period_id' => $season->id,
            'name' => $name,
            'is_public' => false,
        ]);
    }

    private function service(): TeamMemberAssignmentService
    {
        return app(TeamMemberAssignmentService::class);
    }
}
