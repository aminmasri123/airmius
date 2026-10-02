<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AdminCommerceController as WebCommerceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OperatingContractController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SubscriptionInvoiceController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\CommerceOrder;
use App\Models\Invoice;
use App\Models\OperatingContract;
use App\Models\Payment;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBackofficeController extends Controller
{
    public function __construct(private readonly SubscriptionLifecycleService $subscriptionLifecycle) {}

    public function dashboard(Request $request)
    {
        $abilities = $this->abilities($request);
        abort_unless(in_array(true, $abilities, true), 403);
        if ($request->has('lookup')) {
            return $this->lookup($request, $abilities);
        }
        if ($request->has('document_type')) {
            return $this->document($request);
        }
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'user_subscriptions_page' => ['nullable', 'integer', 'min:1'],
            'club_subscriptions_page' => ['nullable', 'integer', 'min:1'],
            'pending_transfers_page' => ['nullable', 'integer', 'min:1'],
            'subscription_invoices_page' => ['nullable', 'integer', 'min:1'],
            'payments_page' => ['nullable', 'integer', 'min:1'],
            'user_subscriptions_q' => ['nullable', 'string', 'max:120'],
            'club_subscriptions_q' => ['nullable', 'string', 'max:120'],
            'contracts_page' => ['nullable', 'integer', 'min:1'],
            'contracts_status' => ['nullable', Rule::in(array_merge(['all'], OperatingContract::STATUSES))],
            'contracts_category' => ['nullable', Rule::in(array_merge(['all'], OperatingContract::CATEGORIES))],
            'contracts_q' => ['nullable', 'string', 'max:120'],
        ]);

        $plans = collect();
        $userSubscriptions = collect();
        $clubSubscriptions = collect();
        $checkouts = collect();
        $pagination = [];
        if ($abilities['subscriptions_manage']) {
            $plans = SubscriptionPlan::query()
                ->with('countryPrices')
                ->withCount(['clubSubscriptions', 'userSubscriptions'])
                ->orderBy('sort_order')
                ->get();
            $userSubscriptions = UserSubscription::query()
                ->with(['user:id,name,email', 'plan:id,name,target_actor'])
                ->when(filled($filters['user_subscriptions_q'] ?? null), fn ($query) => $query->where(fn ($query) => $query
                    ->whereHas('user', fn ($user) => $this->searchUser($user, trim($filters['user_subscriptions_q'])))
                    ->orWhereHas('plan', fn ($plan) => $plan->where('name', 'like', '%'.trim($filters['user_subscriptions_q']).'%'))))
                ->latest('id')
                ->paginate(25, ['*'], 'user_subscriptions_page');
            $pagination['user_subscriptions'] = $this->pageMeta($userSubscriptions);
            $userSubscriptions = $userSubscriptions->getCollection()->map(fn (UserSubscription $subscription) => $this->userSubscriptionPayload($subscription));
            $clubSubscriptions = ClubSubscription::query()
                ->with(['club:id,name', 'plan:id,name,target_actor'])
                ->when(filled($filters['club_subscriptions_q'] ?? null), fn ($query) => $query->where(fn ($query) => $query
                    ->whereHas('club', fn ($club) => $club->where('name', 'like', '%'.trim($filters['club_subscriptions_q']).'%'))
                    ->orWhereHas('plan', fn ($plan) => $plan->where('name', 'like', '%'.trim($filters['club_subscriptions_q']).'%'))))
                ->latest('id')
                ->paginate(25, ['*'], 'club_subscriptions_page');
            $pagination['club_subscriptions'] = $this->pageMeta($clubSubscriptions);
            $clubSubscriptions = $clubSubscriptions->getCollection()->map(fn (ClubSubscription $subscription) => $this->clubSubscriptionPayload($subscription));
            $checkouts = PaymentCheckout::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
                ->where('provider', 'bank_transfer')
                ->where('status', 'awaiting_transfer')
                ->latest()
                ->latest('id')
                ->paginate(25, ['*'], 'pending_transfers_page');
            $pagination['pending_transfers'] = $this->pageMeta($checkouts);
            $checkouts = $checkouts->getCollection()->map(fn (PaymentCheckout $checkout) => $this->checkoutPayload($checkout));
        }

        $subscriptionInvoices = collect();
        if ($abilities['subscriptions_manage'] || $abilities['billing_manage']) {
            $subscriptionInvoices = SubscriptionInvoice::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
                ->latest('id')
                ->paginate(50, ['*'], 'subscription_invoices_page');
            $pagination['subscription_invoices'] = $this->pageMeta($subscriptionInvoices);
            $subscriptionInvoices = $subscriptionInvoices->getCollection()->map(fn (SubscriptionInvoice $invoice) => [
                ...$this->subscriptionInvoicePayload($invoice),
                'can_download' => $abilities['subscriptions_manage'],
            ]);
        }

        $payments = collect();
        $invoices = collect();
        $billingSummary = null;
        if ($abilities['billing_manage']) {
            $payments = Payment::query()
                ->with(['club:id,name', 'user:id,name,email', 'invoice:id,number,title,status'])
                ->latest('paid_at')
                ->latest('id')
                ->paginate(25, ['*'], 'payments_page');
            $pagination['payments'] = $this->pageMeta($payments);
            $payments = $payments->getCollection()->map(fn (Payment $payment) => $this->paymentPayload($payment));
            // Reuse the web's source normalization, deduplication, actions and pagination.
            $webRequest = $request->duplicate();
            $webRequest->headers->set('X-Inertia', 'true');
            $webRequest->headers->set('X-Inertia-Partial-Component', 'Auth/Dashboard/Admin/Invoices/Index');
            $webRequest->headers->set('X-Inertia-Partial-Data', 'invoices,summary');
            $webRequest->headers->remove('X-Inertia-Partial-Except');
            $web = app(InvoiceController::class)->index($request)->toResponse($webRequest)->getData(true)['props'];
            $billingSummary = $web['summary'];
            $pagination['invoices'] = collect($web['invoices'])->only(['current_page', 'last_page', 'total', 'per_page'])->all();
            $invoices = collect($web['invoices']['data'])->map(function (array $invoice) use ($abilities) {
                $invoice['amount_display'] = $invoice['amount'];
                $invoice['amount'] = $invoice['amount_cents'] / 100;
                $invoice['paid_amount_display'] = $invoice['paid_amount'];
                $invoice['can_update_status'] = filled($invoice['status_update_url'] ?? null);
                $invoice['can_delete'] = filled($invoice['delete_url'] ?? null);
                $invoice['can_download'] = filled($invoice['download_url'] ?? null) && $abilities['subscriptions_manage'];
                unset($invoice['download_url'], $invoice['status_update_url'], $invoice['delete_url']);

                return $invoice;
            });
        }

        $contracts = collect();
        $contractsMeta = null;
        $contractCount = 0;
        $monthlyContractCost = 0;
        if ($abilities['finance_view'] || $abilities['finance_edit'] || $abilities['billing_manage']) {
            $contractCount = OperatingContract::query()->count();
            $monthlyContractCost = OperatingContract::query()->where('status', 'active')
                ->get(['amount', 'billing_interval'])->sum(fn (OperatingContract $contract) => $contract->monthlyEquivalent());
            $status = $filters['contracts_status'] ?? 'all';
            $category = $filters['contracts_category'] ?? 'all';
            $search = trim($filters['contracts_q'] ?? '');
            $contractPage = OperatingContract::query()
                ->with('owner:id,name,email')
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($category !== 'all', fn ($query) => $query->where('category', $category))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('contract_number', 'like', "%{$search}%")
                    ->orWhere('account_reference', 'like', "%{$search}%")))
                ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 when 'cancelled' then 2 else 3 end")
                ->orderByRaw('next_due_on is null')
                ->orderBy('next_due_on')
                ->latest('id')
                ->paginate(20, ['*'], 'contracts_page');
            $contracts = $contractPage->getCollection()->map(fn (OperatingContract $contract) => $this->contractPayload($contract));
            $contractsMeta = [
                'current_page' => $contractPage->currentPage(),
                'last_page' => $contractPage->lastPage(),
                'total' => $contractPage->total(),
            ];
        }

        return response()->json([
            'data' => [
                'summary' => [
                    'plans' => $plans->count(),
                    'active_user_subscriptions' => $abilities['subscriptions_manage'] ? UserSubscription::query()->where('status', 'active')->count() : 0,
                    'active_club_subscriptions' => $abilities['subscriptions_manage'] ? ClubSubscription::query()->where('status', 'active')->count() : 0,
                    'pending_transfers' => $abilities['subscriptions_manage'] ? PaymentCheckout::query()->where('provider', 'bank_transfer')->where('status', 'awaiting_transfer')->count() : 0,
                    'open_subscription_invoices' => ($abilities['subscriptions_manage'] || $abilities['billing_manage']) ? SubscriptionInvoice::query()
                        ->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])
                        ->count() : 0,
                    'subscription_revenue_cents' => ($abilities['subscriptions_manage'] || $abilities['billing_manage']) ? (int) SubscriptionInvoice::query()
                        ->where('status', 'paid')
                        ->sum('amount_cents') : 0,
                    'payments' => $abilities['billing_manage'] ? Payment::query()->count() : 0,
                    'payment_revenue_cents' => $abilities['billing_manage'] ? (int) round(Payment::query()->where('status', 'paid')->sum('amount') * 100) : 0,
                    'open_invoices' => $billingSummary['open'] ?? 0,
                    'contracts' => $contractCount,
                    'monthly_contract_cost_cents' => (int) round($monthlyContractCost * 100),
                ],
                'abilities' => $abilities,
                'pagination' => $pagination,
                'billing_summary' => $billingSummary,
                'plans' => $plans,
                'user_subscriptions' => $userSubscriptions,
                'club_subscriptions' => $clubSubscriptions,
                'pending_transfers' => $checkouts,
                'subscription_invoices' => $subscriptionInvoices,
                'payments' => $payments,
                'invoices' => $invoices,
                'contracts' => $contracts,
                'contracts_meta' => $contractsMeta,
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

    private function pageMeta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
        ];
    }

    private function searchUser($query, string $search): void
    {
        $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
            ->orWhere('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%"));
    }

    private function lookup(Request $request, array $abilities)
    {
        $data = $request->validate([
            'lookup' => ['required', Rule::in(['users', 'clubs', 'invoices'])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($data['q'] ?? '');
        if ($data['lookup'] === 'invoices') {
            $this->ensureBillingManager($request);
            $query = Invoice::query()->whereIn('status', ['open', 'pending', 'overdue'])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
                ->orderByDesc('id');
            $page = $query->paginate(25, ['id', 'number', 'title', 'user_id', 'club_id']);
        } elseif ($data['lookup'] === 'users') {
            abort_unless($abilities['subscriptions_manage'] || $abilities['billing_manage'] || $abilities['finance_edit'], 403);
            $query = User::query()->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $this->searchUser($query, $search);
                $query->orWhereHas('subscriptions.plan', fn ($plan) => $plan->where('name', 'like', "%{$search}%"));
            }))->orderBy('name')->orderBy('id');
            $page = $query->paginate(25, ['id', 'name', 'email']);
        } else {
            abort_unless($abilities['subscriptions_manage'] || $abilities['billing_manage'], 403);
            $query = Club::query()->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('currentSubscription.plan', fn ($plan) => $plan->where('name', 'like', "%{$search}%"))))
                ->orderBy('name')->orderBy('id');
            $page = $query->paginate(25, ['id', 'name']);
        }

        return response()->json(['data' => $page->items(), 'meta' => $this->pageMeta($page)]);
    }

    private function document(Request $request)
    {
        // Both web document routes require subscriptions.manage in addition to 2FA.
        $this->ensureSubscriptionsManager($request);
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['subscription', 'commerce'])],
            'document_id' => ['required', 'integer', 'min:1'],
        ]);
        if ($data['document_type'] === 'subscription') {
            $invoice = SubscriptionInvoice::query()->findOrFail($data['document_id']);
            $response = app(SubscriptionInvoiceController::class)->download($request, $invoice);
            $number = $invoice->number;
        } else {
            $order = CommerceOrder::query()->findOrFail($data['document_id']);
            $response = app(WebCommerceController::class)->downloadInvoice($order);
            $number = $order->invoice_number;
        }

        $content = $response->getContent();
        abort_unless(is_string($content) && str_starts_with($content, '%PDF-'), 500, 'Invoice PDF could not be generated.');

        return response()->json(['data' => [
            'filename' => (preg_replace('/[^A-Za-z0-9_.-]/', '_', $number) ?: 'invoice').'.pdf',
            'content_type' => 'application/pdf',
            'content_base64' => base64_encode($content),
        ]])->header('Cache-Control', 'private, no-store');
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
        $data = $request->validate([
            'mode' => ['required', Rule::in(['period_end', 'now'])],
        ]);
        $this->subscriptionLifecycle->cancel($subscription, $data['mode']);

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
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);
        $this->subscriptionLifecycle->renew($subscription, (int) $data['months']);

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
        $data = $request->validate([
            'mode' => ['required', Rule::in(['period_end', 'now'])],
        ]);
        $this->subscriptionLifecycle->cancel($subscription, $data['mode']);

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
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);
        $this->subscriptionLifecycle->renew($subscription, (int) $data['months']);

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
