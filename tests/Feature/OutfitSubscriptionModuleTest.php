<?php

namespace Tests\Feature;

use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OutfitSubscriptionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_outfit_dashboard_without_special_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.outfit-subscriptions.index'))
            ->assertOk();
    }

    public function test_user_can_update_style_profile_and_subscribe(): void
    {
        $user = User::factory()->create();

        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Runner Box',
            'slug' => 'runner-box',
            'description' => 'Monatliche Lauf-Outfits.',
            'monthly_price_cents' => 2990,
            'sponsor_discount_cents' => 500,
            'currency' => 'EUR',
            'sizes' => ['S', 'M', 'L'],
            'sports' => ['Laufen'],
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('auth.outfit-subscriptions.profile.update'), [
                'sport_focus' => 'Laufen',
                'sizes' => ['M'],
                'fit_preference' => 'regular',
                'colors' => ['Schwarz'],
                'excluded_colors' => ['Gelb'],
                'brand_style' => 'minimal',
                'notes' => 'Atmungsaktive Materialien.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_style_profiles', [
            'user_id' => $user->id,
            'sport_focus' => 'Laufen',
        ]);

        $this->actingAs($user)
            ->post(route('auth.outfit-subscriptions.store', $plan))
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscriptions', [
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'monthly_price_cents' => 2490,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('outfit_deliveries', [
            'status' => 'planned',
        ]);
    }

    public function test_admin_can_manage_outfit_plans_with_sponsor(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $sponsor = Sponsor::query()->create(['name' => 'Airmius Sponsor']);

        $this->actingAs($admin)
            ->post(route('admin.outfit-subscription-plans.store'), [
                'sponsor_id' => $sponsor->id,
                'name' => 'Sponsored Fitness Box',
                'description' => 'Verguenstigte Fitness-Outfits mit Sponsor-Branding.',
                'monthly_price_cents' => 3990,
                'sponsor_discount_cents' => 1000,
                'currency' => 'EUR',
                'target_gender' => 'unisex',
                'sizes' => ['S', 'M', 'L'],
                'sports' => ['Fitness'],
                'items_per_box' => 4,
                'branding_type' => 'sponsor_logo',
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscription_plans', [
            'sponsor_id' => $sponsor->id,
            'name' => 'Sponsored Fitness Box',
            'sponsor_discount_cents' => 1000,
            'branding_type' => 'sponsor_logo',
        ]);
    }

    public function test_plan_with_subscription_is_deactivated_instead_of_deleted(): void
    {
        Permission::firstOrCreate(['name' => 'outfit-subscriptions.manage']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Team Box',
            'slug' => 'team-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'club_logo',
            'is_public' => true,
            'is_active' => true,
        ]);
        OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outfit-subscription-plans.destroy', $plan))
            ->assertRedirect();

        $this->assertDatabaseHas('outfit_subscription_plans', [
            'id' => $plan->id,
            'is_active' => false,
            'is_public' => false,
        ]);
    }

    public function test_scheduler_command_plans_due_outfit_deliveries(): void
    {
        $user = User::factory()->create();
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Monthly Box',
            'slug' => 'monthly-box',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'items_per_box' => 2,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);
        OutfitSubscription::query()->create([
            'user_id' => $user->id,
            'outfit_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'monthly_price_cents' => 1990,
            'currency' => 'EUR',
            'next_delivery_at' => now()->subDay(),
        ]);

        $this->artisan('airmius:prepare-outfit-deliveries')
            ->assertSuccessful();

        $this->assertDatabaseHas('outfit_deliveries', [
            'status' => 'planned',
            'notes' => 'Automatisch geplante Monatslieferung.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'outfit.delivery.planned',
        ]);
    }
}
