<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProviderLocation;
use App\Models\MarketplaceProviderProfile;
use App\Models\MarketplaceSellerApplication;
use App\Models\User;
use App\Models\WebsiteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCommerceSellerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_records_the_club_on_the_order_line_and_expiry_rejects_a_second_purchase(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\Setting::setValue('billing_iban', 'DE89370400440532013000');
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $club = \App\Models\Club::factory()->create(['owner_id' => $owner->id]);
        $sub = $club->currentSubscription()->updateOrCreate(['club_id' => $club->id], [
            'subscription_plan_id' => \App\Models\SubscriptionPlan::where('slug', 'verein-komplett')->sole()->id, 'status' => 'active',
        ]);
        $product = $this->productFor($owner, 'Vereinstrikot');
        $product->update(['user_id' => null, 'club_id' => $club->id, 'is_club_shop_product' => true]);
        $payload = ['provider' => 'bank_transfer', 'accepted_terms' => true, 'quantity' => 1, 'shipping_country' => 'DE',
            'shipping_city' => 'Berlin', 'shipping_street' => 'Sportweg', 'shipping_house_number' => '1', 'shipping_postal_code' => '10115'];
        $this->actingAs($buyer)->postJson(route('auth.commerce.products.checkout', $product), $payload)->assertCreated();
        $line = \App\Models\CommerceOrderItem::where('orderable_id', $product->id)->sole();
        $this->assertEquals($club->id, $line->shop_club_id);
        $sub->update(['access_restricted_at' => now()]);
        $this->postJson(route('auth.commerce.products.checkout', $product), $payload)->assertNotFound();
        $this->assertSame(1, CommerceOrder::count());
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/clubs/{$club->id}/shop")->assertOk()->assertJsonPath('data.orders.0.items.0.id', $line->id);
    }

    public function test_club_shop_is_scoped_to_the_selected_paid_club_on_web_and_mobile(): void
    {
        $owner = User::factory()->create();
        $club = \App\Models\Club::factory()->create(['owner_id' => $owner->id]);
        $other = \App\Models\Club::factory()->create(['owner_id' => $owner->id]);
        $plan = \App\Models\SubscriptionPlan::where('slug', 'verein-komplett')->firstOrFail();
        $sub = $club->currentSubscription()->updateOrCreate(['club_id' => $club->id], [
            'subscription_plan_id' => $plan->id, 'status' => 'active',
        ]);
        MarketplaceSellerApplication::create(['user_id' => $owner->id, 'applicant_type' => 'club',
            'business_name' => $club->name, 'status' => 'approved']);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/commerce/seller/products', [
            'club_shop' => true, 'club_id' => $club->id, 'title' => 'Vereinsschal',
            'category' => 'equipment', 'price_cents' => 1200, 'stock_quantity' => 3,
        ])->assertSuccessful();
        $product = MarketplaceProduct::where('title', 'Vereinsschal')->firstOrFail();
        $this->assertNull($product->user_id);
        $this->assertTrue($product->shopAcceptsOrders());
        $this->getJson("/api/v1/clubs/{$club->id}/shop")->assertOk()->assertJsonPath('data.products.0.id', $product->id);
        $this->get(route('auth.club-shop', $club))->assertOk();
        $this->getJson("/api/v1/clubs/{$other->id}/shop")->assertForbidden();
        $this->getJson('/api/v1/commerce/seller')->assertJsonCount(0, 'data.products');
        $this->putJson('/api/v1/commerce/seller/provider-profile', [
            'club_id' => $club->id, 'display_name' => 'Vereinsladen', 'provider_type' => 'club', 'legal_country' => 'DE',
        ])->assertOk();
        $this->postJson('/api/v1/commerce/seller/provider-locations', [
            'club_id' => $club->id, 'name' => 'Vereinsheim', 'type' => 'pickup', 'country' => 'DE',
        ])->assertSuccessful();
        $this->getJson("/api/v1/clubs/{$club->id}/shop")
            ->assertJsonPath('data.provider_profile.display_name', 'Vereinsladen')
            ->assertJsonPath('data.provider_locations.0.name', 'Vereinsheim');
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'membership_status' => 'active']);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/shop")->assertForbidden();
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/commerce/seller/products/{$product->id}", ['club_id' => $other->id])->assertForbidden();
        $order = CommerceOrder::create([
            'user_id' => $owner->id, 'type' => 'marketplace_cart', 'provider' => 'bank_transfer',
            'amount_cents' => 2400, 'currency' => 'EUR', 'status' => 'completed',
        ]);
        $this->addOrderItem($order, $product, 1200);
        $line = $order->items()->firstOrFail();
        $line->update(['shop_club_id' => $club->id]);
        $this->addOrderItem($order, $this->productFor($owner, 'Privater Artikel'), 1200);
        $sub->update(['access_restricted_at' => now()]);
        $this->assertTrue($club->fresh()->canOpenShop($owner));
        $this->getJson("/api/v1/clubs/{$club->id}/shop")->assertOk()->assertJsonPath('data.active', false)
            ->assertJsonCount(1, 'data.orders.0.items')->assertJsonMissing(['title' => 'Privater Artikel']);
        $this->patchJson("/api/v1/clubs/{$club->id}/shop/items/{$line->id}", ['status' => 'shipped'])
            ->assertOk()->assertJsonPath('data.club_fulfillment_status', 'shipped');
        $this->patchJson("/api/v1/clubs/{$other->id}/shop/items/{$line->id}", ['status' => 'delivered'])->assertForbidden();
        $this->assertFalse($product->fresh()->shopAcceptsOrders());
        $this->postJson("/commerce/products/{$product->id}", [])->assertNotFound();
    }

    public function test_seller_dashboard_returns_only_owned_products_and_order_lines(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $buyer = User::factory()->create();
        $ownProduct = $this->productFor($seller, 'Eigenes Trikot');
        $foreignProduct = $this->productFor($otherSeller, 'Fremde Schuhe');
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'business',
            'business_name' => 'Sicherer Shop',
            'accepted_rules' => ['accepted_at' => now()->toJSON()],
            'status' => 'approved',
        ]);

        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'type' => 'marketplace_cart',
            'provider' => 'bank_transfer',
            'item_gross_cents' => 8800,
            'shipping_cents' => 0,
            'net_cents' => 7395,
            'tax_cents' => 1405,
            'amount_cents' => 8800,
            'commission_cents' => 880,
            'currency' => 'EUR',
            'status' => 'completed',
            'shipping_status' => 'open',
            'payout_status' => 'pending',
            'payload' => [
                'shipping_address' => [
                    'name' => 'Käufer Eins',
                    'street' => 'Sportweg',
                    'house_number' => '9',
                    'postal_code' => '10115',
                    'city' => 'Berlin',
                    'country' => 'DE',
                ],
            ],
            'completed_at' => now(),
        ]);
        $this->addOrderItem($order, $ownProduct, 3900);
        $this->addOrderItem($order, $foreignProduct, 4900);

        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/commerce/seller')
            ->assertOk()
            ->assertJsonPath('data.seller_can_sell', true)
            ->assertJsonPath('data.products.0.id', $ownProduct->id)
            ->assertJsonCount(1, 'data.products')
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonCount(1, 'data.orders.0.items')
            ->assertJsonPath('data.orders.0.items.0.product_id', $ownProduct->id)
            ->assertJsonPath('data.orders.0.seller_gross_cents', 3900)
            ->assertJsonPath('data.orders.0.shipping_address.city', 'Berlin')
            ->assertJsonMissing(['product_id' => $foreignProduct->id]);
    }

    public function test_approved_seller_can_create_update_archive_and_delete_own_products(): void
    {
        $seller = User::factory()->create();
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'private',
            'accepted_rules' => ['accepted_at' => now()->toJSON()],
            'status' => 'approved',
        ]);
        Sanctum::actingAs($seller);

        $response = $this->postJson('/api/v1/commerce/seller/products', [
            'title' => 'Mobiles Trainingsband',
            'description' => 'Elastisches Band für das tägliche Training.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'single',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 12,
            'price_cents' => 2490,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $seller->id)
            ->assertJsonPath('data.title', 'Mobiles Trainingsband');

        $productId = (int) $response->json('data.id');

        $this->putJson("/api/v1/commerce/seller/products/{$productId}", [
            'title' => 'Mobiles Trainingsband Pro',
            'description' => 'Neue Beschreibung.',
            'price_cents' => 2990,
            'manages_stock' => true,
            'stock_quantity' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Mobiles Trainingsband Pro')
            ->assertJsonPath('data.status', 'review');

        $this->patchJson("/api/v1/commerce/seller/products/{$productId}/status", [
            'status' => 'archived',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->deleteJson("/api/v1/commerce/seller/products/{$productId}")
            ->assertOk();

        $this->assertDatabaseMissing('marketplace_products', ['id' => $productId]);
    }

    public function test_teamwear_products_store_variants_supplier_funding_and_personalization_rules(): void
    {
        $seller = User::factory()->create();
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'private',
            'accepted_rules' => ['accepted_at' => now()->toJSON()],
            'status' => 'approved',
        ]);
        Sanctum::actingAs($seller);

        $response = $this->postJson('/api/v1/commerce/seller/products', [
            'title' => 'Team Hoodie 2026',
            'description' => 'Vereinskleidung mit Sammelbestell-Varianten.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'variable',
            'is_shippable' => true,
            'stock_quantity' => 20,
            'price_cents' => 5490,
            'teamwear_supplier' => 'Sporthaus Beispiel',
            'teamwear_funded_share_cents' => 1500,
            'teamwear_personalization_rules' => [
                'allowed_types' => ['name', 'initials', 'number'],
                'requires_team_member' => true,
                'privacy_acknowledgement_required' => true,
                'number_min' => 1,
                'number_max' => 99,
                'blocked_terms' => ['admin', 'vorstand'],
            ],
            'attribute_options' => [
                ['name' => 'Groesse', 'values' => ['S', 'M', 'L']],
                ['name' => 'Farbe', 'values' => ['Navy', 'Weiss']],
            ],
            'variants' => [
                [
                    'sku' => 'HOODIE-NAVY-M',
                    'price_cents' => 5490,
                    'stock_quantity' => 7,
                    'attributes' => [
                        ['name' => 'Groesse', 'value' => 'M'],
                        ['name' => 'Farbe', 'value' => 'Navy'],
                    ],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.teamwear_supplier', 'Sporthaus Beispiel')
            ->assertJsonPath('data.teamwear_funded_share_cents', 1500)
            ->assertJsonPath('data.teamwear_personalization_rules.requires_team_member', true)
            ->assertJsonPath('data.teamwear_personalization_rules.number_max', 99)
            ->assertJsonPath('data.variants.0.sku', 'HOODIE-NAVY-M')
            ->assertJsonPath('data.variants.0.attributes.0.name', 'Groesse');

        $productId = (int) $response->json('data.id');
        $this->assertDatabaseHas('marketplace_products', [
            'id' => $productId,
            'teamwear_supplier' => 'Sporthaus Beispiel',
            'teamwear_funded_share_cents' => 1500,
        ]);
    }

    public function test_teamwear_products_reject_conflicting_funding_and_personalization_rules(): void
    {
        $seller = User::factory()->create();
        MarketplaceSellerApplication::query()->create([
            'user_id' => $seller->id,
            'applicant_type' => 'private',
            'accepted_rules' => ['accepted_at' => now()->toJSON()],
            'status' => 'approved',
        ]);
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/commerce/seller/products', [
            'title' => 'Team Trikot',
            'description' => 'Vereinskleidung.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'single',
            'stock_quantity' => 5,
            'price_cents' => 3000,
            'teamwear_funded_share_cents' => 3500,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teamwear_funded_share_cents');

        $this->postJson('/api/v1/commerce/seller/products', [
            'title' => 'Team Trikot',
            'description' => 'Vereinskleidung.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'single',
            'stock_quantity' => 5,
            'price_cents' => 3000,
            'teamwear_personalization_rules' => [
                'allowed_types' => ['number'],
                'privacy_acknowledgement_required' => true,
                'number_min' => 99,
                'number_max' => 1,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teamwear_personalization_rules');
    }

    public function test_seller_profile_locations_and_payout_profile_are_owner_scoped(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $foreignProfile = MarketplaceProviderProfile::query()->create([
            'user_id' => $otherSeller->id,
            'display_name' => 'Fremder Shop',
            'provider_type' => 'private',
            'legal_country' => 'DE',
            'status' => 'active',
        ]);
        $foreignLocation = MarketplaceProviderLocation::query()->create([
            'marketplace_provider_profile_id' => $foreignProfile->id,
            'name' => 'Fremder Standort',
            'type' => 'branch',
            'country' => 'DE',
        ]);

        Sanctum::actingAs($seller);

        $this->putJson('/api/v1/commerce/seller/provider-profile', [
            'display_name' => 'Mein Sportshop',
            'provider_type' => 'business',
            'support_email' => 'hilfe@example.test',
            'legal_country' => 'de',
            'show_support_email' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Mein Sportshop')
            ->assertJsonPath('data.legal_country', 'DE');

        $location = $this->postJson('/api/v1/commerce/seller/provider-locations', [
            'name' => 'Abholung Berlin',
            'type' => 'pickup',
            'country' => 'de',
            'city' => 'Berlin',
            'pickup_enabled' => true,
            'returns_enabled' => true,
            'is_public' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.city', 'Berlin');

        $locationId = (int) $location->json('data.id');
        $this->deleteJson("/api/v1/commerce/seller/provider-locations/{$locationId}")
            ->assertOk();
        $this->deleteJson("/api/v1/commerce/seller/provider-locations/{$foreignLocation->id}")
            ->assertForbidden();

        $this->putJson('/api/v1/commerce/seller/payout-profile', [
            'account_holder' => 'Sportshop GmbH',
            'iban' => 'DE89370400440532013000',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'review');
    }

    public function test_seller_can_manage_only_owned_campaigns_and_submit_website_requests(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $foreignCampaign = AdCampaign::query()->create([
            'user_id' => $otherSeller->id,
            'name' => 'Fremde Kampagne',
            'objective' => 'traffic',
            'placement' => 'feed',
            'creative_format' => 'feed_square',
            'budget_cents' => 2500,
            'daily_budget_cents' => 0,
            'billing_event' => 'impression',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($seller);

        $campaign = $this->postJson('/api/v1/commerce/seller/campaigns', [
            'name' => 'Mobile Sommeraktion',
            'headline' => 'Gemeinsam aktiv',
            'objective' => 'traffic',
            'placement' => 'feed',
            'creative_format' => 'feed_square',
            'target_url' => 'https://example.test/sport',
            'budget_cents' => 2500,
            'start_payment' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $seller->id)
            ->assertJsonPath('data.status', 'draft');

        $campaignId = (int) $campaign->json('data.id');
        $this->patchJson("/api/v1/commerce/seller/campaigns/{$campaignId}/status", ['status' => 'paused'])->assertUnprocessable();
        AdCampaign::query()->findOrFail($campaignId)->update(['status' => 'active']);

        $this->patchJson("/api/v1/commerce/seller/campaigns/{$campaignId}/status", [
            'status' => 'paused',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $this->patchJson("/api/v1/commerce/seller/campaigns/{$foreignCampaign->id}/status", [
            'status' => 'paused',
        ])->assertForbidden();

        $this->postJson('/api/v1/commerce/seller/website-requests', [
            'domain' => 'mein-sportverein.example',
            'goals' => 'Mitglieder informieren und neue Teams vorstellen.',
            'accepted_privacy' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $seller->id)
            ->assertJsonPath('data.status', 'new');

        $this->getJson('/api/v1/commerce/seller')
            ->assertOk()
            ->assertJsonCount(1, 'data.campaigns')
            ->assertJsonPath('data.campaigns.0.id', $campaignId)
            ->assertJsonCount(1, 'data.website_requests')
            ->assertJsonPath(
                'data.website_requests.0.domain',
                'mein-sportverein.example',
            )
            ->assertJsonMissing(['name' => 'Fremde Kampagne']);

        $this->deleteJson("/api/v1/commerce/seller/campaigns/{$campaignId}", [
            'confirmation' => 'delete',
        ])->assertOk();

        $this->assertDatabaseMissing('ad_campaigns', ['id' => $campaignId]);
        $this->assertDatabaseHas('website_requests', [
            'user_id' => $seller->id,
            'domain' => 'mein-sportverein.example',
        ]);
        $this->assertSame(1, WebsiteRequest::query()->count());
    }

    private function productFor(User $seller, string $title): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create([
            'user_id' => $seller->id,
            'title' => $title,
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'single',
            'is_shippable' => true,
            'manages_stock' => false,
            'price_cents' => 3900,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);
    }

    private function addOrderItem(CommerceOrder $order, MarketplaceProduct $product, int $totalCents): void
    {
        $order->items()->create([
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'title' => $product->title,
            'quantity' => 1,
            'unit_gross_cents' => $totalCents,
            'shipping_cents' => 0,
            'net_cents' => (int) round($totalCents / 1.19),
            'tax_cents' => $totalCents - (int) round($totalCents / 1.19),
            'total_cents' => $totalCents,
            'currency' => 'EUR',
            'tax_rate_percent' => 19,
            'is_shippable' => true,
        ]);
    }
}
