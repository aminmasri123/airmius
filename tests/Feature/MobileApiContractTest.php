<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Club;
use App\Models\CommerceOrder;
use App\Models\File;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceSellerApplication;
use App\Models\Notification;
use App\Models\PaymentCheckout;
use App\Models\PayoutProfile;
use App\Models\Setting;
use App\Models\Story;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WebsiteRequest;
use App\Support\Api\V1\ApiContract;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileApiContractTest extends TestCase
{
    use RefreshDatabase;

    private function assertApiErrorContract($response, string $code): void
    {
        $response
            ->assertJsonStructure([
                'message',
                'errors',
                'code',
                'error' => ['code', 'message'],
                'meta' => ['api_version', 'contract_version', 'request_id'],
            ])
            ->assertJsonPath('code', $code)
            ->assertJsonPath('error.code', $code)
            ->assertJsonPath('meta.api_version', 'v1');
    }

    public function test_mobile_meta_returns_versioned_capabilities(): void
    {
        config()->set('sport_map.routing.provider', 'local');

        $response = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonPath('data.contract_version', ApiContract::CONTRACT_VERSION)
            ->assertJsonPath('data.minimum_app_version', ApiContract::MIN_CLIENT_VERSION)
            ->assertJsonPath('data.feature_flags.mvp_surface', true)
            ->assertJsonPath('data.feature_flags.secure_token_storage', true)
            ->assertJsonPath('data.feature_flags.sport_integrations', true)
            ->assertJsonPath('data.feature_flags.global_search_typed', true)
            ->assertJsonPath('data.supported_locales.0', 'de')
            ->assertJsonPath('data.supported_locales.3', 'ar')
            ->assertJsonPath('data.role_matrix.0.key', 'sportler')
            ->assertJsonPath('data.role_matrix.1.key', 'trainer')
            ->assertJsonPath('data.role_matrix.2.key', 'verein_admin')
            ->assertJsonPath('data.role_matrix.3.key', 'elternteil')
            ->assertJsonPath('data.role_matrix.4.key', 'sponsor')
            ->assertJsonPath('data.role_matrix.5.key', 'plattform_admin')
            ->assertJsonPath('data.role_matrix.2.club_roles.0', 'owner')
            ->assertJsonPath('data.role_matrix.3.team_roles.0', 'ParentContact')
            ->assertJsonPath('data.modules.0.key', 'account')
            ->assertJsonPath('data.capabilities.profile.0', 'user_card')
            ->assertJsonPath('data.capabilities.search.3', 'events')
            ->assertJsonPath('data.capabilities.search.6', 'files')
            ->assertJsonPath('data.capabilities.chat.0', 'conversations')
            ->assertJsonPath('data.capabilities.commerce.0', 'products')
            ->assertJsonPath('data.capabilities.subscriptions.2', 'bank_transfer_checkout')
            ->assertJsonPath('data.capabilities.training.2', 'log_create_stepper')
            ->assertJsonPath('data.capabilities.training.4', 'gym_exercise_set_stepper')
            ->assertJsonPath('data.capabilities.sport_integrations.0', 'provider_status')
            ->assertJsonPath('data.capabilities.sport_integrations.5', 'normalized_activity_import')
            ->assertJsonPath('data.capabilities.nutrition.0', 'meal_logging')
            ->assertJsonPath('data.capabilities.nutrition.3', 'recipe_suggestions')
            ->assertJsonPath('data.capabilities.nutrition.4', 'food_search_open_food_facts')
            ->assertJsonPath('data.catalogs.training.log_create_flow.steps.1.key', 'document')
            ->assertJsonPath('data.catalogs.training.log_create_flow.finish_fields.0', 'intensity')
            ->assertJsonPath('data.catalogs.training.training_types.0.key', 'gym')
            ->assertJsonPath('data.catalogs.training.training_types.0.document_flow', 'exercise_set_stepper')
            ->assertJsonPath('data.catalogs.nutrition.meal_types.0.key', 'breakfast')
            ->assertJsonPath('data.catalogs.nutrition.goal_types.0.key', 'maintain')
            ->assertJsonPath('data.catalogs.nutrition.external_sources.0.key', 'open_food_facts')
            ->assertJsonPath('data.capabilities.clubs.3', 'manager_members')
            ->assertJsonPath('data.capabilities.clubs.4', 'manager_billing')
            ->assertJsonPath('data.capabilities.sport_map.0', 'route_planning')
            ->assertJsonPath('data.catalogs.sport_map.sport_types.0.key', 'running')
            ->assertJsonPath('data.catalogs.sport_map.sport_types.0.label_key', 'sport_map.sport_types.running')
            ->assertJsonPath('data.catalogs.sport_map.place_types.0.key', 'football_pitch')
            ->assertJsonPath('data.catalogs.sport_map.map.tile_url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png')
            ->assertJsonPath('data.catalogs.sport_map.map.satellite_tile_url', 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')
            ->assertJsonPath('data.catalogs.sport_map.routing.provider', 'local')
            ->assertJsonPath('data.capabilities.notifications.0', 'list')
            ->assertJsonPath('data.capabilities.uploads.0', 'list')
            ->assertJsonPath('data.capabilities.settings.1', 'update')
            ->assertJsonPath('data.capabilities.support.2', 'contextual_help')
            ->assertJsonPath('data.support.contextual_help.0.key', 'club_membership_member_view')
            ->assertJsonPath('data.support.contextual_help.1.required_capability', 'manager_members')
            ->assertJsonPath('data.support.contextual_help.2.required_capability', 'manager_billing')
            ->assertJsonPath('data.support.diagnostics.endpoint', '/api/v1/support/contact')
            ->assertJsonPath('data.support.diagnostics.allowed_fields.0', 'app_version')
            ->assertJsonPath('data.support.diagnostics.forbidden_fields.9', 'device_id')
            ->assertJsonPath('data.support.diagnostics.stores_device_identifiers', false)
            ->assertJsonPath('data.support.diagnostics.requires_user_submitted_message', true);

        $trainingModule = collect($response->json('data.modules'))->firstWhere('key', 'training');

        $this->assertIsArray($trainingModule);
        $this->assertSame('training', $trainingModule['processing_purpose']);
        $this->assertSame('highly_sensitive', $trainingModule['data_classification']);
    }

    public function test_api_v1_client_errors_use_contract_shape(): void
    {
        $this->assertApiErrorContract(
            $this->getJson('/api/v1/me')->assertUnauthorized(),
            'unauthenticated',
        );

        $this->assertApiErrorContract(
            $this->postJson('/api/v1/auth/login', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']),
            'validation_failed',
        );

        Sanctum::actingAs(User::factory()->create());
        $this->assertApiErrorContract(
            $this->getJson('/api/v1/posts/999999999/image')->assertNotFound(),
            'not_found',
        );

        $this->assertApiErrorContract(
            $this->deleteJson('/api/v1/meta')->assertStatus(405),
            'method_not_allowed',
        );
    }

    public function test_api_v1_server_errors_use_contract_shape(): void
    {
        config()->set('app.debug', false);

        Route::get('/api/v1/__test/server-error', fn () => throw new \RuntimeException('boom'));

        $this->assertApiErrorContract(
            $this->getJson('/api/v1/__test/server-error')
                ->assertStatus(500)
                ->assertJsonPath('message', 'An unexpected error occurred.'),
            'server_error',
        );
    }

    public function test_regular_club_member_cannot_read_member_or_billing_management_api(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        Sanctum::actingAs($user);

        $this->assertApiErrorContract(
            $this->getJson("/api/v1/clubs/{$club->id}/members")
                ->assertForbidden()
                ->assertJsonPath('message', 'Du hast dafür keine Berechtigung. Bitte wende dich an deinen Verein/Admin oder prüfe dein Paket.'),
            'forbidden',
        );
        $this->assertApiErrorContract(
            $this->getJson("/api/v1/clubs/{$club->id}/billing")
                ->assertForbidden()
                ->assertJsonPath('message', 'Du hast dafür keine Berechtigung. Bitte wende dich an deinen Verein/Admin oder prüfe dein Paket.'),
            'forbidden',
        );
    }

    public function test_club_manager_can_read_member_and_billing_management_api(): void
    {
        $manager = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->users()->syncWithoutDetaching([
            $manager->id => [
                'role' => 'manager',
                'roles' => ['manager'],
                'membership_status' => 'active',
            ],
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/clubs/{$club->id}/members")->assertOk();
        $this->getJson("/api/v1/clubs/{$club->id}/billing")->assertOk();
    }

    public function test_authenticated_user_can_read_profile_and_update_language(): void
    {
        $user = User::factory()->create(['language' => 'de', 'name' => 'Mobile Tester']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.language', 'de')
            ->assertJsonPath('data.user_card.display_name', 'Mobile Tester')
            ->assertJsonPath('data.user_card.initials', 'MT');

        $this->patchJson('/api/v1/me/language', ['language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.language', 'ar');

        $this->assertSame('ar', $user->fresh()->language);
    }

    public function test_authenticated_user_can_update_mobile_settings(): void
    {
        $user = User::factory()->create(['country' => 'DE']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/settings', [
            'country' => 'fr',
            'city' => 'Paris',
            'event_radius_km' => 25,
            'profile_visibility' => 'private',
            'direct_message_privacy' => 'friends',
            'friend_request_privacy' => 'friends',
            'ads_personalization_consent' => true,
            'ads_measurement_consent' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.country', 'FR')
            ->assertJsonPath('data.city', 'Paris')
            ->assertJsonPath('data.profile_visibility', 'private')
            ->assertJsonPath('data.direct_message_privacy', 'friends');

        $fresh = $user->fresh();

        $this->assertSame('FR', $fresh->country);
        $this->assertSame(25, $fresh->event_radius_km);
    }

    public function test_notification_preferences_are_saved_server_side_and_returned_with_settings(): void
    {
        $user = User::factory()->create(['country' => 'DE']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/settings', [
            'notification_channels' => [
                'chat' => false,
                'club' => true,
                'billing' => false,
            ],
            'notification_quiet_time' => 'early',
        ])
            ->assertOk();

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.notification_preferences.channels.chat', false)
            ->assertJsonPath('data.notification_preferences.channels.club', true)
            ->assertJsonPath('data.notification_preferences.channels.billing', false)
            ->assertJsonPath('data.notification_preferences.quiet_time', 'early');

        $this->assertFalse($user->fresh()->notification_channels['chat']);
        $this->assertSame('early', $user->fresh()->notification_quiet_time);

        $this->patchJson('/api/v1/settings', [
            'notification_channels' => ['unknown' => true],
        ])->assertStatus(422)->assertJsonValidationErrors(['notification_channels']);
    }

    public function test_mobile_notifications_can_be_listed_read_and_deleted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'event.reminder',
            'data' => [
                'title' => 'Training heute',
                'body' => 'Heute um 18:00 Uhr.',
                'url' => '/events/1',
            ],
            'read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'chat.message',
            'data' => ['title' => 'Chat'],
            'read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $other->id,
            'type' => 'event.reminder',
            'data' => ['title' => 'Other'],
            'read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.title', 'Training heute')
            ->assertJsonPath('meta.unread_count', 1);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertTrue($notification->fresh()->read);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_mobile_club_membership_management_endpoint_requires_manager_context(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Mobile Club',
        ]);

        $club->users()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'roles' => ['member'],
                'membership_status' => 'active',
            ],
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/clubs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $club->id)
            ->assertJsonPath('data.0.name', 'Mobile Club');

        $this->getJson("/api/v1/clubs/{$club->id}/members")
            ->assertForbidden();
    }

    public function test_mobile_upload_flow_can_create_rename_and_delete_user_file(): void
    {
        Storage::fake(UploadStorage::disk());

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/uploads', [
            'scope' => 'user',
            'file' => UploadedFile::fake()->image('mobile-avatar.jpg', 24, 24),
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.display_name', 'mobile-avatar.jpg');

        $file = File::query()->firstOrFail();

        Storage::disk(UploadStorage::disk())->assertExists($file->path);

        $this->patchJson("/api/v1/uploads/{$file->id}", [
            'display_name' => 'renamed-mobile-avatar.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'renamed-mobile-avatar.jpg');

        $this->deleteJson("/api/v1/uploads/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_mobile_story_flow_can_upload_view_react_and_delete(): void
    {
        Storage::fake(UploadStorage::disk());

        $author = User::factory()->create(['name' => 'Mobile Story Author']);
        Sanctum::actingAs($author);

        $this->post('/api/v1/stories', [
            'visibility' => 'public',
            'caption' => 'Mobile story upload',
            'media' => UploadedFile::fake()->image('story.jpg', 32, 32),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', 'Story erstellt.')
            ->assertJsonPath('data.caption', 'Mobile story upload')
            ->assertJsonPath('data.actor.user_card.display_name', 'Mobile Story Author')
            ->assertJsonPath('data.can_delete', true);

        $story = Story::query()->firstOrFail();

        Storage::disk(UploadStorage::disk())->assertExists($story->media_path);

        $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $story->id)
            ->assertJsonPath('data.0.actor.user_card.display_name', 'Mobile Story Author');

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/stories/{$story->id}/viewed")
            ->assertOk()
            ->assertJsonPath('data.viewed_by_me', true);

        $this->postJson("/api/v1/stories/{$story->id}/react", ['reaction' => 'fire'])
            ->assertOk()
            ->assertJsonPath('data.my_reaction', 'fire');

        Sanctum::actingAs($author);

        $this->deleteJson("/api/v1/stories/{$story->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('stories', ['id' => $story->id]);
    }

    public function test_mobile_subscription_bank_transfer_checkout_can_be_created_and_marked_paid(): void
    {
        $user = User::factory()->create(['country' => 'DE']);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'mobile-sportler-pro',
            'target_actor' => 'sportler',
            'name' => 'Mobile Sportler Pro',
            'monthly_price_cents' => 1200,
            'yearly_price_cents' => 12000,
            'currency' => 'EUR',
            'features' => ['Mobile checkout'],
            'sort_order' => 999,
            'is_public' => true,
            'is_active' => true,
        ]);

        Setting::setValue('billing_bank_account_holder', 'Airmius GmbH');
        Setting::setValue('billing_bank_name', 'Airmius Bank');
        Setting::setValue('billing_iban', 'DE89370400440532013000');
        Setting::setValue('billing_bic', 'COBADEFFXXX');
        Setting::setValue('billing_payment_terms_days', 10);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'accepted_terms' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_transfer')
            ->assertJsonPath('data.amount_cents', 1200)
            ->assertJsonPath('data.bank_transfer.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.invoice.status', 'awaiting_transfer');

        $checkout = PaymentCheckout::query()->firstOrFail();

        $admin = $this->adminWithPermission('subscriptions.manage');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/subscription-checkouts/{$checkout->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.invoice.status', 'paid');

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'bank_transfer',
        ]);
    }

    public function test_mobile_subscription_stripe_checkout_returns_redirect_action(): void
    {
        config(['services.stripe.secret' => 'sk_test_mobile']);
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_mobile',
                'url' => 'https://checkout.stripe.test/mobile-session',
            ]),
        ]);

        $user = User::factory()->create(['country' => 'DE']);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'mobile-stripe-pro',
            'target_actor' => 'sportler',
            'name' => 'Mobile Stripe Pro',
            'monthly_price_cents' => 1600,
            'yearly_price_cents' => 16000,
            'currency' => 'EUR',
            'sort_order' => 1001,
            'is_public' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'stripe',
            'billing_interval' => 'monthly',
            'accepted_terms' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/mobile-session')
            ->assertJsonPath('data.payment_action.type', 'redirect')
            ->assertJsonPath('data.payment_action.provider', 'stripe')
            ->assertJsonPath('data.invoice.status', 'open');

        $this->assertDatabaseHas('payment_checkouts', [
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_checkout_id' => 'cs_test_mobile',
            'checkout_url' => 'https://checkout.stripe.test/mobile-session',
        ]);
    }

    public function test_mobile_subscription_paypal_checkout_returns_redirect_action(): void
    {
        config([
            'services.paypal.client_id' => 'paypal-client',
            'services.paypal.client_secret' => 'paypal-secret',
            'services.paypal.mode' => 'sandbox',
        ]);
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token']),
            'https://api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response(['id' => 'PROD-MOBILE']),
            'https://api-m.sandbox.paypal.com/v1/billing/plans' => Http::response(['id' => 'PLAN-MOBILE']),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'SUB-MOBILE',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://paypal.test/approve-mobile'],
                ],
            ]),
        ]);

        $user = User::factory()->create(['country' => 'DE']);
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'mobile-paypal-pro',
            'target_actor' => 'sportler',
            'name' => 'Mobile PayPal Pro',
            'monthly_price_cents' => 1700,
            'yearly_price_cents' => 17000,
            'currency' => 'EUR',
            'sort_order' => 1002,
            'is_public' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscription-plans/{$plan->id}/checkout", [
            'provider' => 'paypal',
            'billing_interval' => 'yearly',
            'accepted_terms' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'paypal')
            ->assertJsonPath('data.checkout_url', 'https://paypal.test/approve-mobile')
            ->assertJsonPath('data.payment_action.type', 'redirect')
            ->assertJsonPath('data.payment_action.provider', 'paypal')
            ->assertJsonPath('data.provider_subscription_id', 'SUB-MOBILE');

        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'paypal_product_id' => 'PROD-MOBILE',
            'paypal_plan_id' => 'PLAN-MOBILE',
        ]);
    }

    public function test_mobile_user_can_cancel_and_renew_own_subscription(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->create([
            'slug' => 'mobile-renewable',
            'target_actor' => 'sportler',
            'name' => 'Mobile Renewable',
            'monthly_price_cents' => 900,
            'yearly_price_cents' => 9000,
            'currency' => 'EUR',
            'minimum_term_months' => 0,
            'cancellation_notice_days' => 0,
            'sort_order' => 1000,
            'is_public' => true,
            'is_active' => true,
        ]);
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'current_period_ends_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscriptions/user/{$subscription->id}/cancel", [
            'mode' => 'period_end',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancels_at_period_end')
            ->assertJsonPath('data.cancel_at_period_end', true);

        $this->postJson("/api/v1/subscriptions/user/{$subscription->id}/renew", [
            'months' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_at_period_end', false);
    }

    public function test_mobile_admin_commerce_actions_cover_status_shipping_and_payouts(): void
    {
        $admin = $this->adminWithPermission('marketplace.manage');
        $seller = User::factory()->create();

        $product = MarketplaceProduct::query()->create([
            'user_id' => $seller->id,
            'title' => 'Mobile Commerce Ball',
            'description' => 'API test product',
            'category' => 'product',
            'price_cents' => 2500,
            'currency' => 'EUR',
            'status' => 'review',
            'moderation_status' => 'pending',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);

        $order = CommerceOrder::query()->create([
            'user_id' => $seller->id,
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 2500,
            'commission_cents' => 250,
            'currency' => 'EUR',
            'status' => 'completed',
            'shipping_status' => 'open',
            'payout_status' => 'prepared',
        ]);

        $payout = MarketplacePayout::query()->create([
            'user_id' => $seller->id,
            'currency' => 'EUR',
            'gross_cents' => 2500,
            'commission_cents' => 250,
            'amount_cents' => 2250,
            'method' => 'bank_transfer',
            'status' => 'prepared',
        ]);
        $order->update(['payout_id' => $payout->id]);

        $profile = PayoutProfile::query()->create([
            'user_id' => $seller->id,
            'account_holder' => 'Seller',
            'status' => 'review',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/commerce')
            ->assertOk()
            ->assertJsonPath('data.summary.products', 1)
            ->assertJsonPath('data.shipping_carriers.0.label', 'DHL');

        $this->patchJson("/api/v1/admin/commerce/products/{$product->id}/status", [
            'status' => 'published',
            'moderation_status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.moderation_status', 'approved');

        $this->patchJson("/api/v1/admin/commerce/orders/{$order->id}/shipping", [
            'shipping_status' => 'shipped',
            'shipping_carrier' => 'dhl',
            'tracking_number' => ' 00340434161234567890 ',
        ])
            ->assertOk()
            ->assertJsonPath('data.shipping_status', 'shipped')
            ->assertJsonPath('data.shipping_carrier', 'DHL')
            ->assertJsonPath('data.tracking_number', '00340434161234567890');

        $this->patchJson("/api/v1/admin/commerce/payouts/{$payout->id}/paid", [
            'notes' => 'Paid through mobile admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->patchJson("/api/v1/admin/commerce/payout-profiles/{$profile->id}", [
            'status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_mobile_admin_commerce_special_flows_are_available_as_json_contracts(): void
    {
        $admin = $this->adminWithPermission('marketplace.manage');
        $seller = User::factory()->create();

        $sellerApplication = MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'user',
            'business_name' => 'Mobile Seller',
            'accepted_rules' => ['seller_terms'],
            'status' => 'pending',
        ]);
        $websiteRequest = WebsiteRequest::query()->create([
            'user_id' => $seller->id,
            'status' => 'new',
            'package' => 'website_plus',
            'domain' => 'mobile.example',
        ]);
        $campaign = AdCampaign::query()->create([
            'user_id' => $seller->id,
            'name' => 'Mobile Ads',
            'budget_cents' => 5000,
            'status' => 'pending_review',
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $seller->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'invoice_number' => 'AIR-RG-2026-000001',
            'amount_cents' => 4000,
            'net_cents' => 3361,
            'tax_cents' => 639,
            'currency' => 'EUR',
            'tax_country' => 'DE',
            'status' => 'completed',
            'shipping_status' => 'delivered',
            'refunded_cents' => 1000,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/commerce/coupons', [
            'code' => 'mobile10',
            'name' => 'Mobile 10',
            'type' => 'percent',
            'percent_off' => 10,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MOBILE10');

        $coupon = SubscriptionCoupon::query()->firstOrFail();

        $this->patchJson("/api/v1/admin/commerce/coupons/{$coupon->id}", [
            'code' => 'mobile15',
            'name' => 'Mobile 15',
            'type' => 'percent',
            'percent_off' => 15,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.code', 'MOBILE15');

        $this->postJson('/api/v1/admin/commerce/addons', [
            'slug' => 'mobile-addon',
            'name' => 'Mobile Add-on',
            'monthly_price_cents' => 300,
            'yearly_price_cents' => 3000,
            'target_actor' => 'verein',
            'features' => ['Mobile'],
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'mobile-addon');

        $this->postJson('/api/v1/admin/commerce/tax-rates', [
            'name' => 'Germany standard',
            'country_code' => 'de',
            'tax_label' => 'MwSt.',
            'rate_percent' => 19,
            'currency' => 'eur',
            'is_default' => true,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.country_code', 'DE')
            ->assertJsonPath('data.currency', 'EUR');

        $this->postJson('/api/v1/admin/commerce/shipping-rates', [
            'name' => 'Germany shipping',
            'origin_country_code' => 'de',
            'country_code' => 'de',
            'amount_cents' => 499,
            'currency' => 'eur',
            'free_from_cents' => 5000,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.country_code', 'DE')
            ->assertJsonPath('data.amount_cents', 499);

        $this->patchJson("/api/v1/admin/commerce/seller-applications/{$sellerApplication->id}", [
            'status' => 'approved',
            'review_note' => 'Approved from mobile admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->patchJson("/api/v1/admin/commerce/website-requests/{$websiteRequest->id}", [
            'status' => 'quoted',
            'notes' => 'Offer sent',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'quoted');

        $this->patchJson("/api/v1/admin/commerce/campaigns/{$campaign->id}/status", [
            'status' => 'paused',
            'review_note' => 'Paused from mobile admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/refund", [
            'amount_cents' => 1500,
            'reason' => 'Partial mobile refund',
        ])
            ->assertOk()
            ->assertJsonPath('data.refunded_cents', 2500)
            ->assertJsonPath('data.issue_status', 'refunded');

        $order->refresh();

        $this->getJson("/api/v1/admin/commerce/orders/{$order->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.invoice.available', true)
            ->assertJsonPath('data.credit_note.available', true);

        $this->getJson('/api/v1/admin/commerce/catalog')
            ->assertOk()
            ->assertJsonPath('data.coupons.0.code', 'MOBILE15')
            ->assertJsonPath('data.addons.0.slug', 'mobile-addon')
            ->assertJsonFragment(['country_code' => 'DE'])
            ->assertJsonFragment(['amount_cents' => 499]);

        $this->getJson('/api/v1/admin/commerce/export')
            ->assertOk()
            ->assertJsonPath('data.columns.0', 'order_id')
            ->assertJsonPath('data.rows.0.order_id', $order->id);
    }

    private function adminWithPermission(string $permission): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}
