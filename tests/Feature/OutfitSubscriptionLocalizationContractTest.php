<?php

namespace Tests\Feature;

use App\Models\Notification as AppNotification;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OutfitSubscriptionLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_outfit_subscription_catalogs_have_key_and_placeholder_parity(): void
    {
        $reference = Arr::dot(require lang_path('de/outfit_subscription.php'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require lang_path($locale.'/outfit_subscription.php'));
            $this->assertSame(array_keys($reference), array_keys($catalog), $locale.' key parity');

            foreach ($reference as $key => $source) {
                $this->assertSame(
                    $this->placeholders((string) $source),
                    $this->placeholders((string) $catalog[$key]),
                    $locale.' placeholder parity for '.$key,
                );
            }
        }
    }

    public function test_mobile_responses_and_notifications_follow_request_and_recipient_locales(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('outfit-subscriptions.manage');

        Setting::setValue('billing_iban', 'DE89370400440532013000');

        $customer = User::factory()->create(['language' => 'ar']);
        $admin = User::factory()->create(['language' => 'fr']);
        $admin->givePermissionTo('outfit-subscriptions.manage');
        $plan = OutfitSubscriptionPlan::query()->create([
            'name' => 'Runner Box',
            'slug' => 'runner-box-localized',
            'monthly_price_cents' => 2990,
            'currency' => 'EUR',
            'items_per_box' => 3,
            'branding_type' => 'none',
            'is_public' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);

        $this->withHeader('X-App-Locale', 'ar')
            ->putJson('/api/v1/outfit-subscriptions/style-profile', [
                'sport_focus' => 'running',
            ])
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', __('outfit_subscription.responses.profile_saved', locale: 'ar'));

        $this->withHeader('X-App-Locale', 'ar')
            ->postJson('/api/v1/outfit-subscriptions/plans/'.$plan->id, [
                'accepted_terms' => true,
                'accepted_contract' => true,
                'payment_provider' => 'bank_transfer',
                'shipping_name' => 'Amina Beispiel',
                'shipping_country' => 'DE',
                'shipping_street' => 'Sportallee',
                'shipping_house_number' => '7',
                'shipping_postal_code' => '10115',
                'shipping_city' => 'Berlin',
            ])
            ->assertCreated()
            ->assertJsonPath('message', __('outfit_subscription.responses.requested', locale: 'ar'));

        $customerNotification = AppNotification::query()
            ->where('user_id', $customer->id)
            ->where('type', 'outfit.subscription.pending_payment')
            ->firstOrFail();
        $adminNotification = AppNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'outfit.subscription.requested')
            ->firstOrFail();

        $this->assertSame('ar', data_get($customerNotification->data, 'locale'));
        $this->assertSame(
            __('outfit_subscription.notifications.pending_payment_title', locale: 'ar'),
            data_get($customerNotification->data, 'title'),
        );
        $this->assertSame(
            'outfit_subscription.notifications.pending_payment_body',
            data_get($customerNotification->data, 'i18n.body_key'),
        );
        $this->assertSame('fr', data_get($adminNotification->data, 'locale'));
        $this->assertSame(
            __('outfit_subscription.notifications.requested_title', locale: 'fr'),
            data_get($adminNotification->data, 'title'),
        );
        $this->assertSame(
            'outfit_subscription.notifications.requested_body',
            data_get($adminNotification->data, 'i18n.body_key'),
        );
    }

    /** @return array<int, string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);

        $placeholders = array_values(array_unique($matches[0] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
