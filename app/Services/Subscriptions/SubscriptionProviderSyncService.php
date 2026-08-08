<?php

namespace App\Services\Subscriptions;

use App\Models\ClubSubscription;
use App\Models\UserSubscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SubscriptionProviderSyncService
{
    public function cancel(ClubSubscription|UserSubscription $subscription, string $mode): void
    {
        if (blank($subscription->provider_subscription_id)) {
            return;
        }

        match ($subscription->payment_provider) {
            'stripe' => $this->cancelStripe($subscription, $mode),
            'paypal' => $this->cancelPayPal($subscription),
            default => null,
        };
    }

    public function reinstate(ClubSubscription|UserSubscription $subscription): bool
    {
        if (blank($subscription->provider_subscription_id)) {
            return true;
        }

        return match ($subscription->payment_provider) {
            'stripe' => $this->reinstateStripe($subscription),
            'paypal' => $this->reinstatePayPal($subscription),
            default => true,
        };
    }

    private function cancelStripe(ClubSubscription|UserSubscription $subscription, string $mode): void
    {
        if (blank(config('services.stripe.secret'))) {
            return;
        }

        try {
            $request = $this->stripeRequest();
            $response = $mode === 'period_end'
                ? $request->post($this->stripeSubscriptionUrl($subscription), ['cancel_at_period_end' => 'true'])
                : $request->delete($this->stripeSubscriptionUrl($subscription));

            if ($response->failed()) {
                $this->logFailure('Stripe subscription cancellation failed.', $subscription, $response->status(), $response->json());
            }
        } catch (Throwable $exception) {
            $this->logException('Stripe subscription cancellation request failed.', $subscription, $exception);
        }
    }

    private function reinstateStripe(ClubSubscription|UserSubscription $subscription): bool
    {
        if (blank(config('services.stripe.secret'))) {
            return false;
        }

        try {
            $response = $this->stripeRequest()->post($this->stripeSubscriptionUrl($subscription), [
                'cancel_at_period_end' => 'false',
            ]);

            if ($response->failed()) {
                $this->logFailure('Stripe subscription reinstatement failed.', $subscription, $response->status(), $response->json());

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            $this->logException('Stripe subscription reinstatement request failed.', $subscription, $exception);

            return false;
        }
    }

    private function cancelPayPal(ClubSubscription|UserSubscription $subscription): void
    {
        $this->postPayPalAction($subscription, 'cancel', 'Airmius subscription cancelled by the customer.');
    }

    private function reinstatePayPal(ClubSubscription|UserSubscription $subscription): bool
    {
        return $this->postPayPalAction($subscription, 'activate', 'Airmius subscription reactivated by the customer.');
    }

    private function postPayPalAction(
        ClubSubscription|UserSubscription $subscription,
        string $action,
        string $reason,
    ): bool {
        if (blank(config('services.paypal.client_id')) || blank(config('services.paypal.client_secret'))) {
            return false;
        }

        try {
            $tokenResponse = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
                ->post($this->payPalBaseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($tokenResponse->failed() || blank($tokenResponse->json('access_token'))) {
                $this->logFailure('PayPal subscription token request failed.', $subscription, $tokenResponse->status(), $tokenResponse->json());

                return false;
            }

            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->withToken($tokenResponse->json('access_token'))
                ->withBody(json_encode(['reason' => $reason], JSON_THROW_ON_ERROR), 'application/json')
                ->post($this->payPalBaseUrl().'/v1/billing/subscriptions/'.$subscription->provider_subscription_id.'/'.$action);

            if ($response->failed()) {
                $this->logFailure("PayPal subscription {$action} failed.", $subscription, $response->status(), $response->json());

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            $this->logException("PayPal subscription {$action} request failed.", $subscription, $exception);

            return false;
        }
    }

    private function stripeRequest(): PendingRequest
    {
        return Http::asForm()
            ->connectTimeout(3)
            ->timeout(8)
            ->withToken(config('services.stripe.secret'));
    }

    private function stripeSubscriptionUrl(ClubSubscription|UserSubscription $subscription): string
    {
        return 'https://api.stripe.com/v1/subscriptions/'.$subscription->provider_subscription_id;
    }

    private function payPalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function logFailure(
        string $message,
        ClubSubscription|UserSubscription $subscription,
        int $status,
        mixed $body,
    ): void {
        Log::warning($message, [
            'subscription_type' => $subscription instanceof ClubSubscription ? 'club' : 'user',
            'subscription_id' => $subscription->id,
            'payment_provider' => $subscription->payment_provider,
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'status' => $status,
            'body' => $body,
        ]);
    }

    private function logException(
        string $message,
        ClubSubscription|UserSubscription $subscription,
        Throwable $exception,
    ): void {
        Log::warning($message, [
            'subscription_type' => $subscription instanceof ClubSubscription ? 'club' : 'user',
            'subscription_id' => $subscription->id,
            'payment_provider' => $subscription->payment_provider,
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'message' => $exception->getMessage(),
        ]);
    }
}
