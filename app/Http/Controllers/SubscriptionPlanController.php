<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionPlanPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionPaymentIssue;
use App\Notifications\SubscriptionRenewed;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        return Inertia::render('Auth/Dashboard/Admin/Subscriptions/Index', [
            'plans' => SubscriptionPlan::query()
                ->with('countryPrices')
                ->withCount(['clubSubscriptions', 'userSubscriptions'])
                ->orderBy('sort_order')
                ->get(),
            'clubs' => Club::query()
                ->with('currentSubscription.plan')
                ->withCount(['users', 'externalMembers', 'teams'])
                ->orderBy('name')
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
            'userSubscriptions' => UserSubscription::query()
                ->with(['user:id,name,email', 'plan:id,name,target_actor'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (UserSubscription $subscription) => [
                    ...$this->subscriptionResource($subscription),
                    'user' => $subscription->user,
                    'plan' => $subscription->plan,
                ]),
            'pendingBankTransfers' => PaymentCheckout::query()
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

        return back()->with('success', 'Abo-Plan aktualisiert.');
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
        $this->sendStatusEmail($subscription);

        return back()->with('success', 'Vereins-Abo aktualisiert.');
    }

    public function assignUser(Request $request, User $user)
    {
        $data = $request->validate([
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
        $this->sendStatusEmail($subscription);

        return back()->with('success', 'Nutzer-Abo aktualisiert.');
    }

    public function cancelClub(Request $request, ClubSubscription $subscription)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['period_end', 'now'])],
        ]);

        $this->cancelSubscription($subscription, $data['mode']);

        return back()->with('success', 'Vereins-Abo wurde gekündigt.');
    }

    public function renewClub(Request $request, ClubSubscription $subscription)
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $this->renewSubscription($subscription, (int) $data['months']);

        return back()->with('success', 'Vereins-Abo wurde verlaengert.');
    }

    public function cancelUser(Request $request, UserSubscription $subscription)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['period_end', 'now'])],
        ]);

        $this->cancelSubscription($subscription, $data['mode']);

        return back()->with('success', 'Nutzer-Abo wurde gekündigt.');
    }

    public function renewUser(Request $request, UserSubscription $subscription)
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $this->renewSubscription($subscription, (int) $data['months']);

        return back()->with('success', 'Nutzer-Abo wurde verlaengert.');
    }

    public function cancelOwnUserSubscription(Request $request, UserSubscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $this->cancelSubscription($subscription, 'period_end');

        return back()->with('success', 'Dein Abo wird zum Periodenende beendet.');
    }

    public function providerPortal(Request $request, UserSubscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        if ($subscription->payment_provider !== 'stripe' || ! $subscription->provider_customer_id || blank(config('services.stripe.secret'))) {
            return back()->with('success', 'Provider-Portal ist für dieses Abo noch nicht verfügbar.');
        }

        $response = Http::asForm()
            ->withToken(config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                'customer' => $subscription->provider_customer_id,
                'return_url' => route('auth.settings'),
            ]);

        if ($response->failed() || blank($response->json('url'))) {
            return back()->with('success', 'Provider-Portal konnte nicht gestartet werden.');
        }

        return redirect()->away($response->json('url'));
    }

    private function cancelSubscription(ClubSubscription|UserSubscription $subscription, string $mode): void
    {
        $endsAt = $subscription->current_period_ends_at ?: now();
        $recipientId = $subscription instanceof UserSubscription ? $subscription->user_id : null;

        if ($mode === 'now') {
            $subscription->update([
                'status' => 'cancelled',
                'cancel_at_period_end' => false,
                'cancels_at' => now(),
                'cancelled_at' => now(),
                'current_period_ends_at' => now(),
            ]);
        } else {
            $subscription->update([
                'status' => 'cancels_at_period_end',
                'cancel_at_period_end' => true,
                'cancels_at' => $endsAt,
                'cancelled_at' => now(),
            ]);
        }

        if ($recipientId) {
            AppNotification::send($recipientId, 'subscription.cancelled', [
                'title' => 'Abo-Kuendigung vorgemerkt',
                'body' => $mode === 'now' ? 'Dein Abo wurde beendet.' : 'Dein Abo läuft bis zum Periodenende weiter.',
                'subscription_id' => $subscription->id,
            ]);
        }
        $this->sendSubscriptionEmail($subscription, new SubscriptionCancelled($subscription, $mode), 'cancellation_email_sent_at');
    }

    private function renewSubscription(ClubSubscription|UserSubscription $subscription, int $months): void
    {
        $baseDate = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
            ? $subscription->current_period_ends_at
            : now();

        $subscription->update([
            'status' => 'active',
            'cancel_at_period_end' => false,
            'cancels_at' => null,
            'cancelled_at' => null,
            'current_period_ends_at' => $baseDate->copy()->addMonths($months),
            'last_renewed_at' => now(),
            'renewal_notified_at' => null,
            'renewal_email_sent_at' => null,
            'payment_issue_email_sent_at' => null,
        ]);
        $subscription->refresh();
        $this->sendSubscriptionEmail($subscription, new SubscriptionRenewed($subscription), 'renewal_email_sent_at');
    }

    private function sendStatusEmail(ClubSubscription|UserSubscription $subscription): void
    {
        $subscription->refresh();

        if ($subscription->status === 'cancelled' || $subscription->status === 'cancels_at_period_end') {
            $this->sendSubscriptionEmail($subscription, new SubscriptionCancelled($subscription, $subscription->status === 'cancelled' ? 'now' : 'period_end'), 'cancellation_email_sent_at');
        }

        if ($subscription->status === 'past_due') {
            $this->sendSubscriptionEmail($subscription, new SubscriptionPaymentIssue($subscription), 'payment_issue_email_sent_at');
        }
    }

    private function sendSubscriptionEmail(ClubSubscription|UserSubscription $subscription, object $notification, string $sentColumn): void
    {
        if ($subscription->{$sentColumn}) {
            return;
        }

        foreach ($this->subscriptionRecipients($subscription) as $recipient) {
            if ($recipient?->email) {
                $recipient->notify($notification);
            }
        }

        $subscription->forceFill([$sentColumn => now()])->save();
    }

    private function subscriptionRecipients(ClubSubscription|UserSubscription $subscription)
    {
        if ($subscription instanceof UserSubscription) {
            return $subscription->user ? collect([$subscription->user]) : collect();
        }

        $club = $subscription->club;

        if (! $club) {
            return collect();
        }

        return ClubRoles::whereAny($club->users(), ['owner', 'admin', 'manager'])
            ->get()
            ->push($club->owner)
            ->filter()
            ->unique('id')
            ->values();
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
