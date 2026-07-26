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
use App\Support\BillingOverview;
use App\Support\MinorSafety;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $clubInvoices = Invoice::query()
            ->where('user_id', $user->id)
            ->with('club')
            ->latest('id')
            ->limit(30)
            ->get();
        $payments = Payment::query()
            ->where('user_id', $user->id)
            ->with(['club', 'invoice'])
            ->latest('id')
            ->limit(30)
            ->get();
        $subscriptionInvoices = SubscriptionInvoice::query()
            ->where('user_id', $user->id)
            ->with(['club', 'plan', 'checkout'])
            ->latest('id')
            ->limit(30)
            ->get();

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
                'notification_preferences' => [
                    'channels' => $this->notificationChannels($user),
                    'quiet_time' => $user->notification_quiet_time ?: 'late',
                ],
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
                    'summary' => BillingOverview::memberBillingSummary($clubInvoices, $subscriptionInvoices, $payments),
                    'airmius_bank' => [
                        'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
                        'bank_name' => Setting::valueFor('billing_bank_name', ''),
                        'iban' => Setting::valueFor('billing_iban', ''),
                        'bic' => Setting::valueFor('billing_bic', ''),
                    ],
                    'invoices' => InvoiceResource::collection($clubInvoices),
                    'payments' => PaymentResource::collection($payments),
                    'subscription_invoices' => SubscriptionInvoiceResource::collection($subscriptionInvoices),
                ],
            ],
        ]);
    }

    public function invoices(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $page = max((int) $request->integer('page', 1), 1);
        $clubInvoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with('club')
            ->latest('id')
            ->get();
        $subscriptionInvoices = SubscriptionInvoice::query()
            ->where('user_id', $request->user()->id)
            ->with(['club', 'plan', 'checkout'])
            ->latest('id')
            ->get();

        $items = collect()
            ->merge($clubInvoices->map(fn (Invoice $invoice) => $this->invoicePayload($invoice)))
            ->merge($subscriptionInvoices->map(fn (SubscriptionInvoice $invoice) => $this->subscriptionInvoicePayload($invoice)))
            ->sortByDesc('sort_at')
            ->values();

        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $data = $items
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(fn (array $item) => \Illuminate\Support\Arr::except($item, ['sort_at']))
            ->values();

        return response()->json([
            'data' => $data,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
            'to' => $total === 0 ? null : min($page * $perPage, $total),
            'meta' => [
                'sources' => ['club_invoices', 'subscription_invoices'],
                'summary' => BillingOverview::memberBillingSummary($clubInvoices, $subscriptionInvoices),
            ],
        ]);
    }

    public function invoice(Request $request, int $invoice)
    {
        $clubInvoice = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with('club')
            ->find($invoice);

        if ($clubInvoice) {
            return response()->json([
                'data' => \Illuminate\Support\Arr::except($this->invoicePayload($clubInvoice), ['sort_at']),
            ]);
        }

        $subscriptionInvoice = SubscriptionInvoice::query()
            ->where('user_id', $request->user()->id)
            ->with(['club', 'plan', 'checkout'])
            ->findOrFail($invoice);

        return response()->json([
            'data' => \Illuminate\Support\Arr::except($this->subscriptionInvoicePayload($subscriptionInvoice), ['sort_at']),
        ]);
    }

    private function invoicePayload(Invoice $invoice): array
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

    private function subscriptionInvoicePayload(SubscriptionInvoice $invoice): array
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
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['nullable', Rule::in(['air', 'dark', 'womanly', 'champion', 'sprint', 'arena', 'pulse', 'trail', 'bazaar'])],
            'country' => ['nullable', 'string', 'size:2'],
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
            'notification_channels' => ['nullable', 'array'],
            'notification_channels.*' => ['boolean'],
            'notification_quiet_time' => ['nullable', Rule::in(['none', 'late', 'early', 'weekend'])],
            'ads_personalization_consent' => ['boolean'],
            'ads_measurement_consent' => ['boolean'],
        ]);

        if (empty($data['theme'])) {
            unset($data['theme']);
        }

        if (array_key_exists('notification_channels', $data)) {
            $allowedChannels = array_keys($this->notificationChannels($request->user()));
            $channels = $data['notification_channels'] ?? [];
            $unknownChannels = array_diff(array_keys($channels), $allowedChannels);
            if ($unknownChannels !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'notification_channels' => 'Unbekannter Benachrichtigungskanal.',
                ]);
            }
            $data['notification_channels'] = array_replace(
                $this->notificationChannels($request->user()),
                collect($channels)->mapWithKeys(fn ($value, $key) => [(string) $key => (bool) $value])->all(),
            );
        }

        if (MinorSafety::isUnderConsentAge($request->user())) {
            $data = array_merge($data, MinorSafety::privacyDefaults());
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
            'country' => strtoupper((string) ($data['country'] ?? $request->user()->country ?? 'DE')),
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

    private function notificationChannels($user): array
    {
        $defaults = [
            'push' => false,
            'email' => true,
            'chat' => true,
            'club' => true,
            'billing' => true,
            'marketing' => false,
        ];

        return array_replace($defaults, is_array($user->notification_channels) ? $user->notification_channels : []);
    }
}
