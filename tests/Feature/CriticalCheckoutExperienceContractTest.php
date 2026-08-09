<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class CriticalCheckoutExperienceContractTest extends TestCase
{
    private const LOCALES = ['de', 'en', 'fr', 'ar'];

    private const CORE_CHECKOUT_KEYS = [
        'commerce.checkout.confirm',
        'commerce.checkout.title',
        'commerce.checkout.selected_products',
        'commerce.checkout.consumer',
        'commerce.checkout.business',
        'commerce.checkout.accept_terms',
    ];

    private const PAGE_CHECKOUT_KEYS = [
        'shipping_country',
        'customer_type',
        'total',
        'period_year',
        'period_month',
        'provider_terms',
        'withdrawal',
        'preparing',
        'processing',
        'cancel',
        'continue_paid',
    ];

    public function test_critical_checkout_copy_is_render_blocking_in_all_four_locales(): void
    {
        $catalogs = [];

        foreach (self::LOCALES as $locale) {
            $catalogs[$locale] = Arr::dot(json_decode(
                (string) file_get_contents(resource_path("js/lang/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ));

            foreach (self::CORE_CHECKOUT_KEYS as $key) {
                $this->assertArrayHasKey($key, $catalogs[$locale], "{$locale}:{$key}");
                $this->assertNotSame('', trim((string) $catalogs[$locale][$key]), "{$locale}:{$key}");
            }
        }

        foreach (['en', 'fr', 'ar'] as $locale) {
            foreach (self::CORE_CHECKOUT_KEYS as $key) {
                $this->assertNotSame(
                    $catalogs['de'][$key],
                    $catalogs[$locale][$key],
                    "{$locale}:{$key} still uses German checkout copy",
                );
            }
        }

        $pageCopy = json_decode(
            (string) file_get_contents(resource_path('js/Pages/Auth/Dashboard/Commerce/commerceCriticalCheckoutCopy.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach (self::LOCALES as $locale) {
            $this->assertSame(self::PAGE_CHECKOUT_KEYS, array_keys($pageCopy[$locale] ?? []), "{$locale} page checkout key parity");

            foreach (self::PAGE_CHECKOUT_KEYS as $key) {
                $this->assertNotSame('', trim((string) $pageCopy[$locale][$key]), "{$locale}:{$key}");
            }
        }

        foreach (['en', 'fr', 'ar'] as $locale) {
            foreach (self::PAGE_CHECKOUT_KEYS as $key) {
                $this->assertNotSame($pageCopy['de'][$key], $pageCopy[$locale][$key], "{$locale}:{$key} still uses German checkout copy");
            }
        }

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $pageCopy['ar']['provider_terms']);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $pageCopy['ar']['continue_paid']);
    }

    public function test_commerce_checkout_overlays_use_the_shared_keyboard_safe_modal_and_semantic_copy(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');
        $checkoutStart = strpos($source, "<Modal\n            :show=\"checkoutConfirmation.open\"");
        $checkoutSurface = substr($source, $checkoutStart === false ? 0 : $checkoutStart);

        $this->assertStringContainsString('import Modal from "@/Components/Modal.vue"', $source);
        $this->assertStringContainsString(':show="checkoutConfirmation.open"', $checkoutSurface);
        $this->assertStringContainsString('aria-labelledby="commerce-checkout-confirmation-title"', $checkoutSurface);
        $this->assertStringContainsString('id="commerce-checkout-confirmation-title"', $checkoutSurface);
        $this->assertStringContainsString(':show="showCartCheckout"', $checkoutSurface);
        $this->assertStringContainsString('aria-labelledby="commerce-cart-checkout-title"', $checkoutSurface);
        $this->assertStringContainsString('id="commerce-cart-checkout-title"', $checkoutSurface);
        $this->assertStringContainsString('import commerceCriticalCheckoutCopy from "./commerceCriticalCheckoutCopy.json"', $source);
        $this->assertStringContainsString("ct('provider_terms')", $checkoutSurface);
        $this->assertStringContainsString("ct('continue_paid')", $checkoutSurface);
        $this->assertStringContainsString(':aria-label="ct(\'shipping_country\')"', $checkoutSurface);
        $this->assertStringNotContainsString('>Checkout bestätigen<', $checkoutSurface);
        $this->assertStringNotContainsString('>Abbrechen<', $checkoutSurface);
        $this->assertStringNotContainsString('placeholder="Firma / Verein"', $checkoutSurface);
        $this->assertStringNotContainsString('label="Zahlung wird vorbereitet..."', $checkoutSurface);
    }

    public function test_outfit_and_guest_checkout_critical_terms_do_not_wait_for_auto_translation(): void
    {
        $outfit = $this->source('resources/js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue');
        $guestProduct = $this->source('resources/js/Pages/Guest/MarketplaceProductShow.vue');

        $this->assertStringContainsString("import Modal from '@/Components/Modal.vue'", $outfit);
        $this->assertStringContainsString(':show="Boolean(pendingSubscribePlan)"', $outfit);
        $this->assertStringContainsString('aria-labelledby="outfit-subscribe-title"', $outfit);
        $this->assertStringContainsString('id="outfit-subscribe-title"', $outfit);
        $this->assertStringContainsString("{{ t('Ich akzeptiere') }}", $outfit);
        $this->assertStringContainsString("{{ t('und') }}", $outfit);
        $this->assertStringContainsString(
            "{{ \$t('Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.') }}",
            $guestProduct,
        );
        $this->assertStringContainsString("price.shipping_label || \$t('Versand')", $guestProduct);
        $this->assertStringNotContainsString(
            "\n                                    Mir ist bewusst, dass der jeweilige Anbieter für sein Angebot verantwortlich sein kann.\n",
            $guestProduct,
        );
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source);

        return $source;
    }
}
