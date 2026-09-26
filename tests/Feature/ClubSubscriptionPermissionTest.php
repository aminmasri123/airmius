<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubSubscriptionPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_view_and_edit_rights_are_independent_and_used_by_the_api(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $editor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach([
            $viewer->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ],
            $editor->id => [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ],
        ]);
        $this->assign($club, $owner, $viewer, 'subscription_viewer', [ClubPermissions::SUBSCRIPTIONS_VIEW]);
        $this->assign($club, $owner, $editor, 'subscription_editor', [ClubPermissions::SUBSCRIPTIONS_EDIT]);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'club-rights',
            'target_actor' => 'verein',
            'name' => 'Vereinsplan',
            'monthly_price_cents' => 1200,
            'yearly_price_cents' => 12000,
            'currency' => 'EUR',
            'storage_gb' => 5,
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'sort_order' => 1,
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'current_period_ends_at' => now()->addMonth(),
            ],
        );
        Setting::setValue('billing_iban', 'DE89370400440532013000');

        Sanctum::actingAs($viewer);
        $this->get(route('guest.pricing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Pricing')
                ->has('checkoutClubs', 0));
        $this->withHeaders([
            'Referer' => route('guest.pricing'),
            'X-Checkout-Mode' => 'json',
        ])->postJson(route('subscription-checkout.store', $plan), [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'club_id' => $club->id,
            'accepted_terms' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_id');
        $this->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonCount(1, 'data.club_subscriptions')
            ->assertJsonPath('data.club_subscriptions.0.club_id', $club->id)
            ->assertJsonPath('data.club_subscriptions.0.can_edit', false)
            ->assertJsonPath('data.club_subscriptions.0.club.can_view_subscriptions', true)
            ->assertJsonPath('data.club_subscriptions.0.club.can_edit_subscriptions', false);
        $this->postJson("/api/v1/clubs/{$club->id}/subscriptions/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])->assertForbidden();
        $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'club_id' => $club->id,
            'accepted_terms' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('club_id');
        $this->assertSame('active', $subscription->fresh()->status);

        Sanctum::actingAs($editor);
        $this->get(route('guest.pricing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guest/Pricing')
                ->has('checkoutClubs', 1)
                ->where('checkoutClubs.0.id', $club->id));
        $this->withHeaders([
            'Referer' => route('guest.pricing'),
            'X-Checkout-Mode' => 'json',
        ])->postJson(route('subscription-checkout.store', $plan), [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'club_id' => $club->id,
            'accepted_terms' => true,
        ])->assertOk()->assertJsonStructure(['redirect_url']);
        $this->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.club_subscriptions.0.can_edit', true)
            ->assertJsonPath('data.club_subscriptions.0.club.can_view_subscriptions', true)
            ->assertJsonPath('data.club_subscriptions.0.club.can_edit_subscriptions', true);
        $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'club_id' => $club->id,
            'accepted_terms' => true,
        ])->assertCreated()->assertJsonPath('data.club_id', $club->id);
        $this->postJson("/api/v1/clubs/{$club->id}/subscriptions/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])->assertOk()->assertJsonPath('data.status', 'cancels_at_period_end');
    }

    public function test_explicit_subscription_denial_overrides_legacy_billing_role(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($manager->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::SUBSCRIPTIONS_EDIT => false],
        ]);

        $this->assertTrue(ClubPermissions::allows($club, $manager, ClubPermissions::SUBSCRIPTIONS_VIEW));
        $this->assertFalse(ClubPermissions::allows($club, $manager, ClubPermissions::SUBSCRIPTIONS_EDIT));
    }

    private function assign(Club $club, User $owner, User $user, string $key, array $permissions): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $user->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
