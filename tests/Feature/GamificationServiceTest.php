<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Club;
use App\Models\GamificationXpEvent;
use App\Models\GamificationRule;
use App\Models\Team;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_user_xp_with_badges_and_daily_limits(): void
    {
        $user = User::factory()->create();

        GamificationRule::create([
            'key' => 'content_created',
            'actor_type' => 'sportler',
            'category' => 'content',
            'label' => 'Content',
            'xp_amount' => 10,
            'daily_limit' => 1,
            'trust_delta' => 1,
        ]);

        Badge::create([
            'key' => 'content_reason',
            'name' => 'Content Badge',
            'actor_type' => 'sportler',
            'trigger' => 'reason',
            'threshold' => 1,
            'meta' => ['reason' => 'content_created'],
        ]);

        $service = app(GamificationService::class);
        $first = $service->grant($user, 'content_created');
        $second = $service->grant($user, 'content_created');

        $this->assertSame(10, $first?->amount);
        $this->assertSame(0, $second?->amount);
        $this->assertTrue((bool) $second?->limited_by_daily_cap);
        $this->assertSame(1, UserBadge::where('user_id', $user->id)->count());
    }

    public function test_it_records_separate_xp_for_clubs_and_teams(): void
    {
        $actor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $actor->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        GamificationRule::create([
            'key' => 'event_created',
            'actor_type' => 'verein',
            'category' => 'events',
            'label' => 'Event',
            'xp_amount' => 12,
        ]);

        GamificationRule::create([
            'key' => 'event_created',
            'actor_type' => 'team',
            'category' => 'events',
            'label' => 'Team Event',
            'xp_amount' => 5,
        ]);

        $service = app(GamificationService::class);
        $service->grantToClub($actor, $club, 'event_created');
        $service->grantToTeam($actor, $team, 'event_created');

        $this->assertSame(12, $service->summaryFor($club, 'verein')['xp']);
        $this->assertSame(5, $service->summaryFor($team, 'team')['xp']);
        $this->assertSame(0, $service->summaryFor($actor, 'sportler')['xp']);
        $this->assertSame(2, GamificationXpEvent::count());
    }
}
