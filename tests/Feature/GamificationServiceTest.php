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

    public function test_it_is_idempotent_for_the_same_source_event(): void
    {
        $user = User::factory()->create();
        $source = Club::factory()->create(['owner_id' => $user->id]);

        GamificationRule::create([
            'key' => 'content_created',
            'actor_type' => 'sportler',
            'category' => 'content',
            'label' => 'Content',
            'xp_amount' => 10,
            'trust_delta' => 1,
        ]);

        $service = app(GamificationService::class);
        $first = $service->grant($user, 'content_created', $source);
        $second = $service->grant($user, 'content_created', $source);

        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(1, GamificationXpEvent::where('reason', 'content_created')->count());
        $this->assertSame(1, GamificationXpEvent::where('reason', 'daily_meaningful_activity')->count());
        $this->assertNotNull($first?->idempotency_key);
    }

    public function test_daily_limits_are_scoped_to_actor_type_and_owner(): void
    {
        $actor = User::factory()->create();
        $firstClub = Club::factory()->create(['owner_id' => $actor->id]);
        $secondClub = Club::factory()->create(['owner_id' => $actor->id]);

        GamificationRule::create([
            'key' => 'event_created',
            'actor_type' => 'verein',
            'category' => 'events',
            'label' => 'Event',
            'xp_amount' => 12,
            'daily_limit' => 1,
            'trust_delta' => 0,
        ]);

        $service = app(GamificationService::class);

        $first = $service->grantToClub($actor, $firstClub, 'event_created');
        $second = $service->grantToClub($actor, $secondClub, 'event_created');
        $third = $service->grantToClub($actor, $firstClub, 'event_created');

        $this->assertSame(12, $first?->amount);
        $this->assertSame(12, $second?->amount);
        $this->assertSame(0, $third?->amount);
        $this->assertTrue((bool) $third?->limited_by_daily_cap);
        $this->assertSame(12, $service->summaryFor($firstClub, 'verein')['xp']);
        $this->assertSame(12, $service->summaryFor($secondClub, 'verein')['xp']);
    }

    public function test_summary_exposes_progress_health_and_today_metrics(): void
    {
        $user = User::factory()->create();

        GamificationRule::create([
            'key' => 'content_created',
            'actor_type' => 'sportler',
            'category' => 'content',
            'label' => 'Content',
            'xp_amount' => 10,
            'daily_limit' => 3,
            'trust_delta' => 1,
        ]);

        $service = app(GamificationService::class);
        $service->grant($user, 'content_created');

        $summary = $service->summaryFor($user, 'sportler');

        $this->assertSame(12, $summary['xp']);
        $this->assertSame(12, $summary['earned_today']);
        $this->assertSame(238, $summary['xp_to_next_level']);
        $this->assertSame('Aufbauphase', $summary['health_label']);
    }

    public function test_negative_xp_never_creates_negative_level_progress(): void
    {
        $user = User::factory()->create();

        GamificationRule::create([
            'key' => 'spam_or_abuse',
            'actor_type' => 'sportler',
            'category' => 'penalties',
            'label' => 'Spam',
            'xp_amount' => -30,
            'trust_delta' => -8,
            'is_penalty' => true,
        ]);

        $service = app(GamificationService::class);
        $service->penalize($user, 'spam_or_abuse');

        $summary = $service->summaryFor($user->refresh());

        $this->assertSame(-30, $summary['xp']);
        $this->assertSame(1, $summary['level']);
        $this->assertSame(0, $summary['progress']);
        $this->assertSame(92, $summary['trust_score']);
    }
}
