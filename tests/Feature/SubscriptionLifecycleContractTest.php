<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\Notification as StoredNotification;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionResumed;
use App\Services\PlanFeatureService;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mobile_cancellation_uses_contract_terms_provider_sync_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-08-08 10:00:00');
        config()->set('services.stripe.secret', 'sk_test_lifecycle');
        Http::fake([
            'https://api.stripe.com/v1/subscriptions/sub_lifecycle' => Http::response(['status' => 'active']),
        ]);
        Notification::fake();

        $user = User::factory()->create(['language' => 'en']);
        $plan = $this->plan([
            'minimum_term_months' => 3,
            'cancellation_notice_days' => 20,
        ]);
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'stripe',
            'provider_subscription_id' => 'sub_lifecycle',
            'current_period_ends_at' => now()->addDays(10),
            'next_invoice_at' => now()->addDays(10),
        ]);
        $subscription->forceFill(['created_at' => now()->subMonth()])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscriptions/user/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancels_at_period_end')
            ->assertJsonPath('data.cancels_at', '2026-10-08T10:00:00.000000Z');

        $subscription->refresh();
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertNull($subscription->next_invoice_at);
        $this->assertSame('2026-10-08', $subscription->cancels_at?->toDateString());
        $this->assertNotNull($subscription->cancellation_email_sent_at);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/subscriptions/sub_lifecycle'
            && $request['cancel_at_period_end'] === 'true');
        Notification::assertSentToTimes($user, SubscriptionCancelled::class, 1);
        Notification::assertSentTo($user, SubscriptionCancelled::class, function (SubscriptionCancelled $notification) use ($user): bool {
            return $notification->toMail($user)->subject === 'Airmius subscription cancellation confirmed';
        });

        $stored = StoredNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.cancelled')
            ->sole();
        $this->assertSame('Subscription cancellation scheduled', $stored->data['title']);
        $this->assertSame('en', $stored->data['locale']);

        $this->postJson("/api/v1/subscriptions/user/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])->assertOk();

        Http::assertSentCount(1);
        Notification::assertSentToTimes($user, SubscriptionCancelled::class, 1);
        $this->assertSame(1, StoredNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.cancelled')
            ->count());
    }

    public function test_customer_resume_reinstates_stripe_without_granting_free_extra_months(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');
        config()->set('services.stripe.secret', 'sk_test_lifecycle');
        Http::fake([
            'https://api.stripe.com/v1/subscriptions/sub_reinstate' => Http::response(['status' => 'active']),
        ]);
        Notification::fake();

        $user = User::factory()->create(['language' => 'fr']);
        $plan = $this->plan();
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'cancels_at_period_end',
            'cancel_at_period_end' => true,
            'payment_provider' => 'stripe',
            'provider_subscription_id' => 'sub_reinstate',
            'current_period_ends_at' => now()->addDays(10),
            'next_invoice_at' => null,
            'cancels_at' => now()->addDays(10),
            'cancelled_at' => now()->subDay(),
            'grace_period_ends_at' => now()->addDays(2),
            'access_restricted_at' => now()->subHour(),
            'cancellation_email_sent_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscriptions/user/{$subscription->id}/renew", [
            'months' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_at_period_end', false);

        $subscription->refresh();
        $this->assertSame('2026-08-18', $subscription->current_period_ends_at?->toDateString());
        $this->assertTrue($subscription->current_period_ends_at?->equalTo($subscription->next_invoice_at));
        $this->assertNull($subscription->cancels_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->grace_period_ends_at);
        $this->assertNull($subscription->access_restricted_at);
        $this->assertNull($subscription->cancellation_email_sent_at);
        $this->assertNotNull($subscription->renewal_email_sent_at);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/subscriptions/sub_reinstate'
            && $request['cancel_at_period_end'] === 'false');
        Notification::assertSentTo($user, SubscriptionResumed::class, function (SubscriptionResumed $notification) use ($user): bool {
            return $notification->toMail($user)->subject === 'Ton abonnement Airmius va continuer';
        });

        $stored = StoredNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.resumed')
            ->sole();
        $this->assertSame('L’abonnement continue', $stored->data['title']);
        $this->assertSame('fr', $stored->data['locale']);

        $this->postJson("/api/v1/subscriptions/user/{$subscription->id}/renew", [
            'months' => 36,
        ])->assertUnprocessable()->assertJsonValidationErrors('subscription');
        $this->assertSame('2026-08-18', $subscription->fresh()->current_period_ends_at?->toDateString());
    }

    public function test_club_cancellation_notifies_authorized_recipients_and_respects_explicit_denials(): void
    {
        Carbon::setTestNow('2026-08-08 14:00:00');
        Notification::fake();

        $owner = User::factory()->create(['language' => 'en']);
        $manager = User::factory()->create(['language' => 'ar']);
        $viewer = User::factory()->create(['language' => 'de']);
        $deniedManager = User::factory()->create();
        $club = Club::factory()->create([
            'name' => 'Global Sports Club',
            'owner_id' => $owner->id,
        ]);
        $club->users()->attach($manager->id, ['role' => 'manager', 'membership_status' => 'active']);
        $club->users()->attach($viewer->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($deniedManager->id, [
            'role' => 'manager',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::SUBSCRIPTIONS_VIEW => false],
        ]);
        $viewerRole = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'subscription_viewer',
            'name' => 'Subscription viewer',
            'permissions' => [ClubPermissions::SUBSCRIPTIONS_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $viewerRole->id,
            'user_id' => $viewer->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
        $subscription = $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $this->plan(['target_actor' => 'verein'])->id,
                'status' => 'active',
                'payment_provider' => 'bank_transfer',
                'current_period_ends_at' => now()->addMonth(),
            ],
        );

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/subscriptions/{$subscription->id}/cancel", [
            'mode' => 'now',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $ownerNotice = StoredNotification::query()
            ->where('user_id', $owner->id)
            ->where('type', 'club.subscription.cancelled')
            ->sole();
        $managerNotice = StoredNotification::query()
            ->where('user_id', $manager->id)
            ->where('type', 'club.subscription.cancelled')
            ->sole();
        $viewerNotice = StoredNotification::query()
            ->where('user_id', $viewer->id)
            ->where('type', 'club.subscription.cancelled')
            ->sole();

        $this->assertSame('Subscription ended', $ownerNotice->data['title']);
        $this->assertSame('The subscription for Global Sports Club has ended.', $ownerNotice->data['body']);
        $this->assertSame('انتهى الاشتراك', $managerNotice->data['title']);
        $this->assertSame('انتهى اشتراك Global Sports Club.', $managerNotice->data['body']);
        $this->assertSame('Abo beendet', $viewerNotice->data['title']);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $deniedManager->id,
            'type' => 'club.subscription.cancelled',
        ]);
        Notification::assertSentToTimes($owner, SubscriptionCancelled::class, 1);
        Notification::assertSentToTimes($manager, SubscriptionCancelled::class, 1);
        Notification::assertSentToTimes($viewer, SubscriptionCancelled::class, 1);
        Notification::assertNotSentTo($deniedManager, SubscriptionCancelled::class);
    }

    public function test_lifecycle_command_finalizes_due_cancellation_once_with_localized_notice(): void
    {
        Carbon::setTestNow('2026-08-08 16:00:00');
        Notification::fake();

        $user = User::factory()->create(['language' => 'fr']);
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $this->plan()->id,
            'status' => 'cancels_at_period_end',
            'cancel_at_period_end' => true,
            'cancels_at' => now()->subDay(),
            'cancelled_at' => now()->subMonth(),
            'cancellation_email_sent_at' => now()->subMonth(),
        ]);

        $this->artisan('airmius:process-subscription-lifecycle', ['--dry-run' => false])
            ->assertSuccessful();

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertFalse($subscription->fresh()->cancel_at_period_end);

        $stored = StoredNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.ended')
            ->sole();
        $this->assertSame('Abonnement terminé', $stored->data['title']);
        $this->assertSame('fr', $stored->data['locale']);

        $this->artisan('airmius:process-subscription-lifecycle', ['--dry-run' => false])
            ->assertSuccessful();
        $this->assertSame(1, StoredNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.ended')
            ->count());
    }

    public function test_lifecycle_restriction_notifies_subscription_viewers_and_skips_denied_managers(): void
    {
        Carbon::setTestNow('2026-08-08 17:00:00');

        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $deniedManager = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($viewer->id, ['role' => 'member', 'membership_status' => 'active']);
        $club->users()->attach($deniedManager->id, [
            'role' => 'manager',
            'membership_status' => 'active',
            'permission_overrides' => [ClubPermissions::SUBSCRIPTIONS_VIEW => false],
        ]);
        $viewerRole = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'subscription_status_viewer',
            'name' => 'Subscription status viewer',
            'permissions' => [ClubPermissions::SUBSCRIPTIONS_VIEW],
            'is_active' => true,
        ]);
        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $viewerRole->id,
            'user_id' => $viewer->id,
            'scope_type' => 'club',
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
        $subscription = $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $this->plan(['target_actor' => 'verein'])->id,
                'status' => 'past_due',
                'grace_period_ends_at' => now()->subDay(),
            ],
        );

        $this->artisan('airmius:process-subscription-lifecycle')->assertSuccessful();

        $this->assertNotNull($subscription->fresh()->access_restricted_at);
        foreach ([$owner, $viewer] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'type' => 'subscription.restricted',
            ]);
        }
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $deniedManager->id,
            'type' => 'subscription.restricted',
        ]);
    }

    public function test_provider_cancelled_webhook_preserves_paid_access_until_contractual_end(): void
    {
        Carbon::setTestNow('2026-08-08 18:00:00');
        config([
            'services.stripe.webhook_secret' => 'whsec_lifecycle',
            'services.stripe.webhook_tolerance' => 300,
        ]);

        $user = User::factory()->create();
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $this->plan()->id,
            'status' => 'cancels_at_period_end',
            'cancel_at_period_end' => true,
            'payment_provider' => 'stripe',
            'provider_subscription_id' => 'sub_paid_access',
            'current_period_ends_at' => now()->addDays(10),
            'next_invoice_at' => null,
            'cancels_at' => now()->addDays(30),
            'cancelled_at' => now()->subMinute(),
        ]);
        $payload = json_encode([
            'id' => 'evt_paid_access',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_paid_access',
                    'status' => 'canceled',
                    'cancel_at_period_end' => false,
                    'current_period_end' => now()->addDays(10)->timestamp,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_lifecycle');

        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload,
        )->assertOk();

        $subscription->refresh();
        $this->assertSame('cancels_at_period_end', $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertSame('2026-09-07', $subscription->cancels_at?->toDateString());
        $this->assertNull($subscription->next_invoice_at);
    }

    public function test_user_entitlements_remain_until_cancellation_date_and_during_payment_grace_only(): void
    {
        Carbon::setTestNow('2026-08-08 19:00:00');
        $user = User::factory()->create();
        $plan = $this->plan(['storage_gb' => 25]);
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'cancels_at_period_end',
            'cancel_at_period_end' => true,
            'cancels_at' => now()->addDay(),
        ]);

        $this->assertTrue($subscription->grantsAccess());
        $this->assertTrue(UserSubscription::query()->grantingAccess()->whereKey($subscription)->exists());
        $this->assertSame(25, app(PlanFeatureService::class)->userStorageSummary($user)['limit_gb']);

        $subscription->forceFill([
            'status' => 'past_due',
            'cancel_at_period_end' => false,
            'cancels_at' => null,
            'grace_period_ends_at' => now()->addDay(),
        ])->save();

        $this->assertTrue($subscription->fresh()->grantsAccess());
        $this->assertSame(25, app(PlanFeatureService::class)->userStorageSummary($user)['limit_gb']);

        $subscription->forceFill(['access_restricted_at' => now()])->save();

        $this->assertFalse($subscription->fresh()->grantsAccess());
        $this->assertFalse(UserSubscription::query()->grantingAccess()->whereKey($subscription)->exists());
        $this->assertSame(1, app(PlanFeatureService::class)->userStorageSummary($user)['limit_gb']);
    }

    public function test_club_entitlements_fall_back_to_free_after_access_really_ends(): void
    {
        Carbon::setTestNow('2026-08-08 20:00:00');
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $paidPlan = $this->plan([
            'slug' => 'club-entitlement-pro',
            'target_actor' => 'verein',
            'storage_gb' => 40,
        ]);
        $subscription = $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $paidPlan->id,
                'status' => 'cancels_at_period_end',
                'cancel_at_period_end' => true,
                'cancels_at' => now()->addDay(),
            ],
        );

        $club->unsetRelation('currentSubscription');
        $this->assertSame($paidPlan->id, $club->subscriptionPlan()?->id);

        $subscription->forceFill([
            'status' => 'cancelled',
            'cancel_at_period_end' => false,
            'cancels_at' => now(),
        ])->save();
        $club->unsetRelation('currentSubscription');

        $this->assertFalse($subscription->fresh()->grantsAccess());
        $this->assertSame('free', $club->subscriptionPlan()?->slug);
    }

    public function test_only_club_billing_roles_can_change_the_club_subscription(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $academyManager = User::factory()->create();
        $financialController = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($academyManager->id, ['role' => 'academy_manager']);
        $club->users()->attach($financialController->id, ['role' => 'financial_controller']);
        $subscription = $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $this->plan(['target_actor' => 'verein'])->id,
                'status' => 'active',
                'current_period_ends_at' => now()->addMonth(),
            ],
        );

        Sanctum::actingAs($academyManager);
        $this->postJson("/api/v1/clubs/{$club->id}/subscriptions/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])->assertForbidden();
        $this->assertSame('active', $subscription->fresh()->status);

        Sanctum::actingAs($financialController);
        $this->postJson("/api/v1/clubs/{$club->id}/subscriptions/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])->assertOk()->assertJsonPath('data.status', 'cancels_at_period_end');
    }

    public function test_recurring_invoices_and_payment_restrictions_use_the_account_locale(): void
    {
        Carbon::setTestNow('2026-08-08 21:00:00');
        Notification::fake();
        $user = User::factory()->create(['language' => 'ar']);
        $plan = $this->plan(['name' => 'Global Pro']);
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'current_period_ends_at' => now()->subDay(),
            'next_invoice_at' => now()->subDay(),
        ]);
        $restricted = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $this->plan(['target_actor' => 'trainer'])->id,
            'status' => 'past_due',
            'grace_period_ends_at' => now()->subDay(),
        ]);

        $this->artisan('airmius:process-subscription-lifecycle')->assertSuccessful();

        $invoice = SubscriptionInvoice::query()
            ->where('subscription_id', '!=', $restricted->id)
            ->sole();
        $this->assertSame('اشتراك Airmius Global Pro (دفع شهري)', $invoice->description);
        $this->assertNotNull($restricted->fresh()->access_restricted_at);

        $notice = StoredNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.restricted')
            ->sole();
        $this->assertSame('تم تقييد الاشتراك', $notice->data['title']);
        $this->assertSame('ar', $notice->data['locale']);
    }

    private function plan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create(array_merge([
            'slug' => 'lifecycle-'.str()->uuid(),
            'target_actor' => 'sportler',
            'name' => 'Lifecycle Pro',
            'monthly_price_cents' => 1200,
            'yearly_price_cents' => 12000,
            'currency' => 'EUR',
            'storage_gb' => 5,
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'sort_order' => 2000,
            'is_public' => true,
            'is_active' => true,
        ], $overrides));
    }
}
