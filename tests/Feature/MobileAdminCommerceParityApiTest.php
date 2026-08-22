<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\CommerceReturnRequest;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\PublicContactRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileAdminCommerceParityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_admin_can_manage_products_campaigns_and_marketplace_configuration(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $productId = $this->postJson('/api/v1/admin/commerce/products', [
            'title' => 'Ergonomic training ball',
            'description' => 'Easy to understand product description.',
            'features_text' => "Soft grip\nVisible size label",
            'attributes_text' => 'Color: Blue',
            'image_urls_text' => '',
            'category' => 'equipment',
            'product_type' => 'single',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 4,
            'low_stock_threshold' => 2,
            'tax_class' => 'standard',
            'return_policy_type' => 'standard',
            'return_window_days' => 14,
            'price_cents' => 2499,
            'currency' => 'EUR',
            'status' => 'draft',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Ergonomic training ball')
            ->assertJsonPath('data.stock_quantity', 4)
            ->json('data.id');

        $this->postJson("/api/v1/admin/commerce/products/{$productId}/stock", [
            'quantity_delta' => 3,
            'note' => 'Mobile inventory count',
        ])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 7);

        $this->putJson("/api/v1/admin/commerce/products/{$productId}", [
            'title' => 'Ergonomic training ball Plus',
            'description' => 'Updated on mobile.',
            'features_text' => 'Soft grip',
            'attributes_text' => 'Color: Blue',
            'image_urls_text' => '',
            'category' => 'equipment',
            'product_type' => 'single',
            'is_shippable' => true,
            'manages_stock' => true,
            'stock_quantity' => 7,
            'low_stock_threshold' => 2,
            'tax_class' => 'standard',
            'return_policy_type' => 'standard',
            'return_window_days' => 14,
            'price_cents' => 2699,
            'currency' => 'EUR',
            'status' => 'review',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Ergonomic training ball Plus')
            ->assertJsonPath('data.status', 'review');

        $campaignId = $this->postJson('/api/v1/admin/commerce/campaigns', [
            'is_internal' => true,
            'force_priority' => false,
            'name' => 'Mobile welcome campaign',
            'headline' => 'Welcome',
            'objective' => 'awareness',
            'placement' => 'marketplace_card',
            'creative_format' => 'feed_square',
            'budget_cents' => 0,
            'daily_budget_cents' => 0,
            'status' => 'draft',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Mobile welcome campaign')
            ->json('data.id');

        $this->putJson("/api/v1/admin/commerce/campaigns/{$campaignId}", [
            'is_internal' => true,
            'force_priority' => true,
            'name' => 'Mobile welcome campaign',
            'headline' => 'Welcome to Airmius',
            'objective' => 'awareness',
            'placement' => 'feed',
            'creative_format' => 'feed_square',
            'budget_cents' => 0,
            'daily_budget_cents' => 0,
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.force_priority', true);

        $settings = [
            'company_country' => 'DE',
            'company_currency' => 'EUR',
            'enable_oss' => true,
            'export_vat_mode' => 'zero',
            'reverse_charge_enabled' => true,
            'ads_cpm_cents' => 500,
            'ads_cpc_cents' => 30,
            'ads_cpl_cents' => 200,
            'ads_cpa_percent' => 10,
            'ads_min_budget_cents' => 1000,
            'ads_frequency_cap_per_day' => 3,
            'ads_frequency_cap_feed' => 3,
            'ads_frequency_cap_sidebar' => 6,
            'ads_frequency_cap_marketplace_card' => 3,
            'ads_frequency_cap_sponsor_section' => 4,
        ];

        $this->putJson('/api/v1/admin/commerce/settings', $settings)
            ->assertOk()
            ->assertJsonPath('data.company_country', 'DE')
            ->assertJsonPath('data.enable_oss', true);

        $this->putJson('/api/v1/admin/commerce/marketplace-commissions', [
            'default_commission_percent' => 12,
            'commissions' => [[
                'category' => 'equipment',
                'label' => 'Equipment',
                'commission_percent' => 8,
            ]],
        ])
            ->assertOk()
            ->assertJsonFragment([
                'category' => 'equipment',
                'commission_percent' => 8,
            ]);

        $this->putJson('/api/v1/admin/commerce/marketplace-visuals', [
            'sources' => [
                'hero_banner' => 'https://cdn.example.test/marketplace-hero.webp',
            ],
            'dimensions' => [
                'hero_banner' => ['width' => 1600, 'height' => 900],
            ],
        ])
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'hero_banner',
                'source' => 'https://cdn.example.test/marketplace-hero.webp',
            ]);

        $this->getJson('/api/v1/admin/commerce/catalog')
            ->assertOk()
            ->assertJsonPath('data.commerce_settings.company_country', 'DE')
            ->assertJsonFragment(['key' => 'hero_banner'])
            ->assertJsonFragment(['category' => 'equipment']);

        $this->deleteJson("/api/v1/admin/commerce/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('marketplace_products', ['id' => $productId]);
        $this->assertSame('12', (string) Setting::valueFor('marketplace_default_commission_percent'));
        $this->assertDatabaseHas('ad_campaigns', [
            'id' => $campaignId,
            'status' => 'active',
        ]);
    }

    public function test_mobile_admin_can_process_issues_returns_and_prepare_payouts(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $product = MarketplaceProduct::query()->create([
            'user_id' => $seller->id,
            'title' => 'Digital coaching guide',
            'category' => 'digital_products',
            'product_type' => 'digital',
            'is_shippable' => false,
            'manages_stock' => false,
            'price_cents' => 4000,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
            'commission_percent' => 10,
            'payout_status' => 'pending_sales',
        ]);
        $order = CommerceOrder::query()->create([
            'user_id' => $buyer->id,
            'orderable_type' => MarketplaceProduct::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => 'bank_transfer',
            'amount_cents' => 4000,
            'commission_cents' => 400,
            'currency' => 'EUR',
            'status' => 'completed',
            'invoice_number' => 'AIR-RG-2026-000777',
            'completed_at' => now()->subDays(20),
            'shipping_status' => 'open',
            'payout_status' => 'pending',
            'issue_status' => 'reported',
            'issue_note' => 'Download was unavailable.',
        ]);
        $return = CommerceReturnRequest::query()->create([
            'commerce_order_id' => $order->id,
            'user_id' => $buyer->id,
            'status' => 'requested',
            'reason' => 'No longer needed',
            'quantity' => 1,
            'requested_amount_cents' => 4000,
            'currency' => 'EUR',
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/commerce/orders/{$order->id}/issue/reply", [
            'issue_response' => 'We reviewed the download and restored access.',
            'issue_status' => 'reviewing',
        ])
            ->assertOk()
            ->assertJsonPath('data.issue_status', 'reviewing');

        $this->patchJson("/api/v1/admin/commerce/orders/{$order->id}/issue", [
            'issue_status' => 'resolved',
            'issue_note' => 'Access restored.',
        ])
            ->assertOk()
            ->assertJsonPath('data.issue_status', 'resolved');

        $this->patchJson("/api/v1/admin/commerce/returns/{$return->id}", [
            'status' => 'approved',
            'resolution_note' => 'Return approved.',
            'approved_amount_cents' => 4000,
            'restock' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_amount_cents', 4000);

        $documentUrl = $this->getJson("/api/v1/admin/commerce/orders/{$order->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.invoice.available', true)
            ->json('data.invoice.url');

        $this->assertIsString($documentUrl);
        $documentQuery = [];
        parse_str((string) parse_url($documentUrl, PHP_URL_QUERY), $documentQuery);
        $this->assertArrayHasKey('signature', $documentQuery);

        $order->update([
            'issue_status' => 'none',
        ]);
        $return->delete();

        $this->getJson('/api/v1/admin/commerce')
            ->assertOk()
            ->assertJsonPath('data.payout_candidates.0.user_id', $seller->id)
            ->assertJsonPath('data.payout_candidates.0.amount_cents', 3600);

        $this->postJson("/api/v1/admin/commerce/payouts/users/{$seller->id}", [
            'method' => 'bank_transfer',
            'notes' => 'Prepared from mobile.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $seller->id)
            ->assertJsonPath('data.amount_cents', 3600)
            ->assertJsonPath('data.status', 'prepared');

        $this->assertDatabaseHas('marketplace_payouts', [
            'user_id' => $seller->id,
            'amount_cents' => 3600,
            'status' => 'prepared',
        ]);
        $this->assertSame(1, MarketplacePayout::query()->count());
    }

    public function test_commerce_parity_actions_require_an_authorized_admin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/commerce/products', [])
            ->assertForbidden();
        $this->putJson('/api/v1/admin/commerce/settings', [])
            ->assertForbidden();
        $this->putJson('/api/v1/admin/commerce/marketplace-visuals', [])
            ->assertForbidden();
        $lead = PublicContactRequest::query()->create([
            'name' => 'Protected Lead',
            'email' => 'protected@example.test',
            'subject' => 'Protected',
            'category' => 'Public',
            'priority' => 'normal',
            'platform' => 'android',
            'message' => 'Must remain protected.',
            'privacy_consent_at' => now(),
            'status' => 'new',
            'email_delivery_status' => 'failed',
            'retention_expires_at' => now()->addYear(),
        ]);
        $this->patchJson("/api/v1/admin/commerce/public-contact-requests/{$lead->id}", [
            'status' => 'completed',
        ])->assertForbidden();
    }

    public function test_mobile_admin_can_review_public_leads_with_audited_status_changes(): void
    {
        $lead = PublicContactRequest::query()->create([
            'name' => 'Public Test',
            'email' => 'public@example.test',
            'subject' => 'Verein empfehlen',
            'category' => 'club_interest',
            'priority' => 'normal',
            'platform' => 'android',
            'message' => 'Bitte den Verein prüfen.',
            'privacy_consent_at' => now(),
            'status' => 'new',
            'email_delivery_status' => 'failed',
            'retention_expires_at' => now()->addYear(),
        ]);

        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/commerce')
            ->assertOk()
            ->assertJsonPath('data.summary.public_contact_requests', 1)
            ->assertJsonPath('data.summary.public_contact_requests_new', 1);

        $this->getJson('/api/v1/admin/commerce/catalog')
            ->assertOk()
            ->assertJsonPath('data.public_contact_requests.0.id', $lead->id)
            ->assertJsonPath('data.public_contact_requests.0.category', 'club_interest');

        foreach (['in_progress', 'approved', 'completed'] as $status) {
            $this->patchJson("/api/v1/admin/commerce/public-contact-requests/{$lead->id}", [
                'status' => $status,
                'internal_notes' => 'Geprüft in der Android-Verwaltung.',
            ])
                ->assertOk()
                ->assertJsonPath('data.status', $status)
                ->assertJsonPath('data.status_changed_by', $admin->id);
        }

        $this->assertDatabaseHas('public_contact_requests', [
            'id' => $lead->id,
            'status' => 'completed',
            'status_changed_by' => $admin->id,
            'internal_notes' => 'Geprüft in der Android-Verwaltung.',
        ]);
        $this->assertNotNull($lead->fresh()->status_changed_at);

        $this->patchJson("/api/v1/admin/commerce/public-contact-requests/{$lead->id}", [
            'status' => 'deleted',
        ])->assertUnprocessable();
    }

    private function admin(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('marketplace.manage');

        $user = User::factory()->create();
        $user->givePermissionTo('marketplace.manage');

        return $user;
    }
}
