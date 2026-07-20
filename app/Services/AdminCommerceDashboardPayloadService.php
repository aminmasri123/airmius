<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\CommerceOrder;
use App\Models\CommerceWarehouse;
use App\Models\MarketplaceProduct;
use App\Models\Setting;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\WebsiteRequest;

class AdminCommerceDashboardPayloadService
{
    public function summary(): array
    {
        $completedOrders = CommerceOrder::query()->where('status', 'completed');

        return [
            'revenue_cents' => SubscriptionInvoice::query()->where('status', 'paid')->sum('amount_cents'),
            'open_cents' => SubscriptionInvoice::query()->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])->sum('amount_cents'),
            'coupons' => SubscriptionCoupon::query()->count(),
            'addons' => SubscriptionAddon::query()->count(),
            'products' => MarketplaceProduct::query()->count(),
            'warehouses' => CommerceWarehouse::query()->count(),
            'campaigns' => AdCampaign::query()->count(),
            'orders' => CommerceOrder::query()->count(),
            'commission_cents' => (clone $completedOrders)->sum('commission_cents'),
            'payout_cents' => (clone $completedOrders)->sum('amount_cents') - (clone $completedOrders)->sum('commission_cents'),
            'website_requests' => WebsiteRequest::query()->count(),
        ];
    }

    public function adReport(): array
    {
        return [
            'impressions' => AdCampaign::query()->sum('impressions'),
            'clicks' => AdCampaign::query()->sum('clicks'),
            'spent_cents' => AdCampaign::query()->sum('spent_cents'),
            'budget_cents' => AdCampaign::query()->sum('budget_cents'),
            'active' => AdCampaign::query()->where('status', 'active')->count(),
        ];
    }

    public function commerceSettings(): array
    {
        return [
            'company_country' => Setting::valueFor('commerce_company_country', 'DE'),
            'company_currency' => Setting::valueFor('commerce_company_currency', 'EUR'),
            'enable_oss' => Setting::boolFor('commerce_enable_oss', true),
            'export_vat_mode' => Setting::valueFor('commerce_export_vat_mode', 'zero'),
            'reverse_charge_enabled' => Setting::boolFor('commerce_reverse_charge_enabled', true),
            'ads_cpm_cents' => (int) Setting::valueFor('ads_cpm_cents', 500),
            'ads_cpc_cents' => (int) Setting::valueFor('ads_cpc_cents', 30),
            'ads_cpl_cents' => (int) Setting::valueFor('ads_cpl_cents', 200),
            'ads_cpa_percent' => (int) Setting::valueFor('ads_cpa_percent', 10),
            'ads_min_budget_cents' => (int) Setting::valueFor('ads_min_budget_cents', 1000),
            'ads_frequency_cap_per_day' => (int) Setting::valueFor('ads_frequency_cap_per_day', 3),
            'ads_frequency_cap_feed' => (int) Setting::valueFor('ads_frequency_cap_feed', 3),
            'ads_frequency_cap_sidebar' => (int) Setting::valueFor('ads_frequency_cap_sidebar', 6),
            'ads_frequency_cap_marketplace_card' => (int) Setting::valueFor('ads_frequency_cap_marketplace_card', 3),
            'ads_frequency_cap_sponsor_section' => (int) Setting::valueFor('ads_frequency_cap_sponsor_section', 4),
            'marketplace_default_commission_percent' => (int) Setting::valueFor('marketplace_default_commission_percent', 10),
        ];
    }
}
