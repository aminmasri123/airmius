<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplacePayoutService
{
    public const ELIGIBILITY_DAYS = 14;

    public function __construct(
        private readonly CommerceAuditService $audit,
    ) {}

    /**
     * The single source of truth for sales that may enter a payout.
     */
    public function eligibleOrdersQuery(User $seller): Builder
    {
        return $this->eligibleMarketplaceOrdersQuery()
            ->where(fn (Builder $query) => $this->scopeSellerOrders($query, $seller->id));
    }

    /** @return array<string, int|string|bool> */
    public function summary(User $seller): array
    {
        $pending = $this->sellerPendingOrdersQuery($seller)
            ->selectRaw('COUNT(*) as orders_count')
            ->first();
        $eligible = $this->eligibleOrdersQuery($seller)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(amount_cents), 0) as gross_cents, COALESCE(SUM(commission_cents), 0) as commission_cents, MIN(currency) as currency, COUNT(DISTINCT currency) as currencies_count')
            ->first();
        $requested = MarketplacePayout::query()
            ->where('user_id', $seller->id)
            ->whereIn('status', ['requested', 'prepared'])
            ->selectRaw('COUNT(*) as payouts_count, COALESCE(SUM(amount_cents), 0) as amount_cents, MIN(currency) as currency, COUNT(DISTINCT currency) as currencies_count')
            ->first();

        $pendingCount = (int) ($pending?->orders_count ?? 0);
        $eligibleCount = (int) ($eligible?->orders_count ?? 0);
        $grossCents = (int) ($eligible?->gross_cents ?? 0);
        $commissionCents = (int) ($eligible?->commission_cents ?? 0);
        $eligibleCurrencies = (int) ($eligible?->currencies_count ?? 0);
        $requestedCurrencies = (int) ($requested?->currencies_count ?? 0);
        $currencies = collect([
            $eligibleCurrencies === 1 ? strtoupper((string) $eligible?->currency) : null,
            $requestedCurrencies === 1 ? strtoupper((string) $requested?->currency) : null,
        ])->filter()->unique();
        $hasMixedCurrencies = $eligibleCurrencies > 1
            || $requestedCurrencies > 1
            || $currencies->count() > 1;

        return [
            'pending_orders' => $pendingCount,
            'eligible_orders' => $eligibleCount,
            'waiting_orders' => max(0, $pendingCount - $eligibleCount),
            'gross_cents' => $grossCents,
            'commission_cents' => $commissionCents,
            'amount_cents' => max(0, $grossCents - $commissionCents),
            'requested_cents' => (int) ($requested?->amount_cents ?? 0),
            'requested_count' => (int) ($requested?->payouts_count ?? 0),
            'currency' => $currencies->first() ?: 'EUR',
            'has_mixed_currencies' => $hasMixedCurrencies,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function candidates(): array
    {
        $orders = $this->eligibleMarketplaceOrdersQuery()
            ->with(['orderable.user:id,name,email', 'items.orderable.user:id,name,email'])
            ->where(function (Builder $query) {
                $query
                    ->whereHasMorph('orderable', [MarketplaceProduct::class], fn (Builder $product) => $product->whereNotNull('user_id'))
                    ->orWhereHas('items', fn (Builder $items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn (Builder $product) => $product->whereNotNull('user_id')));
            })
            ->lazyById(250);

        $candidates = [];

        foreach ($orders as $order) {
            $sellerIds = $this->sellerIdsFor($order);
            if ($sellerIds->count() !== 1) {
                continue;
            }

            $sellerId = (int) $sellerIds->first();
            $seller = $order->orderable?->user
                ?: $order->items->first(fn ($item) => $item->orderable instanceof MarketplaceProduct)?->orderable?->user;
            $currency = strtoupper((string) ($order->currency ?: 'EUR'));

            $candidates[$sellerId] ??= [
                'user_id' => $sellerId,
                'name' => $seller?->name,
                'email' => $seller?->email,
                'orders_count' => 0,
                'gross_cents' => 0,
                'commission_cents' => 0,
                'currencies' => [],
            ];
            $candidates[$sellerId]['orders_count']++;
            $candidates[$sellerId]['gross_cents'] += (int) $order->amount_cents;
            $candidates[$sellerId]['commission_cents'] += (int) $order->commission_cents;
            $candidates[$sellerId]['currencies'][$currency] = true;
        }

        ksort($candidates);

        return collect($candidates)
            ->map(function (array $candidate) {
                $currencies = array_keys($candidate['currencies']);
                unset($candidate['currencies']);

                return [
                    ...$candidate,
                    'amount_cents' => max(0, $candidate['gross_cents'] - $candidate['commission_cents']),
                    'currency' => count($currencies) === 1 ? $currencies[0] : null,
                    'has_mixed_currencies' => count($currencies) > 1,
                ];
            })
            ->values()
            ->all();
    }

    public function create(
        User $seller,
        string $method,
        ?string $notes = null,
        string $status = 'requested',
    ): MarketplacePayout {
        if (! in_array($status, ['requested', 'prepared'], true)) {
            throw ValidationException::withMessages([
                'status' => __('commerce.validation.payout_not_payable'),
            ]);
        }

        $payout = DB::transaction(function () use ($seller, $method, $notes, $status) {
            $orders = $this->eligibleOrdersQuery($seller)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'payout' => __('commerce.validation.no_payout_orders'),
                ]);
            }

            $currencies = $orders
                ->pluck('currency')
                ->map(fn ($currency) => strtoupper((string) ($currency ?: 'EUR')))
                ->unique()
                ->values();

            if ($currencies->count() !== 1) {
                throw ValidationException::withMessages([
                    'payout' => __('commerce.validation.payout_mixed_currencies'),
                ]);
            }

            $grossCents = (int) $orders->sum('amount_cents');
            $commissionCents = (int) $orders->sum('commission_cents');
            $amountCents = $grossCents - $commissionCents;

            if ($amountCents < 0) {
                throw ValidationException::withMessages([
                    'payout' => __('commerce.validation.payout_amount_invalid'),
                ]);
            }

            $payout = MarketplacePayout::query()->create([
                'user_id' => $seller->id,
                'currency' => $currencies->first(),
                'gross_cents' => $grossCents,
                'commission_cents' => $commissionCents,
                'amount_cents' => $amountCents,
                'method' => $method,
                'status' => $status,
                'notes' => $notes,
            ]);

            $payout->update([
                'reference' => 'AIR-PAY-'.$payout->created_at->format('Y').'-'.str_pad((string) $payout->id, 6, '0', STR_PAD_LEFT),
            ]);

            $assigned = CommerceOrder::query()
                ->whereIn('id', $orders->pluck('id'))
                ->where('payout_status', 'pending')
                ->whereNull('payout_id')
                ->update([
                    'payout_id' => $payout->id,
                    'payout_status' => $status,
                ]);

            if ($assigned !== $orders->count()) {
                throw ValidationException::withMessages([
                    'payout' => __('commerce.validation.payout_concurrent_change'),
                ]);
            }

            $this->audit->log(
                'payout.'.$status,
                $payout,
                [],
                $payout->fresh()->only([
                    'user_id',
                    'currency',
                    'gross_cents',
                    'commission_cents',
                    'amount_cents',
                    'method',
                    'status',
                    'reference',
                ]),
                $notes,
            );

            return $payout->fresh(['user'])->loadCount('orders');
        }, 3);

        if ($status === 'prepared') {
            AppNotification::sendLocalized(
                $payout->user,
                'commerce.payout.prepared',
                'commerce.notifications.payout_prepared_title',
                'commerce.notifications.payout_prepared_body',
                [
                    'reference' => $payout->reference,
                    'amount' => $this->formattedAmount($payout),
                ],
                [
                    'url' => route('auth.commerce.index'),
                    'payout_id' => $payout->id,
                ],
            );
        }

        return $payout;
    }

    public function markPaid(MarketplacePayout $payout, ?string $notes = null): MarketplacePayout
    {
        $paid = DB::transaction(function () use ($payout, $notes) {
            $lockedPayout = MarketplacePayout::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($payout->id);

            if (! in_array($lockedPayout->status, ['requested', 'prepared'], true)) {
                throw ValidationException::withMessages([
                    'payout' => __('commerce.validation.payout_not_payable'),
                ]);
            }

            if ((int) $lockedPayout->recovery_cents > 0 || $lockedPayout->reconciliation_status === 'seller_recovery_required') {
                throw ValidationException::withMessages([
                    'payout' => __('commerce.validation.payout_reconciliation_required'),
                ]);
            }

            $before = $lockedPayout->only(['status', 'paid_at', 'notes']);
            $lockedPayout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => $notes ?? $lockedPayout->notes,
            ]);

            CommerceOrder::query()
                ->where('payout_id', $lockedPayout->id)
                ->update(['payout_status' => 'paid']);

            $this->audit->log(
                'payout.paid',
                $lockedPayout,
                $before,
                $lockedPayout->fresh()->only(['status', 'paid_at', 'notes']),
                $notes,
            );

            return $lockedPayout->fresh(['user'])->loadCount('orders');
        }, 3);

        AppNotification::sendLocalized(
            $paid->user,
            'commerce.payout.paid',
            'commerce.notifications.payout_paid_title',
            'commerce.notifications.payout_paid_body',
            [
                'reference' => $paid->reference,
                'amount' => $this->formattedAmount($paid),
            ],
            [
                'url' => route('auth.commerce.index'),
                'payout_id' => $paid->id,
            ],
        );

        return $paid;
    }

    private function eligibleMarketplaceOrdersQuery(): Builder
    {
        $cutoff = now()->subDays(self::ELIGIBILITY_DAYS);

        return CommerceOrder::query()
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(fn (Builder $query) => $query->whereNull('issue_status')->orWhere('issue_status', 'none'))
            ->whereDoesntHave('returnRequests')
            ->where(function (Builder $query) use ($cutoff) {
                $query
                    ->where(function (Builder $noShipping) use ($cutoff) {
                        $noShipping
                            ->whereDoesntHave('items', fn (Builder $items) => $items->where('is_shippable', true))
                            ->where('completed_at', '<=', $cutoff);
                    })
                    ->orWhere(function (Builder $shipping) use ($cutoff) {
                        $shipping
                            ->whereHas('items', fn (Builder $items) => $items->where('is_shippable', true))
                            ->where('shipping_status', 'delivered')
                            ->where('delivered_at', '<=', $cutoff);
                    });
            });
    }

    private function sellerPendingOrdersQuery(User $seller): Builder
    {
        return CommerceOrder::query()
            ->whereIn('type', ['marketplace_product', 'marketplace_cart'])
            ->where('status', 'completed')
            ->where('payout_status', 'pending')
            ->where(fn (Builder $query) => $this->scopeSellerOrders($query, $seller->id));
    }

    private function scopeSellerOrders(Builder $query, int $sellerId): void
    {
        $query
            ->where(function (Builder $owned) use ($sellerId) {
                $owned
                    ->whereHasMorph('orderable', [MarketplaceProduct::class], fn (Builder $product) => $product->where('user_id', $sellerId))
                    ->orWhereHas('items', fn (Builder $items) => $items
                        ->where('orderable_type', MarketplaceProduct::class)
                        ->whereHasMorph('orderable', [MarketplaceProduct::class], fn (Builder $product) => $product->where('user_id', $sellerId)));
            })
            ->whereDoesntHave('items', fn (Builder $items) => $items
                ->where('orderable_type', MarketplaceProduct::class)
                ->whereHasMorph('orderable', [MarketplaceProduct::class], fn (Builder $product) => $product->where('user_id', '!=', $sellerId)));
    }

    private function sellerIdsFor(CommerceOrder $order)
    {
        $sellerIds = $order->items
            ->filter(fn ($item) => $item->orderable instanceof MarketplaceProduct)
            ->map(fn ($item) => $item->orderable->user_id)
            ->filter()
            ->toBase();

        if ($order->orderable instanceof MarketplaceProduct && $order->orderable->user_id) {
            $sellerIds->push($order->orderable->user_id);
        }

        return $sellerIds->unique()->values();
    }

    private function formattedAmount(MarketplacePayout $payout): string
    {
        return number_format($payout->amount_cents / 100, 2, ',', '.').' '.$payout->currency;
    }
}
