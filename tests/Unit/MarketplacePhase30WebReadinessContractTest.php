<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MarketplacePhase30WebReadinessContractTest extends TestCase
{
    public function test_authenticated_web_catalog_only_contains_published_and_approved_products(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommerceCheckoutController.php');

        self::assertMatchesRegularExpression(
            "/'products' => MarketplaceProduct::query\(\).*?->where\('status', 'published'\).*?->where\('moderation_status', 'approved'\)/s",
            $controller,
        );
    }

    public function test_web_exposes_a_complete_team_bulk_order_workflow(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');
        $workspace = file_get_contents(dirname(__DIR__, 2).'/resources/js/composables/useCommerceWorkspace.js');
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/Commerce/TeamBulkOrders.vue');

        self::assertStringContainsString('TeamBulkOrders', $page);
        self::assertStringContainsString("key: 'bulk_orders'", $workspace);

        foreach ([
            '/api/v1/commerce/team-bulk-orders',
            'marketplace_product_id',
            'order_deadline_at',
            'personalization',
            'quantity',
            'size',
            'supplier-export',
            'viewer?.is_team_member',
            'viewer?.can_manage',
        ] as $contract) {
            self::assertStringContainsString($contract, $component);
        }
    }

    public function test_bulk_order_api_keeps_price_and_export_authorization_contracts(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/TeamBulkOrderController.php');
        $resource = file_get_contents(dirname(__DIR__, 2).'/app/Http/Resources/Api/V1/TeamBulkOrderResource.php');

        self::assertStringContainsString("'price_snapshot'", $controller);
        self::assertStringContainsString("'unit_price_cents' => \$bulkOrder->unit_price_cents", $controller);
        self::assertStringContainsString('authorizeClubManager($request, $bulkOrder)', $controller);
        self::assertStringContainsString("->where('owner_id', \$request->user()->id)", $controller);
        self::assertStringContainsString("'can_manage'", $resource);
        self::assertStringContainsString("'is_team_member'", $resource);
        self::assertStringContainsString("'item' => \$viewerItem", $resource);
    }

    public function test_web_checkout_refreshes_the_server_calculated_quote(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommerceCheckoutController.php');
        $payload = file_get_contents(dirname(__DIR__, 2).'/app/Services/CommerceCheckoutPayloadService.php');
        $cart = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/Commerce/Cart.vue');

        self::assertStringContainsString("'shipping_country' => ['nullable', 'string', 'size:2']", $controller);
        self::assertStringContainsString('cartResource($request, $preview)', $controller);
        self::assertStringContainsString('customerFromData($data)', $payload);
        self::assertStringContainsString('const refreshQuote = async () =>', $cart);
        self::assertStringContainsString("headers: { Accept: 'application/json' }", $cart);
        self::assertStringContainsString("@change=\"refreshQuote\"", $cart);
        self::assertStringContainsString("\$t('Preis aktualisieren')", $cart);
    }
}
