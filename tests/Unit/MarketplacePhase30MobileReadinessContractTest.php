<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MarketplacePhase30MobileReadinessContractTest extends TestCase
{
    public function test_mobile_marketplace_links_to_team_bulk_orders(): void
    {
        $marketplace = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/marketplace_screen.dart');

        self::assertStringContainsString("'bulk_orders'", $marketplace);
        self::assertStringContainsString('TeamBulkOrdersScreen', $marketplace);
        self::assertStringContainsString('Icons.groups_2_outlined', $marketplace);
    }

    public function test_mobile_team_bulk_orders_support_manager_and_member_flows(): void
    {
        $screen = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/team_bulk_orders_screen.dart');
        $client = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/core/airmius_api_client.dart');

        foreach ([
            'createCommerceTeamBulkOrder',
            'saveCommerceTeamBulkOrderItem',
            'commerceTeamBulkOrderSupplierExport',
            "'order_deadline_at'",
            "'quantity'",
            "'size'",
            "'name'",
            "'number'",
            "viewer['is_team_member']",
            "viewer['can_manage']",
        ] as $contract) {
            self::assertStringContainsString($contract, $screen.$client);
        }
    }

    public function test_mobile_product_and_checkout_expose_variants_and_live_quote(): void
    {
        $marketplace = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/screens/marketplace_screen.dart');
        $client = file_get_contents(dirname(__DIR__, 2).'/mobile/airmius_mobile/lib/core/airmius_api_client.dart');

        self::assertStringContainsString("final variants = _marketMaps(product['variants'])", $marketplace);
        self::assertStringContainsString('_ProductVariantRow(', $marketplace);
        self::assertStringContainsString('Future<void> _refreshQuote() async', $marketplace);
        self::assertStringContainsString('shippingCountry: _country.text', $marketplace);
        self::assertStringContainsString("label: t('market.itemsSubtotal')", $marketplace);
        self::assertStringContainsString("label: t('market.shipping')", $marketplace);
        self::assertStringContainsString("label: t('market.includingTax')", $marketplace);
        self::assertStringContainsString('String? shippingCountry', $client);
        self::assertStringContainsString("'shipping_country': shippingCountry.trim().toUpperCase()", $client);
    }
}
