<?php

namespace App\Services\Subscriptions;

use App\Models\PaymentCheckout;
use App\Notifications\SubscriptionInvoicePaid;
use App\Services\UserSubscriptionActivationService;
use App\Support\AppNotification;
use App\Support\SupportedLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SubscriptionCheckoutActivationService
{
    public function __construct(private readonly UserSubscriptionActivationService $userSubscriptionActivator) {}

    /** @return array{checkout: PaymentCheckout, subscription: Model|null, activated: bool} */
    public function activate(PaymentCheckout $checkout): array
    {
        $result = DB::transaction(function () use ($checkout): array {
            $locked = PaymentCheckout::query()
                ->with(['coupon', 'club', 'user', 'invoice', 'plan'])
                ->whereKey($checkout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'completed') {
                return ['checkout' => $locked, 'subscription' => null, 'activated' => false];
            }

            $locked->coupon?->increment('redeemed_count');
            $periodEndsAt = $locked->billing_interval === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            if ($locked->club_id) {
                $subscription = $locked->club->currentSubscription()->updateOrCreate(
                    ['club_id' => $locked->club_id],
                    $this->subscriptionValues($locked, $periodEndsAt),
                );
            } else {
                $subscription = $locked->user->subscriptions()->updateOrCreate(
                    ['subscription_plan_id' => $locked->subscription_plan_id],
                    $this->subscriptionValues($locked, $periodEndsAt),
                );
                $this->userSubscriptionActivator->retireOtherUserSubscriptions($subscription->fresh('plan'));
            }

            $locked->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            $locked->invoice?->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $locked->provider,
                'payment_reference' => $locked->payment_reference ?: $locked->provider_checkout_id,
                'subscription_type' => $locked->club_id ? 'club' : 'user',
                'subscription_id' => $subscription->id,
                'meta' => array_merge($locked->invoice->meta ?? [], [
                    'provider_checkout_id' => $locked->provider_checkout_id,
                    'provider_subscription_id' => $locked->provider_subscription_id,
                    'provider_customer_id' => $locked->provider_customer_id,
                ]),
            ])->save();

            return ['checkout' => $locked->fresh(['user', 'club', 'invoice', 'plan']), 'subscription' => $subscription, 'activated' => true];
        });

        if ($result['activated']) {
            $this->notifyPaid($result['checkout']);
        }

        return $result;
    }

    private function subscriptionValues(PaymentCheckout $checkout, mixed $periodEndsAt): array
    {
        return [
            'subscription_plan_id' => $checkout->subscription_plan_id,
            'status' => 'active',
            'payment_provider' => $checkout->provider,
            'billing_interval' => $checkout->billing_interval,
            'provider_subscription_id' => $checkout->provider_subscription_id,
            'provider_customer_id' => $checkout->provider_customer_id,
            'trial_ends_at' => null,
            'current_period_ends_at' => $periodEndsAt,
            'next_invoice_at' => $periodEndsAt,
            'grace_period_ends_at' => null,
            'access_restricted_at' => null,
            'cancel_at_period_end' => false,
            'cancels_at' => null,
            'cancelled_at' => null,
        ];
    }

    private function notifyPaid(PaymentCheckout $checkout): void
    {
        $invoiceName = $checkout->invoice?->number ?: AppNotification::translatedReplacement(
            'subscription.invoice.fallback_name',
            'Subscription invoice',
        );

        AppNotification::sendLocalized(
            $checkout->user,
            'subscription.invoice.paid',
            'subscription.notifications.paid_title',
            'subscription.notifications.paid_body',
            ['invoice' => $invoiceName],
            ['subscription_invoice_id' => $checkout->invoice?->id],
            ['dedupe_key' => 'subscription-invoice:'.$checkout->invoice?->id.':paid'],
        );

        if (! $checkout->invoice || $checkout->invoice->payment_confirmation_email_sent_at || ! $checkout->user?->email) {
            return;
        }

        try {
            $locale = SupportedLocale::normalize($checkout->user->language) ?? SupportedLocale::DEFAULT;
            $checkout->user->notify((new SubscriptionInvoicePaid($checkout->invoice))->locale($locale));
            $checkout->invoice->forceFill(['payment_confirmation_email_sent_at' => now()])->save();
        } catch (Throwable) {
            // Delivery must not roll back an already confirmed payment.
        }
    }
}
