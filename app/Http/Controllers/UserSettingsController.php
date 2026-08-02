<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\SubscriptionInvoice;
use App\Models\UserRoleApplication;
use App\Services\Training\AthleteSportProfileService;
use App\Support\BillingOverview;
use App\Support\MinorSafety;
use App\Support\NavigationModules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AthleteSportProfileService $sportProfiles)
    {
        $user = $request->user();
        $clubInvoices = Invoice::query()
            ->where('user_id', $user->id)
            ->with('club:id,name,sepa_account_holder,sepa_iban,sepa_bic')
            ->latest('id')
            ->limit(30)
            ->get();
        $payments = Payment::query()
            ->where('user_id', $user->id)
            ->with(['club:id,name', 'invoice:id,number,title'])
            ->latest('id')
            ->limit(30)
            ->get();
        $subscriptionInvoices = SubscriptionInvoice::query()
            ->where('user_id', $user->id)
            ->with(['club:id,name', 'plan:id,name', 'checkout:id,status,provider'])
            ->latest('id')
            ->limit(30)
            ->get();

        return Inertia::render('Auth/Dashboard/Settings/Index', [
            'profileAddress' => $user->only([
                'country',
                'street',
                'house_number',
                'postal_code',
                'city',
                'state',
            ]),
            'privacySettings' => $user->only([
                'profile_visibility',
                'direct_message_privacy',
                'friend_request_privacy',
                'ads_personalization_consent',
                'ads_measurement_consent',
            ]),
            'navigationModules' => NavigationModules::payload($user),
            'eventDefaults' => [
                'radius_km' => $user->event_radius_km,
                'sport_ids' => $user->event_default_sport_ids ?? [],
                'filters' => $user->event_default_filters ?? [],
            ],
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'sportProfiles' => $sportProfiles->settingsPayload($user),
            'billingHistory' => [
                'summary' => BillingOverview::memberBillingSummary($clubInvoices, $subscriptionInvoices, $payments),
                'airmius_bank' => [
                    'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
                    'bank_name' => Setting::valueFor('billing_bank_name', ''),
                    'iban' => Setting::valueFor('billing_iban', ''),
                    'bic' => Setting::valueFor('billing_bic', ''),
                ],
                'invoices' => $clubInvoices
                    ->map(fn (Invoice $invoice) => $this->memberInvoicePayload($invoice))
                    ->values(),
                'payments' => $payments,
                'subscription_invoices' => $subscriptionInvoices,
            ],
            'currentUserSubscriptions' => $user
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
            'socialAccounts' => $user
                ->socialAccounts()
                ->latest('id')
                ->get(['id', 'provider', 'email', 'name', 'avatar_url', 'created_at']),
            'sportIntegrations' => [
                'providers' => SportIntegrationController::PROVIDERS,
                'accounts' => $user
                    ->connectedSportAccounts()
                    ->latest('id')
                    ->get(['id', 'provider', 'display_name', 'status', 'last_synced_at', 'sync_summary', 'created_at']),
                'activities' => $user
                    ->connectedSportActivities()
                    ->latest('started_at')
                    ->limit(20)
                    ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories', 'metrics', 'image_path'])
                    ->map(fn ($activity) => [
                        ...$activity->toArray(),
                        'image_url' => $activity->image_path ? Storage::disk('public')->url($activity->image_path) : null,
                    ]),
            ],
            'userRoles' => $user
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
            'roleApplications' => $user
                ->roleApplications()
                ->latest('requested_at')
                ->get()
                ->map(fn (UserRoleApplication $application) => [
                    'id' => $application->id,
                    'type' => $application->type,
                    'status' => $application->status,
                    'message' => $application->message,
                    'application_data' => $application->application_data ?? [],
                    'review_notes' => $application->review_notes,
                    'role_activated' => (bool) $application->role_activated,
                    'requested_at' => $application->requested_at?->toJSON(),
                    'reviewed_at' => $application->reviewed_at?->toJSON(),
                ]),
            'activities' => Activity::query()
                ->where('user_id', $user->id)
                ->with([
                    'club:id,name',
                    'team:id,name',
                ])
                ->latest('id')
                ->limit(50)
                ->get(['id', 'user_id', 'club_id', 'team_id', 'type', 'data', 'created_at']),
        ]);

    }

    private function memberInvoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'amount' => $invoice->amount,
            'status' => $invoice->status,
            'status_label' => $invoice->statusLabel(),
            'source' => $invoice->source,
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'due_date' => $invoice->due_date?->toJSON(),
            'issued_at' => $invoice->issued_at?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'club' => $invoice->club ? [
                'id' => $invoice->club->id,
                'name' => $invoice->club->name,
                'sepa_account_holder' => $invoice->club->sepa_account_holder,
                'sepa_iban' => $invoice->club->sepa_iban,
                'sepa_bic' => $invoice->club->sepa_bic,
            ] : null,
            'created_at' => $invoice->created_at?->toJSON(),
            'updated_at' => $invoice->updated_at?->toJSON(),
        ];
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
                'theme' => ['nullable', 'in:air,dark,womanly,champion,sprint,arena,pulse,trail,bazaar,vital'],
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
                'enabled_navigation_modules' => ['nullable', 'array'],
                'enabled_navigation_modules.*' => ['string', Rule::in(NavigationModules::keys())],
            ]);

            if (empty($data['theme'])) {
                unset($data['theme']);
            }

            if (MinorSafety::isUnderConsentAge($request->user())) {
                $data = array_merge($data, MinorSafety::privacyDefaults());
            }

            if (array_key_exists('enabled_navigation_modules', $data)) {
                $data['enabled_navigation_modules'] = NavigationModules::sanitize(
                    $request->user(),
                    $data['enabled_navigation_modules'],
                );
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

    public function updateSportProfile(Request $request, Sport $sport, AthleteSportProfileService $sportProfiles)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'wants_to_learn', 'coach', 'interested'])],
            'experience_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'expert', 'elite'])],
            'visibility' => ['required', Rule::in(['private', 'trainer', 'public'])],
            'metrics' => ['nullable', 'array'],
            'metric_visibility' => ['nullable', 'array'],
            'unknown_metrics' => ['nullable', 'array'],
        ]);

        $sportProfiles->updateProfile($request->user(), $sport, $data);

        return back();
    }

    public function destroySportProfile(Request $request, Sport $sport)
    {
        $request->user()
            ->sportProfiles()
            ->where('sport_id', $sport->id)
            ->delete();

        return back();
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
