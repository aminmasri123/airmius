<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionRosterEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionGameLineupTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_lineup_persists_numbers_board_positions_and_team_boundaries(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id, 'sport_type' => 'fussball']);
        $otherTeam = Team::factory()->create(['club_id' => $club->id, 'sport_type' => 'fussball']);
        $athlete = User::factory()->create();
        $second = User::factory()->create();
        $outsider = User::factory()->create();
        $team->users()->attach([$athlete->id, $second->id], ['role' => 'player']);
        $otherTeam->users()->attach($outsider->id, ['role' => 'player']);
        $competition = Competition::create(['club_id' => $club->id, 'team_id' => $team->id, 'name' => 'League', 'status' => 'open']);
        $first = CompetitionRosterEntry::create(['competition_id' => $competition->id, 'user_id' => $athlete->id, 'display_name' => 'First']);
        $bench = CompetitionRosterEntry::create(['competition_id' => $competition->id, 'user_id' => $second->id, 'display_name' => 'Second']);
        $foreign = CompetitionRosterEntry::create(['competition_id' => $competition->id, 'user_id' => $outsider->id, 'display_name' => 'Other team']);
        Sanctum::actingAs($owner);

        $game = $this->postJson("/api/v1/competitions/{$competition->id}/games", ['opponent' => 'City', 'scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertCreated()->json('data.id');
        $url = "/api/v1/competitions/{$competition->id}/games/$game/lineup";
        $entries = [
            ['competition_roster_entry_id' => $first->id, 'role' => 'starter', 'shirt_number' => '09', 'board_x' => 45, 'board_y' => 70],
            ['competition_roster_entry_id' => $bench->id, 'role' => 'substitute', 'shirt_number' => '10'],
        ];
        $this->putJson($url, ['entries' => $entries])->assertOk()->assertJsonPath('data.entries.0.shirt_number', '9');
        $this->putJson("/api/v1/competitions/{$competition->id}/games/$game", ['opponent' => 'City United', 'scheduled_at' => null])
            ->assertOk()->assertJsonPath('data.opponent', 'City United');
        $this->getJson("/api/v1/competitions/{$competition->id}")->assertOk()
            ->assertJsonPath('data.game_planning_enabled', true)
            ->assertJsonPath('data.games.0.opponent', 'City United')
            ->assertJsonPath('data.games.0.lineup.entries.0.board_x', 45)
            ->assertJsonPath('data.games.0.lineup.entries.1.shirt_number', '10');
        $this->putJson($url, ['entries' => [
            $entries[0],
            ['competition_roster_entry_id' => $bench->id, 'role' => 'substitute', 'shirt_number' => '9'],
        ]])->assertUnprocessable();
        $this->putJson($url, ['entries' => [[
            'competition_roster_entry_id' => $foreign->id, 'role' => 'starter', 'shirt_number' => '11', 'board_x' => 50, 'board_y' => 50,
        ]]])->assertUnprocessable();
        $nextGame = $this->postJson("/api/v1/competitions/{$competition->id}/games", ['opponent' => 'Town'])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/competitions/{$competition->id}/games/$nextGame/lineup", ['entries' => [$entries[0]]])
            ->assertOk();
        $this->assertDatabaseCount('competition_lineups', 2);
        $this->deleteJson("/api/v1/competitions/{$competition->id}")->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson($url, ['entries' => $entries])->assertForbidden();
        $this->getJson("/api/v1/competitions/{$competition->id}")->assertForbidden();
    }
}
