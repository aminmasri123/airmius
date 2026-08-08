<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use App\Services\UserSubscriptionActivationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubscriptionPlanController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserSubscriptionActivationService $userSubscriptionActivator,
        private readonly SubscriptionLifecycleService $subscriptionLifecycle,
    ) {}

    public function index(Request $request)
    {
        $clubQuery = trim((string) $request->string('club_query'));
        $userQuery = trim((string) $request->string('user_query'));

        return Inertia::render('Auth/Dashboard/Admin/Subscriptions/Index', [
            'filters' => [
                'club_query' => $clubQuery,
                'user_query' => $userQuery,
            ],
            'plans' => fn () => SubscriptionPlan::query()
                ->with('countryPrices')
                ->withCount(['clubSubscriptions', 'userSubscriptions'])
                ->orderBy('sort_order')
                ->get(),
            'clubs' => fn () => Club::query()
                ->with('currentSubscription.plan')
                ->withCount(['users', 'externalMembers', 'teams'])
                ->when($clubQuery !== '', function ($query) use ($clubQuery): void {
                    $query->where(function ($search) use ($clubQuery): void {
                        $search->where('name', 'like', '%'.$clubQuery.'%')
                            ->orWhereHas('currentSubscription.plan', fn ($plan) => $plan
                                ->where('name', 'like', '%'.$clubQuery.'%'));
                    });
                })
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name'])
                ->map(fn (Club $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'users_count' => $club->users_count,
                    'external_members_count' => $club->external_members_count,
                    'teams_count' => $club->teams_count,
                    'member_usage' => $club->users_count + $club->external_members_count,
                    'plan' => $club->subscriptionPlan(),
                    'subscription' => $club->currentSubscription ? $this->subscriptionResource($club->currentSubscription) : null,
                ]),
            'users' => fn () => User::query()
                ->with(['subscriptions' => fn ($query) => $query
                    ->with('plan:id,name,target_actor')
                    ->latest('id')])
                ->when($userQuery !== '', function ($query) use ($userQuery): void {
                    $query->where(function ($search) use ($userQuery): void {
                        $search->where('name', 'like', '%'.$userQuery.'%')
                            ->orWhere('first_name', 'like', '%'.$userQuery.'%')
                            ->orWhere('last_name', 'like', '%'.$userQuery.'%')
                            ->orWhere('email', 'like', '%'.$userQuery.'%')
                            ->orWhereHas('subscriptions.plan', fn ($plan) => $plan
                                ->where('name', 'like', '%'.$userQuery.'%'));
                    });
                })
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email', 'first_name', 'last_name'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'subscriptions' => $user->subscriptions
                        ->map(fn (UserSubscription $subscription) => [
                            ...$this->subscriptionResource($subscription),
                            'plan' => $subscription->plan,
                        ])
                        ->values(),
                ]),
            'userSubscriptions' => fn () => UserSubscription::query()
                ->with(['user:id,name,email', 'plan:id,name,target_actor'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (UserSubscription $subscription) => [
                    ...$this->subscriptionResource($subscription),
                    'user' => $subscription->user,
                    'plan' => $subscription->plan,
                ]),
            'pendingBankTransfers' => fn () => PaymentCheckout::query()
                ->with(['user:id,name,email', 'club:id,name', 'plan:id,name'])
                ->where('provider', 'bank_transfer')
                ->where('status', 'awaiting_transfer')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (PaymentCheckout $checkout) => [
                    'id' => $checkout->id,
                    'payment_reference' => $checkout->payment_reference,
                    'amount' => number_format($checkout->amount_cents / 100, 2, ',', '.').' '.$checkout->currency,
                    'amount_cents' => $checkout->amount_cents,
                    'currency' => $checkout->currency,
                    'due_at' => $checkout->due_at?->toDateString(),
                    'created_at' => $checkout->created_at?->toDateString(),
                    'user' => $checkout->user,
                    'club' => $checkout->club,
                    'plan' => $checkout->plan,
                ]),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $data = $request->validate([
            'target_actor' => ['required', Rule::in(['sportler', 'trainer', 'verein', 'eltern', 'sponsor', 'anbieter', 'enterprise'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price_cents' => ['required', 'integer', 'min:0', 'max:99999900'],
            'yearly_price_cents' => ['required', 'integer', 'min:0', 'max:99999900'],
            'member_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'team_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'storage_gb' => ['required', 'integer', 'min:1', 'max:1000000'],
            'minimum_term_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'cancellation_notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'badge' => ['nullable', 'string', 'max:80'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'country_prices' => ['array'],
            'country_prices.*.country_code' => ['required', 'string', 'size:2'],
            'country_prices.*.currency' => ['required', 'string', 'size:3'],
            'country_prices.*.monthly_price_cents' => ['required', 'integer', 'min:0', 'max:99999900'],
            'country_prices.*.yearly_price_cents' => ['required', 'integer', 'min:0', 'max:99999900'],
            'country_prices.*.is_active' => ['boolean'],
        ]);

        $countryPrices = $data['country_prices'] ?? [];
        unset($data['country_prices']);

        $subscriptionPlan->update($data);
        $this->syncCountryPrices($subscriptionPlan, $countryPrices);

        return back()->with('success', __('subscription.responses.plan_updated'));
    }

    private function syncCountryPrices(SubscriptionPlan $plan, array $prices): void
    {
        $seen = [];

        if ($prices === []) {
            $plan->countryPrices()->delete();

            return;
        }

        foreach ($prices as $price) {
            $country = strtoupper($price['country_code']);
            $seen[] = $country;

            SubscriptionPlanPrice::query()->updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'country_code' => $country,
                ],
                [
                    'currency' => strtoupper($price['currency']),
                    'monthly_price_cents' => (int) $price['monthly_price_cents'],
                    'yearly_price_cents' => (int) $price['yearly_price_cents'],
                    'is_active' => (bool) ($price['is_active'] ?? false),
                ],
            );
        }

        $plan->countryPrices()->whereNotIn('country_code', $seen)->delete();
    }

    public function assignClub(Request $request, Club $club)
    {
        $data = $request->validate([
            'user_subscription_id' => ['nullable', Rule::exists('user_subscriptions', 'id')],
            'subscription_plan_id' => ['required', Rule::exists('subscription_plans', 'id')],
            'status' => ['required', Rule::in(['trialing', 'active', 'past_due', 'cancelled', 'cancels_at_period_end'])],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
            'payment_provider' => ['nullable', 'string', 'max:30'],
        ]);

        $subscription = $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            [
                ...$data,
                'cancel_at_period_end' => $data['status'] === 'cancels_at_period_end',
                'cancels_at' => $data['status'] === 'cancels_at_period_end'
                    ? ($data['current_period_ends_at'] ?? now()->toDateString())
                    : null,
                'cancelled_at' => $data['status'] === 'cancelled' ? now() : null,
            ],
        );
        $this->subscriptionLifecycle->sendCurrentStatusEmail($subscription);

        return back()->with('success', __('subscription.responses.club_updated'));
    }

    public function assignUser(Request $request, User $user)
    {
        $data = $request->validate([
            'user_subscription_id' => ['nullable', Rule::exists('user_subscriptions', 'id')],
            'subscription_plan_id' => ['required', Rule::exists('subscription_plans', 'id')],
            'status' => ['required', Rule::in(['trialing', 'active', 'past_due', 'cancelled', 'cancels_at_period_end'])],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
            'payment_provider' => ['nullable', 'string', 'max:30'],
        ]);

        $payload = [
            'subscription_plan_id' => $data['subscription_plan_id'],
            'status' => $data['status'],
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'current_period_ends_at' => $data['current_period_ends_at'] ?? null,
            'payment_provider' => $data['payment_provider'] ?? null,
            'cancel_at_period_end' => $data['status'] === 'cancels_at_period_end',
            'cancels_at' => $data['status'] === 'cancels_at_period_end'
                ? ($data['current_period_ends_at'] ?? now()->toDateString())
                : null,
            'cancelled_at' => $data['status'] === 'cancelled' ? now() : null,
        ];

        if (! empty($data['user_subscription_id'])) {
            $subscription = $user->subscriptions()->whereKey($data['user_subscription_id'])->firstOrFail();
            $subscription->update($payload);
        } else {
            $subscription = $user->subscriptions()->create($payload);
        }
        $this->userSubscriptionActivator->retireOtherUserSubscriptions($subscription->fresh('plan'));
        $this->subscriptionLifecycle->sendCurrentStatusEmail($subscription);

        return back()->with('success', __('subscription.responses.user_updated'));
    }

    public function cancelClub(Request $request, ClubSubscription $subscription)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['period_end', 'now'])],
        ]);

        $this->subscriptionLifecycle->cancel($subscription, $data['mode']);

        return back()->with('success', __('subscription.responses.club_cancelled'));
    }

    public function renewClub(Request $request, ClubSubscription $subscription)
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $this->subscriptionLifecycle->renew($subscription, (int) $data['months']);

        return back()->with('success', __('subscription.responses.club_renewed'));
    }

    public function cancelUser(Request $request, UserSubscription $subscription)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['period_end', 'now'])],
        ]);

        $this->subscriptionLifecycle->cancel($subscription, $data['mode']);

        return back()->with('success', __('subscription.responses.user_cancelled'));
    }

    public function renewUser(Request $request, UserSubscription $subscription)
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $this->subscriptionLifecycle->renew($subscription, (int) $data['months']);

        return back()->with('success', __('subscription.responses.user_renewed'));
    }

    public function cancelOwnUserSubscription(Request $request, UserSubscription $subscription)
    {
        $this->authorize('cancel', $subscription);

        if (! $this->isUserSubscriptionCancellable($subscription)) {
            return back()->with('error', __('subscription.responses.already_cancelled'));
        }

        $this->subscriptionLifecycle->cancel($subscription, 'period_end');

        return back()->with('success', __('subscription.responses.own_cancelled'));
    }

    public function providerPortal(Request $request, UserSubscription $subscription)
    {
        $this->authorize('accessProviderPortal', $subscription);

        if (! $this->hasStripePortalConfiguration()) {
            return back()->with('error', __('subscription.responses.portal_unavailable'));
        }

        try {
            $response = Http::asForm()
                ->withToken(config('services.stripe.secret'))
                ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                    'customer' => $subscription->provider_customer_id,
                    'return_url' => route('auth.settings'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe Portal API request failed.', [
                'user_id' => $request->user()->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('subscription.responses.portal_request_failed'));
        }

        if ($response->failed() || blank($response->json('url'))) {
            Log::warning('Stripe Portal API returned invalid response.', [
                'user_id' => $request->user()->id,
                'subscription_id' => $subscription->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return back()->with('error', __('subscription.responses.portal_start_failed'));
        }

        return redirect()->away($response->json('url'));
    }

    private function isUserSubscriptionCancellable(UserSubscription $subscription): bool
    {
        return $this->subscriptionLifecycle->canCancel($subscription);
    }

    private function hasStripePortalConfiguration(): bool
    {
        return blank(config('services.stripe.secret')) === false;
    }

    private function subscriptionResource(ClubSubscription|UserSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'payment_provider' => $subscription->payment_provider,
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toDateString(),
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'cancels_at' => $subscription->cancels_at?->toDateString(),
            'cancelled_at' => $subscription->cancelled_at?->toDateString(),
            'last_renewed_at' => $subscription->last_renewed_at?->toDateString(),
        ];
    }
}
