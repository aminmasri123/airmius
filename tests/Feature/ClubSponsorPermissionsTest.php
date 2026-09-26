<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubSponsorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sponsor_edit_and_delete_permissions_are_independent(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $deleter = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $editor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_EDIT => true],
            ],
            $deleter->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
                'permission_overrides' => [ClubPermissions::SPONSORS_DELETE => true],
            ],
        ]);
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'club'],
            [
                'target_actor' => 'verein', 'name' => 'Club',
                'monthly_price_cents' => 2990, 'yearly_price_cents' => 29900,
                'currency' => 'EUR', 'features' => [], 'sort_order' => 1,
                'is_public' => true, 'is_active' => true,
            ],
        );
        $club->currentSubscription()->updateOrCreate([], [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
        ]);

        $this->actingAs($editor)
            ->post(route('auth.clubs.sponsors.store', $club), ['name' => 'Lokaler Partner'])
            ->assertRedirect();

        $sponsor = Sponsor::query()->where('club_id', $club->id)->sole();
        $this->actingAs($editor)
            ->put(route('auth.clubs.sponsors.update', [$club, $sponsor]), [
                'name' => 'Neuer Partnername',
            ])
            ->assertRedirect();
        $this->actingAs($editor)
            ->deleteJson(route('auth.clubs.sponsors.destroy', [$club, $sponsor]))
            ->assertForbidden();

        $this->actingAs($deleter)
            ->postJson(route('auth.clubs.sponsors.store', $club), ['name' => 'Nicht erlaubt'])
            ->assertForbidden();
        $this->actingAs($deleter)
            ->delete(route('auth.clubs.sponsors.destroy', [$club, $sponsor]))
            ->assertRedirect();

        $this->assertDatabaseMissing('sponsors', ['id' => $sponsor->id]);
    }

    public function test_legacy_content_manager_keeps_both_sponsor_actions_unless_explicitly_denied(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::SPONSORS_DELETE => false],
        ]);

        $effective = ClubPermissions::effectiveFor($club, $manager);

        $this->assertTrue($effective[ClubPermissions::SPONSORS_EDIT]);
        $this->assertFalse($effective[ClubPermissions::SPONSORS_DELETE]);
    }
}
