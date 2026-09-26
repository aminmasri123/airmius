<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubYearPeriod;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamSportYearPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_sport_year_scopes_only_season_planning_and_keeps_history_separate(): void
    {
        [$owner, $club, $team] = $this->team();
        $selected = $this->period($club, 'Saison 2026');
        $other = $this->period($club, 'Saison 2027', '2027-01-01', '2027-12-31');
        $team->update(['sport_year_period_id' => $selected->id]);

        $selectedEvent = $this->event($team, 'Ausgewähltes Training', now()->addDays(3), $selected->id);
        $this->event($team, 'Andere Saison', now()->addDays(4), $other->id);
        $unassigned = $this->event($team, 'Historischer Termin', now()->addDay(), null);

        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.teams.daily-life', $team))
            ->assertOk()
            ->assertJsonPath('data.today.next_event_id', $unassigned->id)
            ->assertJsonPath('data.season_planning.sport_year_period.id', $selected->id)
            ->assertJsonPath('data.season_planning.period_selection', 'selected')
            ->assertJsonPath('data.season_planning.events_total', 1)
            ->assertJsonPath('data.season_planning.upcoming_events', 1)
            ->assertJsonPath('data.season_planning.historically_unassigned_events', 1)
            ->assertJsonPath('data.season_planning.events_outside_selected_period', 2);

        $this->getJson(route('api.v1.teams.competitiveness.insights', $team))
            ->assertOk()
            ->assertJsonPath('data.events.next.id', $unassigned->id)
            ->assertJsonPath('data.team_organizer.season_plan.sport_year_period.id', $selected->id)
            ->assertJsonPath('data.team_organizer.season_plan.events_total', 1)
            ->assertJsonPath('data.team_organizer.season_plan.upcoming_events', 1)
            ->assertJsonPath('data.team_organizer.season_plan.next_focus', $selectedEvent->type)
            ->assertJsonPath('data.team_organizer.season_plan.historically_unassigned_events', 1)
            ->assertJsonPath('data.team_organizer.season_plan.events_outside_selected_period', 2);
    }

    public function test_unassigned_existing_team_keeps_previous_all_event_planning_behavior(): void
    {
        [$owner, $club, $team] = $this->team();
        $sport = $this->period($club, 'Saison 2026');
        $this->event($team, 'Zugeordnet', now()->addDays(2), $sport->id);
        $this->event($team, 'Bestand', now()->addDays(3), null);
        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.teams.daily-life', $team))
            ->assertOk()
            ->assertJsonPath('data.season_planning.period_selection', 'unassigned')
            ->assertJsonPath('data.season_planning.sport_year_period', null)
            ->assertJsonPath('data.season_planning.events_total', 2)
            ->assertJsonPath('data.season_planning.upcoming_events', 2)
            ->assertJsonPath('data.season_planning.historically_unassigned_events', 1)
            ->assertJsonPath('data.season_planning.events_outside_selected_period', 0);
    }

    public function test_team_accepts_only_a_sport_year_from_its_own_club_and_exposes_it(): void
    {
        [$owner, $club, $team] = $this->team();
        $sport = $this->period($club, 'Saison 2026');
        $business = ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'business',
            'name' => 'Geschäftsjahr',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $otherOwner = User::factory()->create();
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $foreign = $this->period($otherClub, 'Fremde Saison');
        Sanctum::actingAs($owner);

        foreach ([$business->id, $foreign->id] as $invalidId) {
            $this->putJson(route('api.v1.teams.update', $team), [
                'name' => $team->name,
                'sport_type' => $team->sport_type,
                'sport_year_period_id' => $invalidId,
            ])->assertUnprocessable()->assertJsonValidationErrors('sport_year_period_id');
        }

        $this->putJson(route('api.v1.teams.update', $team), [
            'name' => $team->name,
            'sport_type' => $team->sport_type,
            'sport_year_period_id' => $sport->id,
        ])->assertOk()
            ->assertJsonPath('data.sport_year_period_id', $sport->id)
            ->assertJsonPath('data.sport_year_period.name', 'Saison 2026');

        $this->assertSame($sport->id, $team->refresh()->sport_year_period_id);
        $this->deleteJson("/api/v1/clubs/{$club->id}/year-periods/{$sport->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year_period');
    }

    private function team(): array
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($owner->id, ['role' => TeamRoles::COACH]);

        return [$owner, $club, $team];
    }

    private function period(Club $club, string $name, string $starts = '2026-01-01', string $ends = '2026-12-31'): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'sport',
            'name' => $name,
            'starts_on' => $starts,
            'ends_on' => $ends,
        ]);
    }

    private function event(Team $team, string $title, mixed $startsAt, ?int $periodId): Event
    {
        $event = Event::query()->create([
            'club_id' => $team->club_id,
            'team_id' => $team->id,
            'title' => $title,
            'type' => 'training',
            'visibility' => 'organization',
            'status' => 'scheduled',
            'start_time' => $startsAt,
        ]);
        $event->forceFill(['sport_year_period_id' => $periodId])->saveQuietly();

        return $event->refresh();
    }
}
