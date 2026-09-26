<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\CommerceRefund;
use App\Models\CommerceReturnRequest;
use App\Models\MarketplacePayout;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CommerceRefundService
{
    public function __construct(
        private readonly CommerceAuditService $audit,
        private readonly CommerceLearningOrderService $learningOrders,
        private readonly ClubShopOrderNumberService $shopNumbers,
    ) {}

    public function refund(
        CommerceOrder $order,
        int $amountCents,
        ?string $reason = null,
        ?User $requestedBy = null,
        ?string $idempotencyKey = null,
        ?CommerceReturnRequest $returnRequest = null,
    ): CommerceRefund {
        $key = $this->normalizedIdempotencyKey(
            $idempotencyKey ?: implode(':', [
                'legacy',
                $order->id,
                $amountCents,
                trim((string) $reason),
                $requestedBy?->id ?: 'system',
            ]),
        );

        [$refund, $shouldProcess] = DB::transaction(function () use (
            $order,
            $amountCents,
            $reason,
            $requestedBy,
            $returnRequest,
            $key,
        ): array {
            $lockedOrder = CommerceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $existing = CommerceRefund::query()->where('idempotency_key', $key)->first();

            if (! in_array($lockedOrder->status, ['completed', 'cancelled', 'refunded'], true)) {
                throw ValidationException::withMessages([
                    'amount_cents' => __('commerce.validation.refund_paid_order_required'),
                ]);
            }

            if ($existing && (int) $existing->commerce_order_id !== (int) $lockedOrder->id) {
                throw ValidationException::withMessages([
                    'idempotency_key' => __('commerce.validation.refund_idempotency_conflict'),
                ]);
            }

            if ($existing && (
                (int) $existing->amount_cents !== $amountCents
                || (int) ($existing->commerce_return_request_id ?: 0) !== (int) ($returnRequest?->id ?: 0)
            )) {
                throw ValidationException::withMessages([
                    'idempotency_key' => __('commerce.validation.refund_idempotency_conflict'),
                ]);
            }

            if ($existing?->status === 'succeeded') {
                return [$existing, false];
            }

            $reservedCents = CommerceRefund::query()
                ->where('commerce_order_id', $lockedOrder->id)
                ->where('status', 'processing')
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                ->sum('amount_cents');
            $remainingCents = max(0, (int) $lockedOrder->amount_cents - (int) $lockedOrder->refunded_cents - (int) $reservedCents);

            if ($amountCents < 1 || $amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount_cents' => __('commerce.validation.refund_exceeds_remaining'),
                ]);
            }

            $values = [
                'commerce_order_id' => $lockedOrder->id,
                'commerce_return_request_id' => $returnRequest?->id,
                'requested_by' => $requestedBy?->id,
                'amount_cents' => $amountCents,
                'currency' => strtoupper((string) ($lockedOrder->currency ?: 'EUR')),
                'provider' => $lockedOrder->provider,
                'status' => 'processing',
                'reason' => filled($reason) ? trim((string) $reason) : null,
                'failure_message' => null,
                'processed_at' => null,
            ];

            if ($existing) {
                $existing->forceFill($values)->save();

                return [$existing->fresh(), true];
            }

            return [CommerceRefund::query()->create([...$values, 'idempotency_key' => $key]), true];
        });

        if (! $shouldProcess) {
            return $refund;
        }

        try {
            $providerRefundId = $this->refundViaProvider($order->fresh(), $refund);
        } catch (Throwable $exception) {
            CommerceRefund::query()
                ->whereKey($refund->id)
                ->where('status', 'processing')
                ->update([
                    'status' => 'failed',
                    'failure_message' => Str::limit($exception->getMessage(), 500, ''),
                    'updated_at' => now(),
                ]);

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'provider' => __('commerce.validation.refund_provider_failed'),
            ]);
        }

        $finalizedNow = false;
        $refund = DB::transaction(function () use ($refund, $providerRefundId, $requestedBy, &$finalizedNow): CommerceRefund {
            $lockedRefund = CommerceRefund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($lockedRefund->status === 'succeeded') {
                return $lockedRefund;
            }

            $lockedOrder = CommerceOrder::query()->lockForUpdate()->findOrFail($lockedRefund->commerce_order_id);
            $before = $lockedOrder->only([
                'status',
                'issue_status',
                'refunded_cents',
                'refund_provider_id',
                'payout_status',
            ]);
            $newRefundedCents = (int) $lockedOrder->refunded_cents + (int) $lockedRefund->amount_cents;
            $fullyRefunded = $newRefundedCents >= (int) $lockedOrder->amount_cents;
            [$payoutImpactCents, $payoutImpactStatus] = $this->reconcilePayout($lockedOrder, $lockedRefund);

            $creditNoteNumber = $lockedOrder->credit_note_number ?: $this->shopNumbers->assign(
                $lockedOrder,
                'shop_credit_note',
                fn () => 'AIR-GS-'.now()->format('Y').'-R'.str_pad((string) $lockedRefund->id, 6, '0', STR_PAD_LEFT),
                $requestedBy,
            );
            $lockedOrder->forceFill([
                'status' => $fullyRefunded ? 'refunded' : $lockedOrder->status,
                'issue_status' => 'refunded',
                'issue_note' => $lockedRefund->reason ?: $lockedOrder->issue_note,
                'refunded_cents' => min((int) $lockedOrder->amount_cents, $newRefundedCents),
                'refund_provider_id' => $providerRefundId,
                'credit_note_number' => $creditNoteNumber,
            ])->save();

            $lockedRefund->forceFill([
                'provider_refund_id' => $providerRefundId,
                'status' => 'succeeded',
                'failure_message' => null,
                'payout_impact_cents' => $payoutImpactCents,
                'payout_impact_status' => $payoutImpactStatus,
                'processed_at' => now(),
            ])->save();
            $finalizedNow = true;

            if ($fullyRefunded) {
                $this->learningOrders->revokeAccessForOrder($lockedOrder, 'refunded');
            }

            $this->audit->log(
                'order.refunded',
                $lockedOrder,
                $before,
                $lockedOrder->fresh()->only([
                    'status',
                    'issue_status',
                    'refunded_cents',
                    'refund_provider_id',
                    'payout_status',
                ]),
                $lockedRefund->reason,
            );

            return $lockedRefund->fresh();
        });

        $freshOrder = $order->fresh('user');
        if ($finalizedNow && $freshOrder?->user) {
            AppNotification::sendLocalized(
                $freshOrder->user,
                'commerce.order.refunded',
                'commerce.notifications.refunded_title',
                'commerce.notifications.refunded_body',
                [
                    'id' => $freshOrder->id,
                    'amount' => number_format($refund->amount_cents / 100, 2, ',', '.').' '.$refund->currency,
                ],
                [
                    'url' => route('auth.commerce.index', ['tab' => 'invoices', 'order' => $freshOrder->id]),
                    'order_id' => $freshOrder->id,
                    'refund_id' => $refund->id,
                ],
            );
        }

        return $refund;
    }

    private function refundViaProvider(CommerceOrder $order, CommerceRefund $refund): string
    {
        if ($order->provider === 'stripe') {
            $secret = (string) config('services.stripe.secret');
            $paymentIntent = data_get($order->payload, 'payment_intent')
                ?: data_get($order->payload, 'data.object.payment_intent');

            if (blank($secret) || blank($paymentIntent)) {
                throw ValidationException::withMessages([
                    'provider' => __('commerce.validation.refund_provider_unavailable'),
                ]);
            }

            $response = Http::asForm()
                ->withToken($secret)
                ->withHeaders(['Idempotency-Key' => $refund->idempotency_key])
                ->post('https://api.stripe.com/v1/refunds', [
                    'payment_intent' => $paymentIntent,
                    'amount' => $refund->amount_cents,
                    'metadata[commerce_order_id]' => (string) $order->id,
                    'metadata[commerce_refund_id]' => (string) $refund->id,
                ]);

            if (! $response->successful() || blank($response->json('id'))) {
                throw new \RuntimeException('Stripe refund request failed.');
            }

            return (string) $response->json('id');
        }

        if ($order->provider === 'paypal') {
            $clientId = (string) config('services.paypal.client_id');
            $clientSecret = (string) config('services.paypal.client_secret');
            $captureId = data_get($order->payload, 'purchase_units.0.payments.captures.0.id')
                ?: data_get($order->payload, 'resource.id');

            if (blank($clientId) || blank($clientSecret) || blank($captureId)) {
                throw ValidationException::withMessages([
                    'provider' => __('commerce.validation.refund_provider_unavailable'),
                ]);
            }

            $tokenResponse = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $tokenResponse->successful() || blank($tokenResponse->json('access_token'))) {
                throw new \RuntimeException('PayPal authentication failed.');
            }

            $response = Http::withToken((string) $tokenResponse->json('access_token'))
                ->withHeaders(['PayPal-Request-Id' => substr($refund->idempotency_key, 0, 38)])
                ->post($this->paypalBaseUrl().'/v2/payments/captures/'.$captureId.'/refund', [
                    'amount' => [
                        'currency_code' => $refund->currency,
                        'value' => number_format($refund->amount_cents / 100, 2, '.', ''),
                    ],
                    'invoice_id' => 'refund-'.$refund->id,
                ]);

            if (! $response->successful() || blank($response->json('id'))) {
                throw new \RuntimeException('PayPal refund request failed.');
            }

            return (string) $response->json('id');
        }

        if (! in_array($order->provider, ['bank_transfer', 'manual'], true)) {
            throw ValidationException::withMessages([
                'provider' => __('commerce.validation.refund_provider_unavailable'),
            ]);
        }

        return 'manual-'.$order->id.'-'.$refund->id;
    }

    /** @return array{int, string} */
    private function reconcilePayout(CommerceOrder $order, CommerceRefund $refund): array
    {
        if (! in_array($order->type, ['marketplace_product', 'marketplace_cart'], true)) {
            return [0, 'not_applicable'];
        }

        $alreadyReversedCommission = CommerceRefund::query()
            ->where('commerce_order_id', $order->id)
            ->where('status', 'succeeded')
            ->get(['amount_cents', 'payout_impact_cents'])
            ->sum(fn (CommerceRefund $item) => max(0, $item->amount_cents - $item->payout_impact_cents));
        $proportionalCommission = $order->amount_cents > 0
            ? (int) round($order->commission_cents * ($refund->amount_cents / $order->amount_cents))
            : 0;
        $reversedCommission = min(
            max(0, (int) $order->commission_cents - (int) $alreadyReversedCommission),
            max(0, $proportionalCommission),
        );
        $sellerImpactCents = max(0, (int) $refund->amount_cents - $reversedCommission);
        $payout = $order->payout_id
            ? MarketplacePayout::query()->lockForUpdate()->find($order->payout_id)
            : null;

        if (! $payout) {
            if ($order->payout_status !== 'not_applicable') {
                $order->payout_status = 'refund_hold';
            }

            return [$sellerImpactCents, 'withheld'];
        }

        if ($payout->status === 'paid' || $payout->reconciliation_status === 'seller_recovery_required') {
            $payout->forceFill([
                'recovery_cents' => (int) $payout->recovery_cents + $sellerImpactCents,
                'reconciliation_status' => 'seller_recovery_required',
            ])->save();
            $order->payout_status = 'recovery_required';

            return [$sellerImpactCents, 'seller_recovery_required'];
        }

        $newGrossCents = max(0, (int) $payout->gross_cents - (int) $refund->amount_cents);
        $newCommissionCents = max(0, (int) $payout->commission_cents - $reversedCommission);
        $newAmountCents = max(0, (int) $payout->amount_cents - $sellerImpactCents);
        $payout->forceFill([
            'gross_cents' => $newGrossCents,
            'commission_cents' => $newCommissionCents,
            'amount_cents' => $newAmountCents,
            'adjustment_cents' => (int) $payout->adjustment_cents + $sellerImpactCents,
            'reconciliation_status' => 'adjusted_before_payment',
            'status' => $newAmountCents === 0 ? 'cancelled' : $payout->status,
        ])->save();
        $order->payout_status = 'adjusted';

        return [$sellerImpactCents, 'payout_adjusted'];
    }

    private function normalizedIdempotencyKey(string $key): string
    {
        return hash('sha256', trim($key));
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
