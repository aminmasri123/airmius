<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AdminOutfitSubscriptionPlanController;
use App\Http\Controllers\Controller;
use App\Models\OutfitDelivery;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Models\Sport;
use App\Services\OutfitPaymentReminderService;
use App\Support\UploadStorage;
use Illuminate\Http\Request;

class AdminOutfitController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorizeManager($request);

        $plans = OutfitSubscriptionPlan::query()
            ->with('sponsor:id,name,logo,website')
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (OutfitSubscriptionPlan $plan) => $this->planPayload($plan));

        $subscriptions = OutfitSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name', 'sponsor:id,name', 'latestDelivery'])
            ->withCount('deliveries')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (OutfitSubscription $subscription) => $this->subscriptionPayload($subscription));

        $deliveries = OutfitDelivery::query()
            ->with(['subscription.user:id,name,email', 'subscription.plan:id,name'])
            ->latest('id')
            ->limit(150)
            ->get()
            ->map(fn (OutfitDelivery $delivery) => $this->deliveryPayload($delivery));

        $heroSource = Setting::valueFor(
            'outfit_subscription_hero_image',
            '/images/marketplace/airmius_outfit_abo.webp'
        );

        return response()->json([
            'data' => [
                'summary' => [
                    'plans' => $plans->count(),
                    'active_plans' => $plans->where('is_active', true)->count(),
                    'subscriptions' => $subscriptions->count(),
                    'active_subscriptions' => $subscriptions->where('status', 'active')->count(),
                    'pending_payments' => $subscriptions->where('payment_status', 'pending')->count(),
                    'deliveries' => $deliveries->count(),
                    'open_delivery_issues' => $deliveries
                        ->whereNotIn('issue.status', [null, 'resolved', 'rejected'])
                        ->count(),
                ],
                'abilities' => ['manage' => true],
                'plans' => $plans,
                'subscriptions' => $subscriptions,
                'deliveries' => $deliveries,
                'sponsors' => Sponsor::query()
                    ->where('scope', 'outfit_subscription')
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'logo', 'website']),
                'sports' => Sport::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'category']),
                'visuals' => [
                    'hero' => [
                        'source' => $heroSource,
                        'url' => UploadStorage::url($heroSource),
                        'recommended_size' => '1920 x 1080 px',
                        'ratio' => '16:9',
                        'formats' => 'WebP, JPG, PNG',
                        'max_size' => '8 MB',
                    ],
                ],
                'options' => [
                    'delivery_statuses' => ['planned', 'preparing', 'shipped', 'delivered', 'cancelled'],
                    'issue_statuses' => ['open', 'reviewing', 'approved', 'return_waiting', 'replacement_preparing', 'resolved', 'rejected'],
                    'target_genders' => ['all', 'women', 'men', 'kids'],
                    'branding_types' => ['airmius', 'club', 'sponsor', 'mixed'],
                ],
            ],
        ]);
    }

    public function storePlan(Request $request)
    {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->store($request);

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function updatePlan(
        Request $request,
        OutfitSubscriptionPlan $outfitSubscriptionPlan
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->update(
            $request,
            $outfitSubscriptionPlan
        );

        return response()->json([
            'data' => $this->planPayload(
                $outfitSubscriptionPlan->refresh()->load('sponsor:id,name,logo,website')
            ),
        ]);
    }

    public function destroyPlan(
        Request $request,
        OutfitSubscriptionPlan $outfitSubscriptionPlan
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->destroy(
            $outfitSubscriptionPlan
        );

        return response()->json(['data' => ['deleted_or_disabled' => true]]);
    }

    public function updateVisuals(Request $request)
    {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->updateVisuals($request);

        $source = Setting::valueFor(
            'outfit_subscription_hero_image',
            '/images/marketplace/airmius_outfit_abo.webp'
        );

        return response()->json([
            'data' => ['source' => $source, 'url' => UploadStorage::url($source)],
        ]);
    }

    public function markSubscriptionPaid(
        Request $request,
        OutfitSubscription $outfitSubscription
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->markSubscriptionPaid(
            $request,
            $outfitSubscription,
            app(OutfitPaymentReminderService::class)
        );

        return response()->json([
            'data' => $this->subscriptionPayload(
                $outfitSubscription->refresh()->load([
                    'user:id,name,email',
                    'plan:id,name',
                    'sponsor:id,name',
                    'latestDelivery',
                ])->loadCount('deliveries')
            ),
        ]);
    }

    public function markSubscriptionUnpaid(
        Request $request,
        OutfitSubscription $outfitSubscription
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->markSubscriptionUnpaid(
            $request,
            $outfitSubscription
        );

        return $this->subscriptionResponse($outfitSubscription);
    }

    public function updateShippingAddress(
        Request $request,
        OutfitSubscription $outfitSubscription
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->updateShippingAddress(
            $request,
            $outfitSubscription
        );

        return $this->subscriptionResponse($outfitSubscription);
    }

    public function remindPayment(
        Request $request,
        OutfitSubscription $outfitSubscription
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->remindPayment(
            $outfitSubscription,
            app(OutfitPaymentReminderService::class)
        );

        return $this->subscriptionResponse($outfitSubscription);
    }

    public function cancelSubscription(
        Request $request,
        OutfitSubscription $outfitSubscription
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->cancelSubscription(
            $request,
            $outfitSubscription
        );

        return $this->subscriptionResponse($outfitSubscription);
    }

    public function destroySubscription(
        Request $request,
        OutfitSubscription $outfitSubscription
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->destroySubscription(
            $request,
            $outfitSubscription
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function updateDelivery(
        Request $request,
        OutfitDelivery $outfitDelivery
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->updateDelivery(
            $request,
            $outfitDelivery
        );

        return $this->deliveryResponse($outfitDelivery);
    }

    public function updateDeliveryIssue(
        Request $request,
        OutfitDelivery $outfitDelivery
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->updateDeliveryIssue(
            $request,
            $outfitDelivery
        );

        return $this->deliveryResponse($outfitDelivery);
    }

    public function markDeliveryShipped(
        Request $request,
        OutfitDelivery $outfitDelivery
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->markDeliveryShipped(
            $request,
            $outfitDelivery
        );

        return $this->deliveryResponse($outfitDelivery);
    }

    public function markDeliveryDelivered(
        Request $request,
        OutfitDelivery $outfitDelivery
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->markDeliveryDelivered(
            $outfitDelivery
        );

        return $this->deliveryResponse($outfitDelivery);
    }

    public function destroyDelivery(
        Request $request,
        OutfitDelivery $outfitDelivery
    ) {
        $this->authorizeManager($request);
        app(AdminOutfitSubscriptionPlanController::class)->destroyDelivery(
            $request,
            $outfitDelivery
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->can('outfit-subscriptions.manage'), 403);
    }

    private function subscriptionResponse(OutfitSubscription $subscription)
    {
        return response()->json([
            'data' => $this->subscriptionPayload(
                $subscription->refresh()->load([
                    'user:id,name,email',
                    'plan:id,name',
                    'sponsor:id,name',
                    'latestDelivery',
                ])->loadCount('deliveries')
            ),
        ]);
    }

    private function deliveryResponse(OutfitDelivery $delivery)
    {
        return response()->json([
            'data' => $this->deliveryPayload(
                $delivery->refresh()->load([
                    'subscription.user:id,name,email',
                    'subscription.plan:id,name',
                ])
            ),
        ]);
    }

    private function planPayload(OutfitSubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'sponsor_id' => $plan->sponsor_id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'contract_title' => $plan->contract_title,
            'contract_terms' => $plan->contract_terms ?: [],
            'minimum_term_months' => (int) $plan->minimum_term_months,
            'pause_allowed_after_months' => (int) $plan->pause_allowed_after_months,
            'cancellation_notice_days' => (int) $plan->cancellation_notice_days,
            'monthly_price_cents' => (int) $plan->monthly_price_cents,
            'sponsor_discount_cents' => (int) $plan->sponsor_discount_cents,
            'effective_monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'currency' => $plan->currency,
            'target_gender' => $plan->target_gender,
            'sizes' => $plan->sizes ?: [],
            'sports' => $plan->sports ?: [],
            'items_per_box' => (int) $plan->items_per_box,
            'branding_type' => $plan->branding_type,
            'sort_order' => (int) $plan->sort_order,
            'is_public' => (bool) $plan->is_public,
            'is_active' => (bool) $plan->is_active,
            'subscriptions_count' => (int) ($plan->subscriptions_count ?? 0),
            'sponsor' => $plan->sponsor ? [
                'id' => $plan->sponsor->id,
                'name' => $plan->sponsor->name,
                'logo' => $plan->sponsor->logo,
                'website' => $plan->sponsor->website,
            ] : null,
        ];
    }

    private function subscriptionPayload(OutfitSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'payment_status' => $subscription->payment_status,
            'payment_provider' => $subscription->payment_provider,
            'payment_reference' => $subscription->payment_reference,
            'payment_due_at' => optional($subscription->payment_due_at)->toIso8601String(),
            'payment_reminders_sent' => (int) $subscription->payment_reminders_sent,
            'can_send_payment_reminder' => $subscription->status === 'pending_payment'
                && in_array($subscription->payment_status, ['pending', 'failed'], true)
                && (int) $subscription->payment_reminders_sent < 3,
            'monthly_price_cents' => (int) $subscription->monthly_price_cents,
            'sponsor_discount_cents' => (int) $subscription->sponsor_discount_cents,
            'total_cents' => max(
                0,
                (int) $subscription->monthly_price_cents
                    - (int) $subscription->sponsor_discount_cents
            ),
            'currency' => $subscription->currency,
            'created_at' => optional($subscription->created_at)->toIso8601String(),
            'next_delivery_at' => optional($subscription->next_delivery_at)->toIso8601String(),
            'deliveries_count' => (int) ($subscription->deliveries_count ?? 0),
            'user' => $subscription->user ? [
                'id' => $subscription->user->id,
                'name' => $subscription->user->name,
                'email' => $subscription->user->email,
            ] : null,
            'plan' => $subscription->plan ? [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
            ] : null,
            'sponsor' => $subscription->sponsor ? [
                'id' => $subscription->sponsor->id,
                'name' => $subscription->sponsor->name,
            ] : null,
            'shipping_address' => $this->shippingAddressPayload($subscription),
            'latest_delivery' => $subscription->latestDelivery
                ? $this->deliverySummaryPayload($subscription->latestDelivery)
                : null,
        ];
    }

    private function deliveryPayload(OutfitDelivery $delivery): array
    {
        $subscription = $delivery->subscription;

        return [
            ...$this->deliverySummaryPayload($delivery),
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
            'issue' => [
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
            ],
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
}
