<?php

namespace App\Http\Controllers;

use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Models\Sport;
use App\Notifications\OutfitDeliveryStatusUpdated;
use App\Services\MediaOptimizer;
use App\Services\OutfitPaymentReminderService;
use App\Support\AppNotification;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminOutfitSubscriptionPlanController extends Controller
{
    public function __construct(private MediaOptimizer $mediaOptimizer) {}

    public function index()
    {
        return Inertia::render('Auth/Dashboard/Admin/OutfitSubscriptions/Index', [
            'plans' => OutfitSubscriptionPlan::query()
                ->with('sponsor:id,name,logo,website')
                ->withCount('subscriptions')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'sponsors' => Sponsor::query()
                ->where('scope', 'outfit_subscription')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'logo', 'website']),
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
            'summary' => [
                'plans' => OutfitSubscriptionPlan::query()->count(),
                'activePlans' => OutfitSubscriptionPlan::query()->where('is_active', true)->count(),
                'sponsoredPlans' => OutfitSubscriptionPlan::query()->whereNotNull('sponsor_id')->count(),
                'subscriptions' => OutfitSubscription::query()->count(),
                'pendingPayments' => OutfitSubscription::query()->where('payment_status', 'pending')->count(),
                'paidSubscriptions' => OutfitSubscription::query()->where('payment_status', 'paid')->count(),
                'activeSubscriptions' => OutfitSubscription::query()->where('status', 'active')->count(),
                'deliveries' => OutfitDelivery::query()->count(),
                'plannedDeliveries' => OutfitDelivery::query()->whereIn('status', ['planned', 'preparing'])->count(),
            ],
            'subscriptions' => OutfitSubscription::query()
                ->with(['user:id,name,email', 'plan:id,name', 'sponsor:id,name', 'latestDelivery'])
                ->withCount('deliveries')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (OutfitSubscription $subscription) => $this->subscriptionPayload($subscription)),
            'deliveries' => OutfitDelivery::query()
                ->with(['subscription.user:id,name,email', 'subscription.plan:id,name'])
                ->latest('id')
                ->limit(150)
                ->get()
                ->map(fn (OutfitDelivery $delivery) => $this->deliveryPayload($delivery)),
            'visuals' => $this->visualPayload(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        OutfitSubscriptionPlan::query()->create($data);

        return back()->with('success', 'Sportkleidung-Abo-Plan wurde erstellt.');
    }

    public function update(Request $request, OutfitSubscriptionPlan $plan)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $plan->slug ?: $this->uniqueSlug($data['name']);

        $plan->update($data);

        return back()->with('success', 'Sportkleidung-Abo-Plan wurde gespeichert.');
    }

    public function destroy(OutfitSubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false, 'is_public' => false]);

            return back()->with('success', 'Plan hat aktive Historie und wurde deaktiviert.');
        }

        $plan->delete();

        return back()->with('success', 'Plan wurde geloescht.');
    }

    public function updateVisuals(Request $request)
    {
        $data = $request->validate([
            'hero_source' => ['nullable', 'string', 'max:2048'],
            'hero_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $source = trim((string) ($data['hero_source'] ?? ''));

        if ($request->hasFile('hero_upload')) {
            $source = $this->mediaOptimizer->store($request->file('hero_upload'), 'outfit-subscriptions/visuals')['path'];
        }

        Setting::setValue('outfit_subscription_hero_image', $source ?: $this->defaultHeroImage());

        return back()->with('success', 'Outfit-Abo-Bild wurde gespeichert.');
    }

    public function markSubscriptionPaid(Request $request, OutfitSubscription $subscription, OutfitPaymentReminderService $reminders)
    {
        $data = $request->validate([
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $reminders->resetDunningAfterPayment($subscription);
        $subscription->refresh();

        if (! $subscription->deliveries()->exists()) {
            OutfitDelivery::query()->create([
                'outfit_subscription_id' => $subscription->id,
                'status' => 'planned',
                'delivery_month' => now()->addMonth()->startOfMonth(),
                'items' => [],
                'notes' => trim('Zahlung bestaetigt. '.($data['payment_note'] ?? 'Erste personalisierte Box wird vorbereitet.')),
            ]);
        }

        AppNotification::send($subscription->user_id, 'outfit.subscription.paid', [
            'title' => 'Outfit-Abo aktiviert',
            'message' => 'Deine Zahlung für '.$subscription->plan?->name.' wurde bestaetigt. Dein Outfit-Abo ist jetzt aktiv.',
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Zahlung wurde bestaetigt und das Outfit-Abo aktiviert.');
    }

    public function markSubscriptionUnpaid(Request $request, OutfitSubscription $subscription)
    {
        $data = $request->validate([
            'payment_due_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $subscription->forceFill([
            'status' => 'active',
            'payment_status' => 'pending',
            'payment_due_at' => isset($data['payment_due_at'])
                ? \Illuminate\Support\Carbon::parse($data['payment_due_at'])->endOfDay()
                : now()->endOfDay(),
            'dunning_level' => 0,
            'last_dunning_sent_at' => null,
            'payment_paused_at' => null,
            'payment_paused_reason' => null,
        ])->save();

        AppNotification::send($subscription->user_id, 'outfit.payment.opened_by_admin', [
            'title' => 'Outfit-Abo Zahlung offen',
            'message' => trim('Fuer dein Outfit-Abo '.$subscription->plan?->name.' wurde eine offene Zahlung hinterlegt. '.($data['reason'] ?? '')),
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Outfit-Abo wurde als offene Zahlung markiert. Mahnungen laufen automatisch.');
    }

    public function remindPayment(OutfitSubscription $subscription, OutfitPaymentReminderService $reminders)
    {
        if (! $reminders->send($subscription)) {
            return back()->with('success', 'Erinnerung konnte nicht gesendet werden. Die Zahlung ist nicht offen oder das Limit von 3 Erinnerungen ist erreicht.');
        }

        return back()->with('success', 'Zahlungserinnerung wurde per E-Mail und Benachrichtigung versendet.');
    }

    public function cancelSubscription(Request $request, OutfitSubscription $subscription)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($subscription->payment_provider === 'paypal' && $subscription->provider_subscription_id) {
            $this->cancelPayPalSubscription($subscription, $data['reason'] ?? null);
        }

        $subscription->forceFill([
            'status' => 'cancelled',
            'payment_status' => $subscription->payment_status === 'paid' ? 'paid' : 'cancelled',
            'cancelled_at' => now(),
            'next_delivery_at' => null,
            'current_period_ends_at' => null,
        ])->save();

        AppNotification::send($subscription->user_id, 'outfit.subscription.cancelled_by_admin', [
            'title' => 'Outfit-Abo-Anfrage abgebrochen',
            'message' => trim('Deine Outfit-Abo-Anfrage für '.$subscription->plan?->name.' wurde abgebrochen. '.($data['reason'] ?? '')),
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Outfit-Abo-Anfrage wurde abgebrochen.');
    }

    public function destroySubscription(Request $request, OutfitSubscription $subscription)
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:delete'],
        ]);

        if ($subscription->payment_provider === 'paypal' && $subscription->provider_subscription_id) {
            $this->cancelPayPalSubscription($subscription, 'Abo wurde durch Admin geloescht.');
        }

        $subscription->delete();

        return back()->with('success', 'Outfit-Abo wurde geloescht.');
    }

    public function updateDelivery(Request $request, OutfitDelivery $delivery)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:planned,preparing,shipped,delivered,cancelled'],
            'delivery_month' => ['nullable', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'carrier' => ['nullable', 'string', 'max:120'],
            'items_text' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $previousStatus = $delivery->status;
        $status = $data['status'];

        $delivery->update([
            'status' => $status,
            'delivery_month' => $data['delivery_month'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'carrier' => $data['carrier'] ?? null,
            'items' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['items_text'] ?? '')))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all(),
            'notes' => $data['notes'] ?? null,
            'shipped_at' => $status === 'shipped' && ! $delivery->shipped_at ? now() : ($status === 'planned' || $status === 'preparing' ? null : $delivery->shipped_at),
            'delivered_at' => $status === 'delivered' && ! $delivery->delivered_at ? now() : ($status !== 'delivered' ? null : $delivery->delivered_at),
        ]);

        if ($previousStatus !== $status) {
            $this->notifyDeliveryStatus($delivery->fresh());
        }

        return back()->with('success', 'Lieferung wurde aktualisiert.');
    }

    public function markDeliveryShipped(Request $request, OutfitDelivery $delivery)
    {
        $data = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'carrier' => ['nullable', 'string', 'max:120'],
        ]);

        $previousStatus = $delivery->status;

        $delivery->update([
            'status' => 'shipped',
            'tracking_number' => $data['tracking_number'] ?? $delivery->tracking_number,
            'carrier' => $data['carrier'] ?? $delivery->carrier,
            'shipped_at' => $delivery->shipped_at ?: now(),
        ]);

        if ($previousStatus !== 'shipped') {
            $this->notifyDeliveryStatus($delivery->fresh());
        }

        return back()->with('success', 'Lieferung wurde als versendet markiert.');
    }

    public function markDeliveryDelivered(OutfitDelivery $delivery)
    {
        $previousStatus = $delivery->status;

        $delivery->update([
            'status' => 'delivered',
            'shipped_at' => $delivery->shipped_at ?: now(),
            'delivered_at' => $delivery->delivered_at ?: now(),
        ]);

        if ($previousStatus !== 'delivered') {
            $this->notifyDeliveryStatus($delivery->fresh());
        }

        return back()->with('success', 'Lieferung wurde als geliefert markiert.');
    }

    public function destroyDelivery(Request $request, OutfitDelivery $delivery)
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:delete'],
        ]);

        $delivery->delete();

        return back()->with('success', 'Lieferung wurde geloescht.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'sponsor_id' => ['nullable', 'exists:sponsors,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contract_title' => ['nullable', 'string', 'max:160'],
            'contract_terms' => ['nullable', 'array'],
            'contract_terms.*' => ['nullable', 'string', 'max:1000'],
            'minimum_term_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'pause_allowed_after_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'cancellation_notice_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'monthly_price_cents' => ['required', 'integer', 'min:0'],
            'sponsor_discount_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'target_gender' => ['nullable', 'string', 'max:30'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['nullable', 'string', 'max:20'],
            'sports' => ['nullable', 'array'],
            'sports.*' => ['nullable', 'string', 'max:80'],
            'items_per_box' => ['required', 'integer', 'min:1', 'max:12'],
            'branding_type' => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['sponsor_discount_cents'] = $data['sponsor_discount_cents'] ?? 0;
        $data['contract_title'] = $data['contract_title'] ?: null;
        $data['contract_terms'] = collect($data['contract_terms'] ?? [])
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->values()
            ->all();
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_public'] = (bool) ($data['is_public'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'outfit-plan';
        $slug = $base;
        $counter = 2;

        while (OutfitSubscriptionPlan::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function visualPayload(): array
    {
        $source = Setting::valueFor('outfit_subscription_hero_image', $this->defaultHeroImage());

        return [
            'hero' => [
                'label' => 'Outfit-Abo Hero-Bild',
                'source' => $source,
                'url' => UploadStorage::url($source),
                'recommended_size' => '1920 x 1080 px',
                'ratio' => '16:9',
                'formats' => 'WebP, JPG, PNG',
                'max_size' => 'bis 8 MB',
                'note' => 'Querformat nutzen. Wichtige Texte/Logos mittig halten, weil die Dashboard-Ansicht seitlich oder oben/unten beschneiden kann.',
            ],
        ];
    }

    private function defaultHeroImage(): string
    {
        return '/images/marketplace/airmius_outfit_abo.webp';
    }

    private function subscriptionPayload(OutfitSubscription $subscription): array
    {
        $plan = $subscription->plan;
        $grossCents = max(0, (int) $subscription->monthly_price_cents - (int) $subscription->sponsor_discount_cents);
        $bankTransfer = $subscription->payment_provider === 'bank_transfer'
            ? ($subscription->payment_payload['bank_transfer'] ?? [])
            : null;

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'payment_status' => $subscription->payment_status,
            'payment_provider' => $subscription->payment_provider,
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'payment_reference' => $subscription->payment_reference,
            'payment_due_at' => optional($subscription->payment_due_at)->toIso8601String(),
            'payment_expires_at' => optional($subscription->created_at?->copy()->addDays(OutfitPaymentReminderService::EXPIRATION_DAYS))->toIso8601String(),
            'payment_reminders_sent' => (int) $subscription->payment_reminders_sent,
            'last_payment_reminder_sent_at' => optional($subscription->last_payment_reminder_sent_at)->toIso8601String(),
            'dunning_level' => (int) $subscription->dunning_level,
            'last_dunning_sent_at' => optional($subscription->last_dunning_sent_at)->toIso8601String(),
            'payment_paused_at' => optional($subscription->payment_paused_at)->toIso8601String(),
            'payment_paused_reason' => $subscription->payment_paused_reason,
            'can_send_payment_reminder' => $subscription->status === 'pending_payment'
                && in_array($subscription->payment_status, ['pending', 'failed'], true)
                && (int) $subscription->payment_reminders_sent < OutfitPaymentReminderService::MAX_REMINDERS,
            'monthly_price_cents' => (int) $subscription->monthly_price_cents,
            'sponsor_discount_cents' => (int) $subscription->sponsor_discount_cents,
            'total_cents' => $grossCents,
            'currency' => $subscription->currency,
            'created_at' => optional($subscription->created_at)->toIso8601String(),
            'next_delivery_at' => optional($subscription->next_delivery_at)->toIso8601String(),
            'deliveries_count' => (int) ($subscription->deliveries_count ?? 0),
            'user' => [
                'id' => $subscription->user?->id,
                'name' => $subscription->user?->name,
                'email' => $subscription->user?->email,
            ],
            'plan' => [
                'id' => $plan?->id,
                'name' => $plan?->name,
            ],
            'sponsor' => $subscription->sponsor ? [
                'id' => $subscription->sponsor->id,
                'name' => $subscription->sponsor->name,
            ] : null,
            'bank_transfer' => $bankTransfer,
            'latest_delivery' => $subscription->latestDelivery ? $this->deliverySummaryPayload($subscription->latestDelivery) : null,
        ];
    }

    private function notifyDeliveryStatus(OutfitDelivery $delivery): void
    {
        $delivery->loadMissing(['subscription.user', 'subscription.plan']);
        $subscription = $delivery->subscription;
        $user = $subscription?->user;

        if (! $user) {
            return;
        }

        AppNotification::send($user, 'outfit.delivery.'.$delivery->status, [
            'title' => $this->deliveryNotificationTitle($delivery->status),
            'message' => $this->deliveryNotificationMessage($delivery),
            'status' => $delivery->status,
            'delivery_id' => $delivery->id,
            'subscription_id' => $subscription->id,
            'tracking_number' => $delivery->tracking_number,
            'carrier' => $delivery->carrier,
            'url' => route('auth.outfit-subscriptions.index'),
        ]);

        if ($user->email) {
            $user->notify(new OutfitDeliveryStatusUpdated($delivery));
        }
    }

    private function deliveryNotificationTitle(?string $status): string
    {
        return match ($status) {
            'preparing' => 'Outfit-Lieferung in Vorbereitung',
            'shipped' => 'Outfit-Lieferung versendet',
            'delivered' => 'Outfit-Lieferung geliefert',
            'cancelled' => 'Outfit-Lieferung storniert',
            default => 'Outfit-Lieferung geplant',
        };
    }

    private function deliveryNotificationMessage(OutfitDelivery $delivery): string
    {
        $planName = $delivery->subscription?->plan?->name ?? 'Outfit-Abo';

        return match ($delivery->status) {
            'preparing' => "Deine Lieferung fuer {$planName} wird vorbereitet.",
            'shipped' => "Deine Lieferung fuer {$planName} wurde versendet.",
            'delivered' => "Deine Lieferung fuer {$planName} wurde als geliefert markiert.",
            'cancelled' => "Deine Lieferung fuer {$planName} wurde storniert.",
            default => "Deine Lieferung fuer {$planName} wurde geplant.",
        };
    }

    private function deliverySummaryPayload(OutfitDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'delivery_month' => optional($delivery->delivery_month)->toDateString(),
            'tracking_number' => $delivery->tracking_number,
            'carrier' => $delivery->carrier,
            'shipped_at' => optional($delivery->shipped_at)->toIso8601String(),
            'delivered_at' => optional($delivery->delivered_at)->toIso8601String(),
            'items' => $delivery->items ?: [],
            'notes' => $delivery->notes,
        ];
    }

    private function deliveryPayload(OutfitDelivery $delivery): array
    {
        $subscription = $delivery->subscription;

        return [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'delivery_month' => optional($delivery->delivery_month)->toDateString(),
            'tracking_number' => $delivery->tracking_number,
            'carrier' => $delivery->carrier,
            'shipped_at' => optional($delivery->shipped_at)->toIso8601String(),
            'delivered_at' => optional($delivery->delivered_at)->toIso8601String(),
            'items' => $delivery->items ?: [],
            'notes' => $delivery->notes,
            'created_at' => optional($delivery->created_at)->toIso8601String(),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'payment_reference' => $subscription->payment_reference,
                'user' => $subscription->user ? [
                    'id' => $subscription->user->id,
                    'name' => $subscription->user->name,
                    'email' => $subscription->user->email,
                ] : null,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                ] : null,
            ] : null,
        ];
    }

    private function cancelPayPalSubscription(OutfitSubscription $subscription, ?string $reason = null): void
    {
        try {
            Http::withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
                ->asForm()
                ->post($this->paypalBaseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw()
                ->collect()
                ->tap(function ($payload) use ($subscription, $reason) {
                    Http::withToken($payload->get('access_token'))
                        ->withBody(json_encode(['reason' => $reason ?: 'Airmius Outfit-Abo wurde administrativ abgebrochen.']), 'application/json')
                        ->post($this->paypalBaseUrl().'/v1/billing/subscriptions/'.$subscription->provider_subscription_id.'/cancel');
                });
        } catch (\Throwable $exception) {
            Log::warning('Admin outfit PayPal subscription cancel failed', [
                'subscription_id' => $subscription->id,
                'paypal_subscription_id' => $subscription->provider_subscription_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function paypalBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
