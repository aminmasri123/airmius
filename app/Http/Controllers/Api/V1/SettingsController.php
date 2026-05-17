<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Resources\Api\V1\SubscriptionInvoiceResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => new UserResource($user->loadMissing(['roles', 'permissions'])),
                'profile_address' => $user->only([
                    'country',
                    'street',
                    'house_number',
                    'postal_code',
                    'city',
                    'state',
                ]),
                'privacy_settings' => $user->only([
                    'profile_visibility',
                    'direct_message_privacy',
                    'friend_request_privacy',
                    'ads_personalization_consent',
                    'ads_measurement_consent',
                ]),
                'event_defaults' => [
                    'radius_km' => $user->event_radius_km,
                    'sport_ids' => $user->event_default_sport_ids ?? [],
                    'filters' => $user->event_default_filters ?? [],
                ],
                'sports' => Sport::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'category']),
                'billing_history' => [
                    'airmius_bank' => [
                        'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
                        'bank_name' => Setting::valueFor('billing_bank_name', ''),
                        'iban' => Setting::valueFor('billing_iban', ''),
                        'bic' => Setting::valueFor('billing_bic', ''),
                    ],
                    'invoices' => InvoiceResource::collection(
                        Invoice::query()
                            ->where('user_id', $user->id)
                            ->with('club')
                            ->latest('id')
                            ->limit(30)
                            ->get()
                    ),
                    'payments' => PaymentResource::collection(
                        Payment::query()
                            ->where('user_id', $user->id)
                            ->with(['club', 'invoice'])
                            ->latest('id')
                            ->limit(30)
                            ->get()
                    ),
                    'subscription_invoices' => SubscriptionInvoiceResource::collection(
                        SubscriptionInvoice::query()
                            ->where('user_id', $user->id)
                            ->with(['club', 'plan', 'checkout'])
                            ->latest('id')
                            ->limit(30)
                            ->get()
                    ),
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['nullable', Rule::in(['air', 'dark', 'womanly', 'champion', 'sprint', 'arena', 'pulse', 'trail', 'bazaar'])],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'event_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'event_default_sport_ids' => ['nullable', 'array'],
            'event_default_sport_ids.*' => ['integer', 'exists:sports,id'],
            'profile_visibility' => ['nullable', Rule::in(['public', 'private'])],
            'direct_message_privacy' => ['nullable', Rule::in(['everyone', 'friends'])],
            'friend_request_privacy' => ['nullable', Rule::in(['everyone', 'friends'])],
            'ads_personalization_consent' => ['boolean'],
            'ads_measurement_consent' => ['boolean'],
        ]);

        if (empty($data['theme'])) {
            unset($data['theme']);
        }

        $eventSportIds = collect($data['event_default_sport_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

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

        return new UserResource($request->user()->refresh());
    }
}
