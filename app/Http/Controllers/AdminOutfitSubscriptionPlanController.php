<?php

namespace App\Http\Controllers;

use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Models\Sport;
use App\Notifications\OutfitDeliveryStatusUpdated;
use App\Services\CommerceAuditService;
use App\Services\MediaOptimizer;
use App\Services\OutfitInvoiceService;
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
    public function __construct(
        private MediaOptimizer $mediaOptimizer,
        private CommerceAuditService $audit,
        private OutfitInvoiceService $outfitInvoices,
    ) {}

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

        return back()->with('success', 'Plan wurde gelöscht.');
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

        $before = $this->subscriptionAuditSnapshot($subscription);

        $reminders->resetDunningAfterPayment($subscription);
        $subscription->refresh();

        if (! $subscription->deliveries()->exists()) {
            OutfitDelivery::query()->create([
                'outfit_subscription_id' => $subscription->id,
                'status' => 'planned',
                'delivery_month' => now()->addMonth()->startOfMonth(),
                'items' => [],
                'notes' => trim('Zahlung bestätigt. '.($data['payment_note'] ?? 'Erste personalisierte Box wird vorbereitet.')),
            ]);
        }

        $this->outfitInvoices->createPaidInvoice($subscription->fresh(['plan', 'sponsor']));

        $this->audit->log(
            'outfit.subscription.marked_paid',
            $subscription,
            $before,
            $this->subscriptionAuditSnapshot($subscription->fresh()),
            $data['payment_note'] ?? null,
        );

        AppNotification::send($subscription->user_id, 'outfit.subscription.paid', [
            'title' => 'Outfit-Abo aktiviert',
            'message' => 'Deine Zahlung für '.$subscription->plan?->name.' wurde bestätigt. Dein Outfit-Abo ist jetzt aktiv.',
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Zahlung wurde bestätigt und das Outfit-Abo aktiviert.');
    }

    public function markSubscriptionUnpaid(Request $request, OutfitSubscription $subscription)
    {
        $data = $request->validate([
            'payment_due_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $this->subscriptionAuditSnapshot($subscription);

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

        $this->audit->log(
            'outfit.subscription.marked_unpaid',
            $subscription,
            $before,
            $this->subscriptionAuditSnapshot($subscription->fresh()),
            $data['reason'] ?? null,
        );

        AppNotification::send($subscription->user_id, 'outfit.payment.opened_by_admin', [
            'title' => 'Outfit-Abo Zahlung offen',
            'message' => trim('Für dein Outfit-Abo '.$subscription->plan?->name.' wurde eine offene Zahlung hinterlegt. '.($data['reason'] ?? '')),
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Outfit-Abo wurde als offene Zahlung markiert. Mahnungen laufen automatisch.');
    }

    public function updateShippingAddress(Request $request, OutfitSubscription $subscription)
    {
        $data = $this->validatedShippingAddress($request);
        $before = $this->subscriptionAuditSnapshot($subscription);

        $subscription->forceFill($data)->save();

        $this->audit->log(
            'outfit.subscription.shipping_address_updated',
            $subscription,
            $before,
            $this->subscriptionAuditSnapshot($subscription->fresh()),
        );

        AppNotification::send($subscription->user_id, 'outfit.subscription.shipping_address_updated', [
            'title' => 'Lieferadresse aktualisiert',
            'message' => 'Die Lieferadresse für dein Outfit-Abo '.$subscription->plan?->name.' wurde aktualisiert.',
            'url' => route('auth.outfit-subscriptions.index'),
            'subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Lieferadresse wurde aktualisiert.');
    }

    public function remindPayment(OutfitSubscription $subscription, OutfitPaymentReminderService $reminders)
    {
        if (! $reminders->send($subscription)) {
            return back()->with('success', 'Erinnerung konnte nicht gesendet werden. Die Zahlung ist nicht offen oder das Limit von 3 Erinnerungen ist erreicht.');
        }

        $this->audit->log(
            'outfit.subscription.payment_reminder_sent',
            $subscription,
            [],
            $this->subscriptionAuditSnapshot($subscription->fresh()),
        );

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

        $before = $this->subscriptionAuditSnapshot($subscription);

        $subscription->forceFill([
            'status' => 'cancelled',
            'payment_status' => $subscription->payment_status === 'paid' ? 'paid' : 'cancelled',
            'cancelled_at' => now(),
            'next_delivery_at' => null,
            'current_period_ends_at' => null,
        ])->save();

        $this->audit->log(
            'outfit.subscription.cancelled_by_admin',
            $subscription,
            $before,
            $this->subscriptionAuditSnapshot($subscription->fresh()),
            $data['reason'] ?? null,
        );

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
            $this->cancelPayPalSubscription($subscription, 'Abo wurde durch Admin gelöscht.');
        }

        $this->audit->log(
            'outfit.subscription.deleted_by_admin',
            $subscription,
            $this->subscriptionAuditSnapshot($subscription),
            [],
        );

        $subscription->delete();

        return back()->with('success', 'Outfit-Abo wurde gelöscht.');
    }

    public function updateDelivery(Request $request, OutfitDelivery $delivery)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:planned,preparing,shipped,delivered,cancelled'],
            'delivery_month' => ['nullable', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
            'carrier' => ['nullable', 'string', 'max:120'],
            'items_text' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $previousStatus = $delivery->status;
        $before = $this->deliveryAuditSnapshot($delivery);
        $status = $data['status'];

        $delivery->update([
            'status' => $status,
            'delivery_month' => $data['delivery_month'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'tracking_url' => $data['tracking_url'] ?? null,
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

        $this->audit->log(
            'outfit.delivery.updated',
            $delivery,
            $before,
            $this->deliveryAuditSnapshot($delivery->fresh()),
        );

        return back()->with('success', 'Lieferung wurde aktualisiert.');
    }

    public function updateDeliveryIssue(Request $request, OutfitDelivery $delivery)
    {
        $data = $request->validate([
            'issue_status' => ['required', 'string', 'in:open,reviewing,approved,return_waiting,replacement_preparing,resolved,rejected'],
            'issue_admin_note' => ['nullable', 'string', 'max:2000'],
            'return_tracking_number' => ['nullable', 'string', 'max:120'],
            'return_tracking_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $before = $this->deliveryAuditSnapshot($delivery);
        $previousStatus = $delivery->issue_status;
        $resolved = in_array($data['issue_status'], ['resolved', 'rejected'], true);

        $delivery->forceFill([
            'issue_status' => $data['issue_status'],
            'issue_admin_note' => $data['issue_admin_note'] ?? null,
            'return_tracking_number' => $data['return_tracking_number'] ?? null,
            'return_tracking_url' => $data['return_tracking_url'] ?? null,
            'issue_resolved_at' => $resolved ? ($delivery->issue_resolved_at ?: now()) : null,
        ])->save();

        $this->audit->log(
            'outfit.delivery.issue_updated',
            $delivery,
            $before,
            $this->deliveryAuditSnapshot($delivery->fresh()),
        );

        if ($previousStatus !== $delivery->issue_status) {
            $this->notifyDeliveryIssueStatus($delivery->fresh(['subscription.user', 'subscription.plan']));
        }

        return back()->with('success', 'Support-Vorgang wurde aktualisiert.');
    }

    public function markDeliveryShipped(Request $request, OutfitDelivery $delivery)
    {
        $data = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
            'carrier' => ['nullable', 'string', 'max:120'],
        ]);

        $previousStatus = $delivery->status;
        $before = $this->deliveryAuditSnapshot($delivery);

        $delivery->update([
            'status' => 'shipped',
            'tracking_number' => $data['tracking_number'] ?? $delivery->tracking_number,
            'tracking_url' => $data['tracking_url'] ?? $delivery->tracking_url,
            'carrier' => $data['carrier'] ?? $delivery->carrier,
            'shipped_at' => $delivery->shipped_at ?: now(),
        ]);

        if ($previousStatus !== 'shipped') {
            $this->notifyDeliveryStatus($delivery->fresh());
        }

        $this->audit->log(
            'outfit.delivery.marked_shipped',
            $delivery,
            $before,
            $this->deliveryAuditSnapshot($delivery->fresh()),
        );

        return back()->with('success', 'Lieferung wurde als versendet markiert.');
    }

    public function markDeliveryDelivered(OutfitDelivery $delivery)
    {
        $previousStatus = $delivery->status;
        $before = $this->deliveryAuditSnapshot($delivery);

        $delivery->update([
            'status' => 'delivered',
            'shipped_at' => $delivery->shipped_at ?: now(),
            'delivered_at' => $delivery->delivered_at ?: now(),
        ]);

        if ($previousStatus !== 'delivered') {
            $this->notifyDeliveryStatus($delivery->fresh());
        }

        $this->audit->log(
            'outfit.delivery.marked_delivered',
            $delivery,
            $before,
            $this->deliveryAuditSnapshot($delivery->fresh()),
        );

        return back()->with('success', 'Lieferung wurde als geliefert markiert.');
    }

    public function destroyDelivery(Request $request, OutfitDelivery $delivery)
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:delete'],
        ]);

        $this->audit->log(
            'outfit.delivery.deleted_by_admin',
            $delivery,
            $this->deliveryAuditSnapshot($delivery),
            [],
        );

        $delivery->delete();

        return back()->with('success', 'Lieferung wurde gelöscht.');
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
        $data['contract_title'] = ($data['contract_title'] ?? null) ?: null;
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
            'shipping_address' => $this->shippingAddressPayload($subscription),
            'latest_delivery' => $subscription->latestDelivery ? $this->deliverySummaryPayload($subscription->latestDelivery) : null,
        ];
    }

    private function shippingAddressPayload(OutfitSubscription $subscription): array
    {
        return [
            'name' => $subscription->shipping_name,
            'country' => $subscription->shipping_country,
            'street' => $subscription->shipping_street,
            'house_number' => $subscription->shipping_house_number,
            'postal_code' => $subscription->shipping_postal_code,
            'city' => $subscription->shipping_city,
            'state' => $subscription->shipping_state,
            'note' => $subscription->shipping_note,
        ];
    }

    private function validatedShippingAddress(Request $request): array
    {
        $data = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_country' => ['required', 'string', 'size:2'],
            'shipping_street' => ['required', 'string', 'max:255'],
            'shipping_house_number' => ['nullable', 'string', 'max:40'],
            'shipping_postal_code' => ['required', 'string', 'max:30'],
            'shipping_city' => ['required', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_note' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'shipping_name' => trim($data['shipping_name']),
            'shipping_country' => strtoupper(trim($data['shipping_country'])),
            'shipping_street' => trim($data['shipping_street']),
            'shipping_house_number' => filled($data['shipping_house_number'] ?? null) ? trim($data['shipping_house_number']) : null,
            'shipping_postal_code' => trim($data['shipping_postal_code']),
            'shipping_city' => trim($data['shipping_city']),
            'shipping_state' => filled($data['shipping_state'] ?? null) ? trim($data['shipping_state']) : null,
            'shipping_note' => filled($data['shipping_note'] ?? null) ? trim($data['shipping_note']) : null,
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
            'tracking_url' => $delivery->tracking_url,
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
            'preparing' => "Deine Lieferung für {$planName} wird vorbereitet.",
            'shipped' => "Deine Lieferung für {$planName} wurde versendet.",
            'delivered' => "Deine Lieferung für {$planName} wurde als geliefert markiert.",
            'cancelled' => "Deine Lieferung für {$planName} wurde storniert.",
            default => "Deine Lieferung für {$planName} wurde geplant.",
        };
    }

    private function deliverySummaryPayload(OutfitDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'delivery_month' => optional($delivery->delivery_month)->toDateString(),
            'tracking_number' => $delivery->tracking_number,
            'tracking_url' => $delivery->tracking_url,
            'carrier' => $delivery->carrier,
            'shipped_at' => optional($delivery->shipped_at)->toIso8601String(),
            'delivered_at' => optional($delivery->delivered_at)->toIso8601String(),
            'items' => $delivery->items ?: [],
            'notes' => $delivery->notes,
            'issue' => $this->deliveryIssuePayload($delivery),
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
            'tracking_url' => $delivery->tracking_url,
            'carrier' => $delivery->carrier,
            'shipped_at' => optional($delivery->shipped_at)->toIso8601String(),
            'delivered_at' => optional($delivery->delivered_at)->toIso8601String(),
            'items' => $delivery->items ?: [],
            'notes' => $delivery->notes,
            'issue' => $this->deliveryIssuePayload($delivery),
            'created_at' => optional($delivery->created_at)->toIso8601String(),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'payment_reference' => $subscription->payment_reference,
                'shipping_address' => $this->shippingAddressPayload($subscription),
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

    private function subscriptionAuditSnapshot(OutfitSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'outfit_subscription_plan_id' => $subscription->outfit_subscription_plan_id,
            'status' => $subscription->status,
            'payment_status' => $subscription->payment_status,
            'payment_provider' => $subscription->payment_provider,
            'payment_reference' => $subscription->payment_reference,
            'monthly_price_cents' => (int) $subscription->monthly_price_cents,
            'currency' => $subscription->currency,
            'shipping_address' => $this->shippingAddressPayload($subscription),
            'payment_due_at' => optional($subscription->payment_due_at)->toIso8601String(),
            'next_delivery_at' => optional($subscription->next_delivery_at)->toIso8601String(),
            'current_period_ends_at' => optional($subscription->current_period_ends_at)->toIso8601String(),
            'cancelled_at' => optional($subscription->cancelled_at)->toIso8601String(),
        ];
    }

    private function deliveryAuditSnapshot(OutfitDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'outfit_subscription_id' => $delivery->outfit_subscription_id,
            'status' => $delivery->status,
            'delivery_month' => optional($delivery->delivery_month)->toDateString(),
            'tracking_number' => $delivery->tracking_number,
            'tracking_url' => $delivery->tracking_url,
            'carrier' => $delivery->carrier,
            'items' => $delivery->items ?: [],
            'notes' => $delivery->notes,
            'issue' => $this->deliveryIssuePayload($delivery),
            'shipped_at' => optional($delivery->shipped_at)->toIso8601String(),
            'delivered_at' => optional($delivery->delivered_at)->toIso8601String(),
        ];
    }

    private function deliveryIssuePayload(OutfitDelivery $delivery): ?array
    {
        if (! $delivery->issue_status && ! $delivery->issue_type) {
            return null;
        }

        return [
            'type' => $delivery->issue_type,
            'status' => $delivery->issue_status,
            'description' => $delivery->issue_description,
            'requested_resolution' => $delivery->issue_requested_resolution,
            'exchange_size' => $delivery->issue_exchange_size,
            'admin_note' => $delivery->issue_admin_note,
            'return_tracking_number' => $delivery->return_tracking_number,
            'return_tracking_url' => $delivery->return_tracking_url,
            'requested_at' => optional($delivery->issue_requested_at)->toIso8601String(),
            'resolved_at' => optional($delivery->issue_resolved_at)->toIso8601String(),
        ];
    }

    private function notifyDeliveryIssueStatus(OutfitDelivery $delivery): void
    {
        $user = $delivery->subscription?->user;

        if (! $user) {
            return;
        }

        AppNotification::send($user, 'outfit.delivery.issue_status_updated', [
            'title' => 'Support-Vorgang aktualisiert',
            'message' => $this->deliveryIssueStatusMessage($delivery),
            'delivery_id' => $delivery->id,
            'subscription_id' => $delivery->outfit_subscription_id,
            'issue_status' => $delivery->issue_status,
            'return_tracking_number' => $delivery->return_tracking_number,
            'return_tracking_url' => $delivery->return_tracking_url,
            'url' => route('auth.outfit-subscriptions.index'),
        ]);
    }

    private function deliveryIssueStatusMessage(OutfitDelivery $delivery): string
    {
        $planName = $delivery->subscription?->plan?->name ?? 'Outfit-Abo';

        return match ($delivery->issue_status) {
            'reviewing' => "Deine Meldung für {$planName} wird geprüft.",
            'approved' => "Deine Meldung für {$planName} wurde freigegeben.",
            'return_waiting' => "Wir warten auf deine Rücksendung für {$planName}.",
            'replacement_preparing' => "Dein Ersatz für {$planName} wird vorbereitet.",
            'resolved' => "Dein Support-Vorgang für {$planName} wurde gelöst.",
            'rejected' => "Dein Support-Vorgang für {$planName} wurde abgeschlossen.",
            default => "Dein Support-Vorgang für {$planName} wurde aktualisiert.",
        };
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
