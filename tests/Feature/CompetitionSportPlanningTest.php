<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionClass;
use App\Models\CompetitionOfficialAssignment;
use App\Models\CompetitionRosterEntry;
use App\Models\CompetitionTournamentCorrection;
use App\Models\CompetitionTournamentMatch;
use App\Models\CompetitionTournamentStanding;
use App\Models\CompetitionVenue;
use App\Models\Event;
use App\Models\User;
use App\Services\CompetitionSportPlanningService;
use App\Support\ClubPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompetitionSportPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sport_planning_creates_lineups_start_lists_relays_substitutions_playing_time_and_officials(): void
    {
        [$club, $manager, $competition, $class, $venue, $event, $starter, $substitute] = $this->fixture();
        $service = app(CompetitionSportPlanningService::class);

        $lineup = $service->createLineup($competition, $manager, [
            'competition_class_id' => $class->id,
            'event_id' => $event->id,
            'sport_type' => 'football',
            'name' => 'Finale Startelf',
            'status' => 'published',
        ], [
            ['competition_roster_entry_id' => $starter->id, 'role' => 'starter', 'position' => 'GK'],
            ['competition_roster_entry_id' => $substitute->id, 'role' => 'substitute', 'position' => 'FW'],
        ]);
        $startList = $service->createStartList($competition, $manager, [[
            'competition_class_id' => $class->id,
            'competition_roster_entry_id' => $starter->id,
            'event_id' => $event->id,
            'heat' => 'A',
            'lane' => '4',
            'start_number' => '12',
        ]]);
        $relay = $service->createRelayTeam($competition, $manager, [
            'competition_class_id' => $class->id,
            'event_id' => $event->id,
            'name' => '4x100 A',
            'discipline' => '4x100m',
        ], [
            ['competition_roster_entry_id' => $starter->id, 'leg_number' => 1],
            ['competition_roster_entry_id' => $substitute->id, 'leg_number' => 2],
        ]);
        $substitution = $service->recordSubstitution($competition, $manager, [
            'event_id' => $event->id,
            'out_roster_entry_id' => $starter->id,
            'in_roster_entry_id' => $substitute->id,
            'minute' => 63,
            'reason' => 'tactical',
        ]);
        $playingTime = $service->recordPlayingTime($competition, $manager, [
            'event_id' => $event->id,
            'competition_roster_entry_id' => $starter->id,
            'minutes_played' => 63,
            'segments' => [['from' => 0, 'to' => 63]],
        ]);
        $official = $service->assignOfficial($competition, $manager, [
            'event_id' => $event->id,
            'competition_venue_id' => $venue->id,
            'display_name' => 'Alex Referee',
            'assignment_type' => 'referee',
            'role' => 'main_referee',
        ]);

        $this->assertSame($club->id, $lineup->club_id);
        $this->assertCount(2, $lineup->entries);
        $this->assertSame('12', $startList[0]->start_number);
        $this->assertCount(2, $relay->legs);
        $this->assertSame(63, $substitution->minute);
        $this->assertSame(63, $playingTime->minutes_played);
        $this->assertSame('main_referee', $official->role);
        $this->assertSame(1, CompetitionOfficialAssignment::query()->where('competition_id', $competition->id)->count());
    }

    public function test_roles_deadlines_and_club_boundaries_are_enforced_for_sport_planning(): void
    {
        [$club, $manager, $competition, $class, $venue, $event, $starter] = $this->fixture();
        $otherClub = Club::factory()->create(['owner_id' => User::factory()]);
        $outsider = User::factory()->create();
        $foreignCompetition = Competition::query()->create([
            'club_id' => $otherClub->id,
            'name' => 'Foreign cup',
            'status' => 'open',
        ]);
        $foreignRoster = CompetitionRosterEntry::query()->create([
            'competition_id' => $foreignCompetition->id,
            'display_name' => 'Foreign starter',
        ]);
        $service = app(CompetitionSportPlanningService::class);

        try {
            $service->createLineup($competition, $outsider, ['name' => 'Blocked']);
            $this->fail('Expected outsider authorization to fail.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        try {
            $service->createLineup($competition, $manager, ['name' => 'Invalid'], [
                ['competition_roster_entry_id' => $foreignRoster->id],
            ]);
            $this->fail('Expected cross-club roster validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('competition_roster_entry_id', $exception->errors());
        }

        $competition->forceFill([
            'metadata' => ['lineup_deadline_at' => now()->subMinute()->toISOString()],
        ])->save();

        try {
            $service->createLineup($competition->fresh(), $manager, ['name' => 'Too late'], [
                ['competition_roster_entry_id' => $starter->id],
            ]);
            $this->fail('Expected expired lineup deadline to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lineup_deadline_at', $exception->errors());
        }

        $this->assertSame($club->id, $class->club_id);
        $this->assertSame($club->id, $venue->club_id);
        $this->assertSame($club->id, $event->club_id);
    }

    public function test_tournament_generation_is_deterministic_and_builds_groups_fixtures_and_knockout_slots(): void
    {
        [$club, $manager, $competition, $class, , , $starter, $substitute] = $this->fixture();
        $third = CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'display_name' => 'Third',
            'metadata' => ['seed' => 3],
        ]);
        $fourth = CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'display_name' => 'Fourth',
            'metadata' => ['seed' => 4],
        ]);
        $starter->forceFill(['metadata' => ['seed' => 1]])->save();
        $substitute->forceFill(['metadata' => ['seed' => 2]])->save();
        $service = app(CompetitionSportPlanningService::class);

        $first = $service->generateTournament($competition, $manager, [
            $fourth->id,
            $substitute->id,
            $starter->id,
            $third->id,
        ], ['competition_class_id' => $class->id, 'group_count' => 2]);
        $signature = $first['matches']->map(fn (CompetitionTournamentMatch $match) => [
            $match->phase,
            $match->round_number,
            $match->home_label,
            $match->away_label,
        ])->all();

        $second = $service->generateTournament($competition->fresh(), $manager, [
            $third->id,
            $starter->id,
            $fourth->id,
            $substitute->id,
        ], ['competition_class_id' => $class->id, 'group_count' => 2]);

        $this->assertSame($signature, $second['matches']->map(fn (CompetitionTournamentMatch $match) => [
            $match->phase,
            $match->round_number,
            $match->home_label,
            $match->away_label,
        ])->all());
        $this->assertCount(2, $second['groups']);
        $this->assertSame(2, CompetitionTournamentMatch::query()->where('phase', 'group')->count());
        $this->assertSame(2, CompetitionTournamentMatch::query()->where('phase', 'knockout')->count());
        $this->assertSame(2, CompetitionTournamentCorrection::query()->where('correction_type', 'generate_tournament')->count());
        $this->assertSame($club->id, $second['groups'][0]->club_id);
    }

    public function test_match_corrections_are_historized_recalculate_standings_and_detect_conflicts(): void
    {
        [, $manager, $competition, $class, , , $starter, $substitute] = $this->fixture();
        $third = CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'display_name' => 'Third',
        ]);
        $service = app(CompetitionSportPlanningService::class);
        $service->generateTournament($competition, $manager, [$starter->id, $substitute->id, $third->id], [
            'competition_class_id' => $class->id,
            'group_count' => 1,
        ]);
        $match = CompetitionTournamentMatch::query()->where('phase', 'group')->orderBy('match_number')->firstOrFail();

        $correction = $service->applyMatchCorrection($competition, $manager, $match, [
            'home_score' => 2,
            'away_score' => 1,
            'status' => 'completed',
            'scheduled_at' => now()->addDay()->startOfHour(),
        ], 'Ergebnis vom Schiedsgericht korrigiert.');

        $this->assertSame('match_update', $correction->correction_type);
        $this->assertSame([2, 1], [$correction->after['home_score'], $correction->after['away_score']]);
        $leader = CompetitionTournamentStanding::query()->orderBy('rank')->firstOrFail();
        $this->assertSame(3, $leader->points);
        $this->assertSame(1, $leader->played);

        $conflicting = CompetitionTournamentMatch::query()
            ->where('phase', 'group')
            ->whereKeyNot($match->id)
            ->where(function ($query) use ($match): void {
                $query->where('home_roster_entry_id', $match->home_roster_entry_id)
                    ->orWhere('away_roster_entry_id', $match->home_roster_entry_id);
            })
            ->firstOrFail();

        $this->expectException(ValidationException::class);
        $service->applyMatchCorrection($competition, $manager, $conflicting, [
            'scheduled_at' => $match->fresh()->scheduled_at,
        ]);
    }

    private function fixture(): array
    {
        $club = Club::factory()->create(['owner_id' => User::factory()]);
        $manager = User::factory()->create();
        $athlete = User::factory()->create();
        $substituteUser = User::factory()->create();
        $club->users()->attach($manager->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::EVENTS_EDIT => true],
        ]);
        $club->users()->attach($athlete->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $club->users()->attach($substituteUser->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $competition = Competition::query()->create([
            'club_id' => $club->id,
            'name' => 'Cup',
            'status' => 'open',
            'registration_deadline_at' => now()->addDay(),
        ]);
        $class = CompetitionClass::query()->create(['competition_id' => $competition->id, 'name' => 'Senior']);
        $venue = CompetitionVenue::query()->create(['competition_id' => $competition->id, 'name' => 'Arena']);
        $event = Event::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'competition_venue_id' => $venue->id,
            'user_id' => $manager->id,
            'title' => 'Finale',
            'type' => 'match',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => now()->addDays(2),
        ]);
        $starter = CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'user_id' => $athlete->id,
            'display_name' => 'Starter',
        ]);
        $substitute = CompetitionRosterEntry::query()->create([
            'competition_id' => $competition->id,
            'competition_class_id' => $class->id,
            'user_id' => $substituteUser->id,
            'display_name' => 'Substitute',
        ]);

        return [$club, $manager, $competition->fresh(), $class, $venue, $event, $starter, $substitute];
    }
}
