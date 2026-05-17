<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\PlanFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_clubs_have_three_member_invitations_per_day(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);

        TeamInvitation::query()->create([
            'team_id' => $team->id,
            'inviter_id' => $owner->id,
            'email' => 'one@example.com',
            'token' => 'token-one',
            'status' => 'pending',
            'invited_at' => now(),
        ]);

        ClubExternalMember::query()->create([
            'club_id' => $club->id,
            'created_by' => $owner->id,
            'email' => 'two@example.com',
            'invitation_status' => 'pending',
            'invitation_token' => 'token-two',
            'invited_at' => now(),
        ]);

        $service = app(PlanFeatureService::class);

        $this->assertSame(3, $service->memberInvitationDailyLimit($club));
        $this->assertSame(2, $service->memberInvitationUsageToday($club));
        $this->assertSame(1, $service->memberInvitationRemainingToday($club));
    }

    public function test_paid_clubs_have_no_daily_member_invitation_limit(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $starter->id,
                'status' => 'active',
            ],
        );

        $service = app(PlanFeatureService::class);

        $this->assertNull($service->memberInvitationDailyLimit($club->fresh('currentSubscription.plan')));
        $service->ensureCanSendMemberInvitations($club->fresh('currentSubscription.plan'), 50);
        $this->assertTrue(true);
    }

    public function test_user_storage_uses_latest_active_subscription_per_actor(): void
    {
        $user = User::factory()->create();
        $pro = SubscriptionPlan::query()->where('slug', 'sportler-pro')->firstOrFail();
        $free = SubscriptionPlan::query()->where('slug', 'sportler-free')->firstOrFail();

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $pro->id,
            'status' => 'active',
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $free->id,
            'status' => 'active',
        ]);

        $summary = app(PlanFeatureService::class)->userStorageSummary($user);

        $this->assertSame('Sportler Free', $summary['plan_name']);
        $this->assertSame(1, $summary['limit_gb']);
    }
}
