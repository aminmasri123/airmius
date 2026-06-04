<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use App\Models\Team;
use App\Models\TrainingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AthleteMotivationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_motivation_endpoint_returns_prs_goals_segments_challenges_and_rankings(): void
    {
        $user = User::factory()->create(['name' => 'Amina Runner']);
        $rival = User::factory()->create(['name' => 'Rival Pace']);
        $club = Club::factory()->create(['owner_id' => User::factory()->create()->id, 'name' => 'Airmius Club']);
        $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'Fast Team']);

        $club->users()->syncWithoutDetaching([
            $user->id => ['role' => 'member', 'membership_status' => 'active'],
            $rival->id => ['role' => 'member', 'membership_status' => 'active'],
        ]);
        $team->users()->syncWithoutDetaching([
            $user->id => ['role' => 'player'],
            $rival->id => ['role' => 'player'],
        ]);

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'team_id' => $team->id,
            'sport_type' => 'running',
            'title' => 'Tempo Run',
            'status' => 'completed',
            'performed_at' => now(),
            'duration_minutes' => 35,
            'distance_meters' => 6000,
        ]);

        TrainingLog::query()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'team_id' => $team->id,
            'sport_type' => 'running',
            'title' => 'Longer Run',
            'status' => 'completed',
            'performed_at' => now()->subDays(2),
            'duration_minutes' => 50,
            'distance_meters' => 8000,
        ]);

        TrainingLog::query()->create([
            'user_id' => $rival->id,
            'created_by' => $rival->id,
            'team_id' => $team->id,
            'sport_type' => 'running',
            'title' => 'Easy Run',
            'status' => 'completed',
            'performed_at' => now(),
            'duration_minutes' => 30,
            'distance_meters' => 5000,
        ]);

        $route = SportRoute::query()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'Park Loop Segment',
            'sport_type' => 'running',
            'visibility' => 'team',
            'status' => 'completed',
            'distance_meters' => 10000,
            'estimated_duration_seconds' => 3600,
            'elevation_gain_meters' => 120,
            'waypoints' => [
                ['lat' => 51.1, 'lng' => 6.1],
                ['lat' => 51.2, 'lng' => 6.2],
            ],
        ]);

        SportRouteTrack::query()->create([
            'user_id' => $user->id,
            'sport_route_id' => $route->id,
            'team_id' => $team->id,
            'title' => 'Amina Park Loop',
            'sport_type' => 'running',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addSeconds(3000),
            'distance_meters' => 10000,
            'duration_seconds' => 3000,
            'elevation_gain_meters' => 120,
            'average_speed_mps' => 3.333,
        ]);

        SportRouteTrack::query()->create([
            'user_id' => $rival->id,
            'sport_route_id' => $route->id,
            'team_id' => $team->id,
            'title' => 'Rival Park Loop',
            'sport_type' => 'running',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addSeconds(2700),
            'distance_meters' => 10000,
            'duration_seconds' => 2700,
            'elevation_gain_meters' => 110,
            'average_speed_mps' => 3.704,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/maturity/motivation')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.summary.weekly_distance_meters', 24000)
            ->assertJsonPath('data.goals.weekly_distance.completed', true)
            ->assertJsonPath('data.goals.weekly_sessions.current', 3)
            ->assertJsonPath('data.personal_records.0.key', 'longest_activity')
            ->assertJsonPath('data.personal_records.0.value', 10000)
            ->assertJsonPath('data.segments.0.route_id', $route->id)
            ->assertJsonPath('data.segments.0.challenge_type', 'route_segment')
            ->assertJsonPath('data.segments.0.leaderboard_metric', 'fastest_time')
            ->assertJsonPath('data.segments.0.my_rank', 2)
            ->assertJsonPath('data.segments.0.leaderboard.0.name', 'Rival Pace')
            ->assertJsonPath('data.challenges.weekly_goal_challenges.0.key', 'complete_3_sessions')
            ->assertJsonPath('data.rankings.team_weekly_distance.my_rank', 1)
            ->assertJsonPath('data.rankings.team_weekly_distance.leaderboard.0.name', 'Amina Runner')
            ->assertJsonPath('data.rankings.club_weekly_distance.my_rank', 1)
            ->assertJsonPath('data.clubs.active_clubs', 1)
            ->assertJsonPath('data.clubs.items.0.name', 'Airmius Club')
            ->assertJsonPath('data.contract.mobile_sections.3', 'segments');
    }
}
