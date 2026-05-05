<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionInvoice;
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
            ]),
            'billingHistory' => [
                'invoices' => Invoice::query()
                    ->where('user_id', $request->user()->id)
                    ->with('club:id,name')
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
                    ->with(['club:id,name', 'plan:id,name'])
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
                    ->get(['id', 'provider', 'activity_type', 'title', 'started_at', 'duration_seconds', 'distance_meters', 'calories']),
            ],
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
                'theme' => ['nullable', 'in:air,dark,womanly'],
                'country' => ['required', 'string', 'size:2'],
                'street' => ['nullable', 'string', 'max:255'],
                'house_number' => ['nullable', 'string', 'max:40'],
                'postal_code' => ['nullable', 'string', 'max:30'],
                'city' => ['nullable', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:255'],
                'profile_visibility' => ['nullable', 'in:public,private'],
                'direct_message_privacy' => ['nullable', 'in:everyone,friends'],
                'friend_request_privacy' => ['nullable', 'in:everyone,friends'],
            ]);

            if (empty($data['theme'])) {
                unset($data['theme']);
            }

            $request->user()->update([
                ...$data,
                'country' => strtoupper($data['country']),
                'profile_visibility' => $data['profile_visibility'] ?? $request->user()->profile_visibility ?? 'public',
                'direct_message_privacy' => $data['direct_message_privacy'] ?? $request->user()->direct_message_privacy ?? 'everyone',
                'friend_request_privacy' => $data['friend_request_privacy'] ?? $request->user()->friend_request_privacy ?? 'everyone',
            ]);

            return back()->with('success', 'Einstellungen wurden gespeichert.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
