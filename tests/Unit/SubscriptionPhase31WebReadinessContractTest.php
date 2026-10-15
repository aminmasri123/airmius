<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SubscriptionPhase31WebReadinessContractTest extends TestCase
{
    public function test_outfit_locales_keep_the_selected_size_contract_in_sync(): void
    {
        $catalogs = [];
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalogs[$locale] = require dirname(__DIR__, 2)."/lang/{$locale}/outfit_subscription.php";
            self::assertArrayHasKey('selected_size', $catalogs[$locale]['fields']);
        }

        self::assertSame(
            array_keys($catalogs['de']),
            array_keys($catalogs['en']),
        );
        self::assertSame(
            array_keys($catalogs['de']),
            array_keys($catalogs['fr']),
        );
        self::assertSame(
            array_keys($catalogs['de']),
            array_keys($catalogs['ar']),
        );
    }

    public function test_catalog_checkout_and_limits_keep_person_and_club_contracts_separate(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/SubscriptionController.php');
        $resource = file_get_contents(dirname(__DIR__, 2).'/app/Http/Resources/Api/V1/SubscriptionPlanResource.php');
        $limits = file_get_contents(dirname(__DIR__, 2).'/app/Services/PlanFeatureService.php');
        $club = file_get_contents(dirname(__DIR__, 2).'/app/Models/Club.php');

        self::assertStringContainsString("->where('is_active', true)", $controller);
        self::assertStringContainsString("->where('is_public', true)", $controller);
        self::assertStringContainsString("'target_actor'", $resource);
        self::assertStringContainsString("'club_id' => ['nullable', 'integer', 'min:1']", $controller);
        self::assertStringContainsString("'accepted_terms' => ['accepted']", $controller);

        foreach (['member_limit', 'team_limit', 'storage_gb'] as $limit) {
            self::assertStringContainsString("'{$limit}'", $resource);
            self::assertStringContainsString($limit, $limits.$club);
        }
    }

    public function test_subscription_lifecycle_exposes_activation_cancellation_renewal_and_payment_grace(): void
    {
        $activation = file_get_contents(dirname(__DIR__, 2).'/app/Services/Subscriptions/SubscriptionCheckoutActivationService.php');
        $lifecycle = file_get_contents(dirname(__DIR__, 2).'/app/Services/Subscriptions/SubscriptionLifecycleService.php');
        $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/ProcessSubscriptionLifecycle.php');
        $settings = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/Settings/Index.vue');

        self::assertStringContainsString("'status' => 'active'", $activation);
        self::assertStringContainsString('function cancel(', $lifecycle);
        self::assertStringContainsString('function resume(', $lifecycle);
        self::assertStringContainsString("'status' => 'past_due'", $command);
        self::assertStringContainsString("'grace_period_ends_at'", $command);
        self::assertStringContainsString('billing.payment_failed_title', $settings);
        self::assertStringContainsString('subscription.grace_period_ends_at', $settings);
    }

    public function test_platform_invoices_remain_separate_and_downloadable(): void
    {
        $settings = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/Settings/Index.vue');
        $download = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/MemberBillingInvoiceController.php');
        $resource = file_get_contents(dirname(__DIR__, 2).'/app/Http/Resources/Api/V1/SubscriptionInvoiceResource.php');

        self::assertStringContainsString('billing.subscription_invoices_title', $settings);
        self::assertStringContainsString('billing.my_invoices_title', $settings);
        self::assertStringContainsString("\$kind === 'subscription_invoice'", $download);
        self::assertStringContainsString("'kind' => 'subscription_invoice'", $resource);
    }

    public function test_outfit_checkout_persists_size_address_contract_and_delivery_state(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OutfitSubscriptionController.php');
        $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/OutfitSubscription.php');
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue');

        foreach ([
            "'selected_size' => ['nullable', 'string', 'max:60']",
            "'selected_size' => \$selectedSize",
            "'shipping_address'",
            "'deliveries'",
            'accepted_contract_at',
        ] as $contract) {
            self::assertStringContainsString($contract, $controller.$model);
        }

        self::assertStringContainsString('v-model="subscribeSelectedSize"', $page);
        self::assertStringContainsString('selected_size: subscribeSelectedSize.value', $page);
        self::assertStringContainsString('subscription.selected_size', $page);
        self::assertStringContainsString('subscription.deliveries', $page);
    }
}
