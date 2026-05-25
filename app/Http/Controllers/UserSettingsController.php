<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Inertia::render('Auth/Dashboard/Settings/Index', [
            'profileAddress' => $request->user()->only([
                'country',
                'street',
                'house_number',
                'postal_code',
                'city',
                'state',
            ]),
            'privacySettings' => $request->user()->only([
                'profile_visibility',
                'direct_message_privacy',
                'friend_request_privacy',
                'ads_personalization_consent',
                'ads_measurement_consent',
            ]),
            'eventDefaults' => [
                'radius_km' => $request->user()->event_radius_km,
                'sport_ids' => $request->user()->event_default_sport_ids ?? [],
                'filters' => $request->user()->event_default_filters ?? [],
            ],
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'billingHistory' => [
                'airmius_bank' => [
                    'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
                    'bank_name' => Setting::valueFor('billing_bank_name', ''),
                    'iban' => Setting::valueFor('billing_iban', ''),
                    'bic' => Setting::valueFor('billing_bic', ''),
                ],
                'invoices' => Invoice::query()
                    ->where('user_id', $request->user()->id)
                    ->with('club:id,name,sepa_account_holder,sepa_iban,sepa_bic')
                    ->latest('id')
                    ->limit(30)
                    ->get(),
                'payments' => Payment::query()
                    ->where('user_id', $request->user()->id)
                    ->with(['club:id,name', 'invoice:id,number,title'])
                    ->latest('id')
                    ->limit(30)
                    ->get(),
                'subscription_invoices' => SubscriptionInvoice::query()
                    ->where('user_id', $request->user()->id)
                    ->with(['club:id,name', 'plan:id,name', 'checkout:id,status,provider'])
                    ->latest('id')
                    ->limit(30)
                    ->get(),
            ],
            'currentUserSubscriptions' => $request->user()
                ->subscriptions()
                ->with('plan:id,name,target_actor')
                ->latest('id')
                ->get()
                ->map(fn ($subscription) => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'payment_provider' => $subscription->payment_provider,
                    'provider_customer_id' => $subscription->provider_customer_id,
                    'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                    'current_period_ends_at' => $subscription->current_period_ends_at?->toDateString(),
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'cancels_at' => $subscription->cancels_at?->toDateString(),
                    'plan' => $subscription->plan,
                ]),
            'socialAccounts' => $request->user()
                ->socialAccounts()
                ->latest('id')
                ->get(['id', 'provider', 'email', 'name', 'avatar_url', 'created_at']),
            'sportIntegrations' => [
                'providers' => SportIntegrationController::PROVIDERS,
                'accounts' => $request->user()
                    ->connectedSportAccounts()
                    ->latest('id')
                    ->get(['id', 'provider', 'display_name', 'status', 'last_synced_at', 'sync_summary', 'created_at']),
                'activities' => $request->user()
                    ->connectedSportActivities()
                    ->latest('started_at')
                    ->limit(20)
                    ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories', 'metrics', 'image_path'])
                    ->map(fn ($activity) => [
                        ...$activity->toArray(),
                        'image_url' => $activity->image_path ? Storage::disk('public')->url($activity->image_path) : null,
                    ]),
            ],
            'userRoles' => $request->user()
                ->roles()
                ->withCount('permissions')
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions_count' => $role->permissions_count,
                ]),
            'activities' => Activity::query()
                ->where('user_id', $request->user()->id)
                ->with([
                    'club:id,name',
                    'team:id,name',
                ])
                ->latest('id')
                ->limit(50)
                ->get(['id', 'user_id', 'club_id', 'team_id', 'type', 'data', 'created_at']),
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
            $data = $request->validate([
                'theme' => ['nullable', 'in:air,dark,womanly,champion,sprint,arena,pulse,trail,bazaar'],
                'country' => ['required', 'string', 'size:2'],
                'street' => ['nullable', 'string', 'max:255'],
                'house_number' => ['nullable', 'string', 'max:40'],
                'postal_code' => ['nullable', 'string', 'max:30'],
                'city' => ['nullable', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:255'],
                'event_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
                'event_default_sport_ids' => ['nullable', 'array'],
                'event_default_sport_ids.*' => ['integer', 'exists:sports,id'],
                'profile_visibility' => ['nullable', 'in:public,private'],
                'direct_message_privacy' => ['nullable', 'in:everyone,friends'],
                'friend_request_privacy' => ['nullable', 'in:everyone,friends'],
                'ads_personalization_consent' => ['boolean'],
                'ads_measurement_consent' => ['boolean'],
            ]);

            if (empty($data['theme'])) {
                unset($data['theme']);
            }

            $eventSportIds = collect($data['event_default_sport_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
            $eventDefaults = array_merge($request->user()->event_default_filters ?? [], [
                'radius_km' => $data['event_radius_km'] ?? null,
                'sport_ids' => $eventSportIds,
            ]);

            $request->user()->update([
                ...$data,
                'country' => strtoupper($data['country']),
                'event_radius_km' => $data['event_radius_km'] ?? null,
                'event_default_sport_ids' => $eventSportIds,
                'event_default_filters' => $eventDefaults,
                'profile_visibility' => $data['profile_visibility'] ?? $request->user()->profile_visibility ?? 'public',
                'direct_message_privacy' => $data['direct_message_privacy'] ?? $request->user()->direct_message_privacy ?? 'everyone',
                'friend_request_privacy' => $data['friend_request_privacy'] ?? $request->user()->friend_request_privacy ?? 'everyone',
                'ads_personalization_consent' => (bool) ($data['ads_personalization_consent'] ?? false),
                'ads_measurement_consent' => (bool) ($data['ads_measurement_consent'] ?? false),
            ]);

            return back()->with('success', 'Einstellungen wurden gespeichert.');
    }

    public function cancelOpenPayment(Request $request, SubscriptionInvoice $subscriptionInvoice)
    {
        abort_unless($subscriptionInvoice->user_id === $request->user()->id, 403);

        $checkout = $subscriptionInvoice->checkout;
        $isOpenInvoice = in_array($subscriptionInvoice->status, ['open', 'awaiting_transfer', 'overdue'], true);
        $isOpenCheckout = $checkout && in_array($checkout->status, ['pending', 'awaiting_transfer'], true);

        if (! $isOpenInvoice || ! $isOpenCheckout) {
            return back()->with('error', 'Nur offene Airmius-Zahlungen können abgebrochen werden.');
        }

        DB::transaction(function () use ($subscriptionInvoice, $checkout) {
            $checkout->forceFill([
                'status' => 'cancelled',
                'payload' => array_merge($checkout->payload ?? [], [
                    'cancelled_by_user_at' => now()->toIso8601String(),
                ]),
            ])->save();

            $subscriptionInvoice->forceFill([
                'status' => 'cancelled',
                'meta' => array_merge($subscriptionInvoice->meta ?? [], [
                    'cancelled_by_user_at' => now()->toIso8601String(),
                ]),
            ])->save();
        });

        return back()->with('success', 'Offene Zahlung wurde abgebrochen.');
    }

    public function destroyOpenPayment(Request $request, SubscriptionInvoice $subscriptionInvoice)
    {
        abort_unless($subscriptionInvoice->user_id === $request->user()->id, 403);

        $checkout = $subscriptionInvoice->checkout;
        $isUnpaidInvoice = ! in_array($subscriptionInvoice->status, ['paid'], true) && ! $subscriptionInvoice->paid_at;
        $isDisposableCheckout = ! $checkout || in_array($checkout->status, ['pending', 'awaiting_transfer', 'cancelled'], true);

        if (! $isUnpaidInvoice || ! $isDisposableCheckout) {
            return back()->with('error', 'Bezahlte oder bereits aktivierte Zahlungen können nicht gelöscht werden.');
        }

        DB::transaction(function () use ($subscriptionInvoice, $checkout) {
            $subscriptionInvoice->delete();
            $checkout?->delete();
        });

        return back()->with('success', 'Offene Zahlung wurde gelöscht.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
