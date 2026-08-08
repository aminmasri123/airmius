<?php

namespace Tests\Feature;

use App\Models\Notification as AppNotificationRecord;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionInvoiceAwaitingTransfer;
use App\Notifications\SubscriptionInvoicePaid;
use App\Services\Subscriptions\SubscriptionCheckoutActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionLocalizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_catalogs_have_key_and_placeholder_parity(): void
    {
        $reference = Arr::dot(require base_path('lang/de/subscription.php'));

        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $catalog = Arr::dot(require base_path('lang/'.$locale.'/subscription.php'));
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

    public function test_checkout_activation_is_idempotent_and_notifies_in_recipient_locale(): void
    {
        Notification::fake();
        $user = User::factory()->create(['language' => 'ar']);
        $plan = $this->paidPlan();
        $coupon = SubscriptionCoupon::query()->create([
            'code' => 'ARABIC10',
            'name' => 'Arabic 10',
            'type' => 'percent',
            'percent_off' => 10,
            'is_active' => true,
        ]);
        $checkout = PaymentCheckout::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'subscription_coupon_id' => $coupon->id,
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'original_amount_cents' => 1299,
            'discount_cents' => 130,
            'amount_cents' => 1169,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_reference' => 'AIRMIUS-IDEMPOTENT-1',
        ]);
        $invoice = SubscriptionInvoice::query()->create([
            'payment_checkout_id' => $checkout->id,
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'subscription_type' => 'user',
            'number' => 'AR-2026-900001',
            'title' => 'Airmius Athlete Pro',
            'amount_cents' => 1169,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_method' => 'bank_transfer',
            'issued_at' => now(),
        ]);
        $service = app(SubscriptionCheckoutActivationService::class);

        $first = $service->activate($checkout);
        $second = $service->activate($checkout->fresh());

        $this->assertTrue($first['activated']);
        $this->assertFalse($second['activated']);
        $this->assertSame('completed', $checkout->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, $coupon->fresh()->redeemed_count);
        $this->assertSame(1, $user->subscriptions()->where('subscription_plan_id', $plan->id)->count());

        $notification = AppNotificationRecord::query()
            ->where('user_id', $user->id)
            ->where('type', 'subscription.invoice.paid')
            ->firstOrFail();
        $this->assertSame('ar', data_get($notification->data, 'locale'));
        $this->assertSame(
            __('subscription.notifications.paid_title', locale: 'ar'),
            data_get($notification->data, 'title'),
        );
        $this->assertSame('subscription.notifications.paid_body', data_get($notification->data, 'i18n.body_key'));
        $this->assertSame(1, AppNotificationRecord::query()->where('type', 'subscription.invoice.paid')->count());
        Notification::assertSentToTimes($user, SubscriptionInvoicePaid::class, 1);
    }

    public function test_subscription_invoice_emails_use_the_recipient_locale(): void
    {
        $user = User::factory()->create(['language' => 'fr', 'name' => 'Camille']);
        $plan = $this->paidPlan();
        $checkout = PaymentCheckout::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'provider' => 'bank_transfer',
            'billing_interval' => 'monthly',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
        ]);
        $invoice = SubscriptionInvoice::query()->create([
            'payment_checkout_id' => $checkout->id,
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'subscription_type' => 'user',
            'number' => 'AR-2026-900002',
            'title' => 'Airmius Athlete Pro',
            'amount_cents' => 1299,
            'currency' => 'EUR',
            'status' => 'awaiting_transfer',
            'payment_method' => 'bank_transfer',
            'issued_at' => now(),
            'due_at' => now()->addWeek(),
            'paid_at' => now(),
        ]);

        $awaitingMail = (new SubscriptionInvoiceAwaitingTransfer($invoice))->toMail($user);
        $paidMail = (new SubscriptionInvoicePaid($invoice))->toMail($user);

        $this->assertSame(
            'La facture Airmius AR-2026-900002 attend ton virement',
            $awaitingMail->subject,
        );
        $this->assertSame(
            'Paiement de la facture Airmius AR-2026-900002 confirmé',
            $paidMail->subject,
        );
        $this->assertSame('Bonjour Camille,', $paidMail->greeting);
    }

    private function paidPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'slug' => 'localized-athlete-pro',
            'target_actor' => 'sportler',
            'name' => 'Athlete Pro',
            'monthly_price_cents' => 1299,
            'yearly_price_cents' => 12900,
            'currency' => 'EUR',
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        $placeholders = array_values(array_unique($matches[0] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
