<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OperatingContractController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SubscriptionInvoiceController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\Invoice;
use App\Models\OperatingContract;
use App\Models\Payment;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class AdminBackofficeController extends Controller
{
    public function dashboard(Request $request)
    {
        $abilities = $this->abilities($request);
        abort_unless(in_array(true, $abilities, true), 403);

        $plans = collect();
        $userSubscriptions = collect();
        $clubSubscriptions = collect();
        $checkouts = collect();
        if ($abilities['subscriptions_manage']) {
            $plans = SubscriptionPlan::query()
                ->with('countryPrices')
                ->withCount(['clubSubscriptions', 'userSubscriptions'])
                ->orderBy('sort_order')
                ->get();
            $userSubscriptions = UserSubscription::query()
                ->with(['user:id,name,email', 'plan:id,name,target_actor'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (UserSubscription $subscription) => $this->userSubscriptionPayload($subscription));
            $clubSubscriptions = ClubSubscription::query()
                ->with(['club:id,name', 'plan:id,name,target_actor'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (ClubSubscription $subscription) => $this->clubSubscriptionPayload($subscription));
            $checkouts = PaymentCheckout::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
                ->where('provider', 'bank_transfer')
                ->where('status', 'awaiting_transfer')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (PaymentCheckout $checkout) => $this->checkoutPayload($checkout));
        }

        $subscriptionInvoices = collect();
        if ($abilities['subscriptions_manage'] || $abilities['billing_manage']) {
            $subscriptionInvoices = SubscriptionInvoice::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (SubscriptionInvoice $invoice) => $this->subscriptionInvoicePayload($invoice));
        }

        $payments = collect();
        $invoices = collect();
        if ($abilities['billing_manage']) {
            $payments = Payment::query()
                ->with(['club:id,name', 'user:id,name,email', 'invoice:id,number,title,status'])
                ->latest('paid_at')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Payment $payment) => $this->paymentPayload($payment));
            $invoices = Invoice::query()
                ->with(['club:id,name', 'user:id,name,email'])
                ->withSum('payments as paid_amount', 'amount')
                ->latest('issued_at')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Invoice $invoice) => $this->invoicePayload($invoice));
        }

        $contracts = collect();
        if ($abilities['finance_view'] || $abilities['finance_edit'] || $abilities['billing_manage']) {
            $contracts = OperatingContract::query()
                ->with('owner:id,name,email')
                ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 when 'cancelled' then 2 else 3 end")
                ->orderByRaw('next_due_on is null')
                ->orderBy('next_due_on')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (OperatingContract $contract) => $this->contractPayload($contract));
        }

        return response()->json([
            'data' => [
                'summary' => [
                    'plans' => $plans->count(),
                    'active_user_subscriptions' => $userSubscriptions->where('status', 'active')->count(),
                    'active_club_subscriptions' => $clubSubscriptions->where('status', 'active')->count(),
                    'pending_transfers' => $checkouts->count(),
                    'open_subscription_invoices' => $subscriptionInvoices
                        ->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])
                        ->count(),
                    'subscription_revenue_cents' => (int) $subscriptionInvoices
                        ->where('status', 'paid')
                        ->sum('amount_cents'),
                    'payments' => $payments->count(),
                    'payment_revenue_cents' => (int) round(
                        $payments->where('status', 'paid')->sum('amount') * 100
                    ),
                    'open_invoices' => $invoices
                        ->whereIn('status', ['open', 'pending', 'overdue'])
                        ->count(),
                    'contracts' => $contracts->count(),
                    'monthly_contract_cost_cents' => (int) round(
                        $contracts
                            ->where('status', 'active')
                            ->sum('monthly_equivalent') * 100
                    ),
                ],
                'abilities' => $abilities,
                'plans' => $plans,
                'user_subscriptions' => $userSubscriptions,
                'club_subscriptions' => $clubSubscriptions,
                'pending_transfers' => $checkouts,
                'subscription_invoices' => $subscriptionInvoices,
                'payments' => $payments,
                'invoices' => $invoices,
                'contracts' => $contracts,
                'users' => ($abilities['subscriptions_manage'] || $abilities['billing_manage'] || $abilities['finance_edit'])
                    ? User::query()->orderBy('name')->limit(250)->get(['id', 'name', 'email'])
                    : [],
                'clubs' => ($abilities['subscriptions_manage'] || $abilities['billing_manage'])
                    ? Club::query()->orderBy('name')->limit(250)->get(['id', 'name'])
                    : [],
                'options' => [
                    'invoice_sources' => [
                        'account_subscription',
                        'outfit_subscription_manual',
                        'marketplace_purchase',
                        'elearning',
                        'ads',
                        'agency_website',
                        'agency_logo',
                        'agency_branding',
                        'sponsorship',
                        'custom',
                    ],
                    'contract_statuses' => OperatingContract::STATUSES,
                    'contract_categories' => OperatingContract::CATEGORIES,
                    'billing_intervals' => OperatingContract::BILLING_INTERVALS,
                    'payment_methods' => OperatingContract::PAYMENT_METHODS,
                ],
            ],
        ]);
    }

    public function updatePlan(
        Request $request,
        SubscriptionPlan $subscriptionPlan
    ) {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->update($request, $subscriptionPlan);

        return response()->json([
            'data' => $subscriptionPlan->refresh()->load('countryPrices'),
        ]);
    }

    public function assignUserSubscription(Request $request, User $user)
    {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->assignUser($request, $user);

        return response()->json([
            'data' => $user->subscriptions()
                ->with('plan:id,name,target_actor')
                ->latest('id')
                ->first(),
        ]);
    }

    public function assignClubSubscription(Request $request, Club $club)
    {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->assignClub($request, $club);

        return response()->json([
            'data' => $club->currentSubscription()
                ->with('plan:id,name,target_actor')
                ->first(),
        ]);
    }

    public function cancelUserSubscription(
        Request $request,
        UserSubscription $subscription
    ) {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->cancelUser($request, $subscription);

        return response()->json([
            'data' => $this->userSubscriptionPayload(
                $subscription->refresh()->load(['user:id,name,email', 'plan:id,name,target_actor'])
            ),
        ]);
    }

    public function renewUserSubscription(
        Request $request,
        UserSubscription $subscription
    ) {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->renewUser($request, $subscription);

        return response()->json([
            'data' => $this->userSubscriptionPayload(
                $subscription->refresh()->load(['user:id,name,email', 'plan:id,name,target_actor'])
            ),
        ]);
    }

    public function cancelClubSubscription(
        Request $request,
        ClubSubscription $subscription
    ) {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->cancelClub($request, $subscription);

        return response()->json([
            'data' => $this->clubSubscriptionPayload(
                $subscription->refresh()->load(['club:id,name', 'plan:id,name,target_actor'])
            ),
        ]);
    }

    public function renewClubSubscription(
        Request $request,
        ClubSubscription $subscription
    ) {
        $this->ensureSubscriptionsManager($request);
        app(SubscriptionPlanController::class)->renewClub($request, $subscription);

        return response()->json([
            'data' => $this->clubSubscriptionPayload(
                $subscription->refresh()->load(['club:id,name', 'plan:id,name,target_actor'])
            ),
        ]);
    }

    public function markTransferPaid(Request $request, PaymentCheckout $checkout)
    {
        $this->ensureSubscriptionsManager($request);

        return app(SubscriptionController::class)->markCheckoutPaid($request, $checkout);
    }

    public function markSubscriptionInvoicePaid(
        Request $request,
        SubscriptionInvoice $subscriptionInvoice
    ) {
        $this->ensureBillingOrSubscriptionsManager($request);
        app(SubscriptionInvoiceController::class)->markPaid(
            $request,
            $subscriptionInvoice
        );

        return response()->json([
            'data' => $this->subscriptionInvoicePayload(
                $subscriptionInvoice->refresh()->load(['user:id,name,email', 'club:id,name', 'plan:id,name'])
            ),
        ]);
    }

    public function storePayment(Request $request)
    {
        $this->ensureBillingManager($request);
        app(PaymentController::class)->store($request);

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function destroyPayment(Request $request, Payment $payment)
    {
        $this->ensureBillingManager($request);
        app(PaymentController::class)->destroy($payment);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeInvoice(Request $request)
    {
        $this->ensureBillingManager($request);
        app(InvoiceController::class)->store($request);

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function updateInvoiceStatus(Request $request, Invoice $invoice)
    {
        $this->ensureBillingManager($request);
        app(InvoiceController::class)->updateStatus($request, $invoice);

        return response()->json([
            'data' => $this->invoicePayload(
                $invoice->refresh()->load(['club:id,name', 'user:id,name,email'])
            ),
        ]);
    }

    public function destroyInvoice(Request $request, Invoice $invoice)
    {
        $this->ensureBillingManager($request);
        app(InvoiceController::class)->destroy($invoice);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeContract(Request $request)
    {
        $this->ensureFinanceManager($request);
        app(OperatingContractController::class)->store($request);

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function updateContract(
        Request $request,
        OperatingContract $operatingContract
    ) {
        $this->ensureFinanceManager($request);
        app(OperatingContractController::class)->update(
            $request,
            $operatingContract
        );

        return response()->json([
            'data' => $this->contractPayload(
                $operatingContract->refresh()->load('owner:id,name,email')
            ),
        ]);
    }

    public function destroyContract(
        Request $request,
        OperatingContract $operatingContract
    ) {
        $this->ensureFinanceManager($request);
        app(OperatingContractController::class)->destroy(
            $request,
            $operatingContract
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function abilities(Request $request): array
    {
        return [
            'subscriptions_manage' => (bool) $request->user()?->can('subscriptions.manage'),
            'billing_manage' => (bool) $request->user()?->can('billing.manage'),
            'finance_view' => (bool) (
                $request->user()?->can('finance.view')
                || $request->user()?->can('finance.edit')
                || $request->user()?->can('system.manage')
            ),
            'finance_edit' => (bool) (
                $request->user()?->can('finance.edit')
                || $request->user()?->can('system.manage')
            ),
        ];
    }

    private function ensureSubscriptionsManager(Request $request): void
    {
        abort_unless($request->user()?->can('subscriptions.manage'), 403);
    }

    private function ensureBillingManager(Request $request): void
    {
        abort_unless($request->user()?->can('billing.manage'), 403);
    }

    private function ensureBillingOrSubscriptionsManager(Request $request): void
    {
        abort_unless(
            $request->user()?->can('billing.manage')
                || $request->user()?->can('subscriptions.manage'),
            403
        );
    }

    private function ensureFinanceManager(Request $request): void
    {
        abort_unless(
            $request->user()?->can('finance.edit')
                || $request->user()?->can('system.manage'),
            403
        );
    }

    private function userSubscriptionPayload(
        UserSubscription $subscription
    ): array {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'payment_provider' => $subscription->payment_provider,
            'billing_interval' => $subscription->billing_interval,
            'trial_ends_at' => $subscription->trial_ends_at?->toJSON(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toJSON(),
            'cancels_at' => $subscription->cancels_at?->toJSON(),
            'user' => $subscription->user,
            'plan' => $subscription->plan,
        ];
    }

    private function clubSubscriptionPayload(
        ClubSubscription $subscription
    ): array {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'payment_provider' => $subscription->payment_provider,
            'billing_interval' => $subscription->billing_interval,
            'trial_ends_at' => $subscription->trial_ends_at?->toJSON(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toJSON(),
            'cancels_at' => $subscription->cancels_at?->toJSON(),
            'club' => $subscription->club,
            'plan' => $subscription->plan,
        ];
    }

    private function checkoutPayload(PaymentCheckout $checkout): array
    {
        return [
            'id' => $checkout->id,
            'payment_reference' => $checkout->payment_reference,
            'amount_cents' => (int) $checkout->amount_cents,
            'currency' => $checkout->currency,
            'status' => $checkout->status,
            'due_at' => $checkout->due_at?->toJSON(),
            'created_at' => $checkout->created_at?->toJSON(),
            'user' => $checkout->user,
            'club' => $checkout->club,
            'plan' => $checkout->plan,
        ];
    }

    private function subscriptionInvoicePayload(
        SubscriptionInvoice $invoice
    ): array {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'amount_cents' => (int) $invoice->amount_cents,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'payment_method' => $invoice->payment_method,
            'payment_reference' => $invoice->payment_reference,
            'issued_at' => $invoice->issued_at?->toJSON(),
            'due_at' => $invoice->due_at?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'user' => $invoice->user,
            'club' => $invoice->club,
            'plan' => $invoice->plan,
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'paid_at' => $payment->paid_at?->toJSON(),
            'notes' => $payment->notes,
            'club' => $payment->club,
            'user' => $payment->user,
            'invoice' => $payment->invoice,
        ];
    }

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'amount' => (float) $invoice->amount,
            'paid_amount' => (float) ($invoice->paid_amount ?? 0),
            'status' => $invoice->status,
            'source' => $invoice->source,
            'due_date' => $invoice->due_date?->toJSON(),
            'issued_at' => $invoice->issued_at?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'club' => $invoice->club,
            'user' => $invoice->user,
            'can_delete' => ! $invoice->payments()->exists(),
        ];
    }

    private function contractPayload(OperatingContract $contract): array
    {
        return [
            'id' => $contract->id,
            'name' => $contract->name,
            'vendor' => $contract->vendor,
            'category' => $contract->category,
            'status' => $contract->status,
            'amount' => (float) $contract->amount,
            'currency' => $contract->currency,
            'billing_interval' => $contract->billing_interval,
            'payment_method' => $contract->payment_method,
            'next_due_on' => $contract->next_due_on?->toDateString(),
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'notice_until_on' => $contract->notice_until_on?->toDateString(),
            'cancellation_period_days' => $contract->cancellation_period_days,
            'auto_renews' => (bool) $contract->auto_renews,
            'contract_number' => $contract->contract_number,
            'account_reference' => $contract->account_reference,
            'contact_email' => $contract->contact_email,
            'website' => $contract->website,
            'document_url' => $contract->document_url,
            'notes' => $contract->notes,
            'monthly_equivalent' => $contract->monthlyEquivalent(),
            'yearly_equivalent' => $contract->yearlyEquivalent(),
            'owner' => $contract->owner,
        ];
    }
}
