<?php

namespace App\Services;

use App\Http\Resources\Api\V1\ClubMembershipRequestResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\CommerceRefund;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\BillingOverview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class MemberPortalOverviewService
{
    public function overview(User $user, Request $request): array
    {
        $clubInvoices = $this->clubInvoicesQuery($user)
            ->latest('id')
            ->limit(30)
            ->get();
        $subscriptionInvoices = $this->subscriptionInvoicesQuery($user)
            ->latest('id')
            ->limit(30)
            ->get();
        $payments = $this->paymentsQuery($user)
            ->latest('id')
            ->limit(30)
            ->get();

        return [
            'memberships' => $this->memberships($user),
            'billing' => [
                'summary' => BillingOverview::memberBillingSummary($clubInvoices, $subscriptionInvoices, $payments),
                'invoices' => InvoiceResource::collection($clubInvoices)->resolve($request),
                'payments' => PaymentResource::collection($payments)->resolve($request),
                'subscription_invoices' => $this->subscriptionInvoicePayloads($subscriptionInvoices),
            ],
            'requests' => ClubMembershipRequestResource::collection(
                $this->membershipRequestsQuery($user)->latest('id')->limit(30)->get()
            )->resolve($request),
            'refunds' => $this->refundPayloads($this->refundsQuery($user)->latest('id')->limit(30)->get()),
            'cancellations' => $this->cancellations($user),
        ];
    }

    public function detail(User $user, Request $request, string $section): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        return match ($section) {
            'memberships' => $this->paginateCollection($this->memberships($user), $perPage),
            'invoices' => $this->paginateInvoices($user, $perPage),
            'payments' => $this->paymentsQuery($user)->latest('id')->paginate($perPage),
            'requests' => $this->membershipRequestsQuery($user)->latest('id')->paginate($perPage),
            'refunds' => $this->refundsQuery($user)->latest('id')->paginate($perPage),
            'cancellations' => $this->paginateCollection($this->cancellations($user), $perPage),
            default => abort(404),
        };
    }

    public function transformDetail(LengthAwarePaginator $items, Request $request, string $section): LengthAwarePaginator
    {
        $items->setCollection($items->getCollection()->map(function ($item) use ($request, $section) {
            return match ($section) {
                'invoices' => Arr::except($item, ['sort_at']),
                'payments' => (new PaymentResource($item))->resolve($request),
                'requests' => (new ClubMembershipRequestResource($item))->resolve($request),
                'refunds' => $this->refundPayload($item),
                default => $item,
            };
        }));

        return $items;
    }

    public function invoice(User $user, int $invoice): array
    {
        $clubInvoice = $this->clubInvoicesQuery($user)->find($invoice);

        if ($clubInvoice) {
            return Arr::except($this->invoicePayload($clubInvoice), ['sort_at']);
        }

        $subscriptionInvoice = $this->subscriptionInvoicesQuery($user)->findOrFail($invoice);

        return Arr::except($this->subscriptionInvoicePayload($subscriptionInvoice), ['sort_at']);
    }

    public function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'kind' => 'club_invoice',
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'amount' => $invoice->amount,
            ...$invoice->balancePayload(),
            'amount_cents' => (int) round(((float) $invoice->amount) * 100),
            'currency' => 'EUR',
            'status' => $invoice->status,
            'status_label' => $invoice->statusLabel(),
            'source' => $invoice->source,
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'issued_at' => $invoice->issued_at?->toJSON(),
            'due_at' => $invoice->due_date?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'club' => $invoice->relationLoaded('club') && $invoice->club ? [
                'id' => $invoice->club->id,
                'name' => $invoice->club->name,
            ] : null,
            'created_at' => $invoice->created_at?->toJSON(),
            'updated_at' => $invoice->updated_at?->toJSON(),
            'sort_at' => ($invoice->issued_at ?? $invoice->created_at)?->timestamp ?? 0,
        ];
    }

    public function subscriptionInvoicePayload(SubscriptionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'kind' => 'subscription_invoice',
            'payment_checkout_id' => $invoice->payment_checkout_id,
            'user_id' => $invoice->user_id,
            'club_id' => $invoice->club_id,
            'subscription_plan_id' => $invoice->subscription_plan_id,
            'subscription_type' => $invoice->subscription_type,
            'subscription_id' => $invoice->subscription_id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'amount_cents' => $invoice->amount_cents,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'status_label' => BillingOverview::statusLabel($invoice->status),
            'payment_method' => $invoice->payment_method,
            'payment_reference' => $invoice->payment_reference,
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'issued_at' => $invoice->issued_at?->toJSON(),
            'due_at' => $invoice->due_at?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'club' => $invoice->relationLoaded('club') && $invoice->club ? [
                'id' => $invoice->club->id,
                'name' => $invoice->club->name,
            ] : null,
            'created_at' => $invoice->created_at?->toJSON(),
            'updated_at' => $invoice->updated_at?->toJSON(),
            'sort_at' => ($invoice->issued_at ?? $invoice->created_at)?->timestamp ?? 0,
        ];
    }

    private function clubInvoicesQuery(User $user): Builder
    {
        return Invoice::query()
            ->withSum('settledPayments', 'amount')
            ->where('user_id', $user->id)
            ->with('club');
    }

    private function subscriptionInvoicesQuery(User $user): Builder
    {
        return SubscriptionInvoice::query()
            ->where('user_id', $user->id)
            ->with(['club', 'plan', 'checkout']);
    }

    private function paymentsQuery(User $user): Builder
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->with(['club', 'invoice']);
    }

    private function membershipRequestsQuery(User $user): Builder
    {
        return ClubMembershipRequest::query()
            ->where('user_id', $user->id)
            ->with(['club', 'membershipType:id,name', 'department:id,name']);
    }

    private function refundsQuery(User $user): Builder
    {
        return CommerceRefund::query()
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with('order:id,user_id,club_id,type,invoice_number,status,amount_cents,currency');
    }

    private function memberships(User $user): Collection
    {
        return $user->clubs()
            ->with(['membershipTypes:id,club_id,name', 'departments:id,club_id,name'])
            ->orderBy('clubs.name')
            ->get()
            ->map(fn (Club $club) => [
                'club' => [
                    'id' => $club->id,
                    'name' => $club->name,
                ],
                'membership' => [
                    'status' => $club->pivot->membership_status,
                    'role' => $club->pivot->role,
                    'member_number' => $club->pivot->member_number,
                    'joined_on' => $club->pivot->joined_on,
                    'membership_ends_on' => $club->pivot->membership_ends_on,
                    'membership_ended_at' => $club->pivot->membership_ended_at,
                    'contribution_amount' => $club->pivot->contribution_amount,
                    'contribution_interval' => $club->pivot->contribution_interval,
                    'contribution_next_invoice_on' => $club->pivot->contribution_next_invoice_on,
                    'payment_method' => $club->pivot->payment_method,
                    'sepa_mandate_active' => (bool) $club->pivot->sepa_mandate_active,
                ],
                'tariff' => $club->membershipTypes->firstWhere('id', $club->pivot->club_membership_type_id)?->only(['id', 'name']),
                'department' => $club->departments->firstWhere('id', $club->pivot->club_department_id)?->only(['id', 'name']),
            ]);
    }

    private function cancellations(User $user): Collection
    {
        return $this->membershipRequestsQuery($user)
            ->where('type', 'termination')
            ->latest('id')
            ->get()
            ->map(fn (ClubMembershipRequest $request) => [
                'id' => $request->id,
                'club_id' => $request->club_id,
                'club' => $request->club ? ['id' => $request->club->id, 'name' => $request->club->name] : null,
                'status' => $request->status,
                'requested_termination_on' => $request->requested_termination_on?->toDateString(),
                'termination_reason' => $request->termination_reason,
                'submitted_at' => $request->created_at?->toJSON(),
                'reviewed_at' => $request->reviewed_at?->toJSON(),
            ]);
    }

    private function paginateInvoices(User $user, int $perPage): LengthAwarePaginator
    {
        $clubInvoices = $this->clubInvoicesQuery($user)->latest('id')->get();
        $subscriptionInvoices = $this->subscriptionInvoicesQuery($user)->latest('id')->get();
        $items = collect()
            ->merge($clubInvoices->map(fn (Invoice $invoice) => $this->invoicePayload($invoice)))
            ->merge($subscriptionInvoices->map(fn (SubscriptionInvoice $invoice) => $this->subscriptionInvoicePayload($invoice)))
            ->sortByDesc('sort_at')
            ->values();

        return $this->paginateCollection($items, $perPage);
    }

    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = max((int) request()->integer('page', 1), 1);

        return new Paginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    private function subscriptionInvoicePayloads(iterable $invoices): Collection
    {
        return collect($invoices)->map(fn (SubscriptionInvoice $invoice) => $this->subscriptionInvoicePayload($invoice))->values();
    }

    private function refundPayloads(iterable $refunds): Collection
    {
        return collect($refunds)->map(fn (CommerceRefund $refund) => $this->refundPayload($refund))->values();
    }

    private function refundPayload(CommerceRefund $refund): array
    {
        return [
            'id' => $refund->id,
            'commerce_order_id' => $refund->commerce_order_id,
            'amount_cents' => $refund->amount_cents,
            'currency' => $refund->currency,
            'provider' => $refund->provider,
            'status' => $refund->status,
            'reason' => $refund->reason,
            'processed_at' => $refund->processed_at?->toJSON(),
            'order' => $refund->order ? [
                'id' => $refund->order->id,
                'club_id' => $refund->order->club_id,
                'type' => $refund->order->type,
                'invoice_number' => $refund->order->invoice_number,
                'status' => $refund->order->status,
            ] : null,
            'created_at' => $refund->created_at?->toJSON(),
            'updated_at' => $refund->updated_at?->toJSON(),
        ];
    }
}
