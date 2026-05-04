<?php

namespace App\Http\Controllers;

use App\Models\OutfitDelivery;
use App\Models\OutfitStyleProfile;
use App\Models\OutfitSubscription;
use App\Models\OutfitSubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OutfitSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Auth/Dashboard/OutfitSubscriptions/Index', [
            'plans' => OutfitSubscriptionPlan::query()
                ->with('sponsor:id,name,logo,website')
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price_cents')
                ->get()
                ->map(fn (OutfitSubscriptionPlan $plan) => $this->planPayload($plan)),
            'styleProfile' => $request->user()
                ->outfitStyleProfile()
                ->first(),
            'subscriptions' => $request->user()
                ->outfitSubscriptions()
                ->with(['plan.sponsor:id,name,logo,website', 'sponsor:id,name,logo,website', 'deliveries' => fn ($query) => $query->latest()->limit(6)])
                ->latest()
                ->get()
                ->map(fn (OutfitSubscription $subscription) => $this->subscriptionPayload($subscription)),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'sport_focus' => ['nullable', 'string', 'max:120'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['nullable', 'string', 'max:20'],
            'fit_preference' => ['nullable', 'string', 'max:60'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['nullable', 'string', 'max:40'],
            'excluded_colors' => ['nullable', 'array'],
            'excluded_colors.*' => ['nullable', 'string', 'max:40'],
            'brand_style' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        OutfitStyleProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return back()->with('success', 'Style-Profil gespeichert.');
    }

    public function store(Request $request, OutfitSubscriptionPlan $plan)
    {
        abort_unless($plan->is_active && $plan->is_public, 404);

        $subscription = OutfitSubscription::query()->create([
            'user_id' => $request->user()->id,
            'outfit_subscription_plan_id' => $plan->id,
            'sponsor_id' => $plan->sponsor_id,
            'status' => 'active',
            'monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'sponsor_discount_cents' => $plan->sponsor_discount_cents,
            'currency' => $plan->currency,
            'next_delivery_at' => now()->addMonth()->startOfDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        OutfitDelivery::query()->create([
            'outfit_subscription_id' => $subscription->id,
            'status' => 'planned',
            'delivery_month' => now()->addMonth()->startOfMonth(),
            'items' => [],
            'notes' => 'Erste personalisierte Box wird nach dem Style-Profil zusammengestellt.',
        ]);

        return back()->with('success', 'Sportkleidung-Abo wurde aktiviert.');
    }

    public function pause(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        $subscription->update(['status' => 'paused']);

        return back()->with('success', 'Abo wurde pausiert.');
    }

    public function resume(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        $subscription->update([
            'status' => 'active',
            'next_delivery_at' => $subscription->next_delivery_at ?: now()->addMonth()->startOfDay(),
        ]);

        return back()->with('success', 'Abo wurde fortgesetzt.');
    }

    public function cancel(Request $request, OutfitSubscription $subscription)
    {
        $this->authorizeSubscription($request, $subscription);

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return back()->with('success', 'Abo wurde gekuendigt.');
    }

    private function authorizeSubscription(Request $request, OutfitSubscription $subscription): void
    {
        abort_unless((int) $subscription->user_id === (int) $request->user()->id, 403);
    }

    private function planPayload(OutfitSubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'monthly_price_cents' => $plan->monthly_price_cents,
            'sponsor_discount_cents' => $plan->sponsor_discount_cents,
            'effective_monthly_price_cents' => $plan->effectiveMonthlyPriceCents(),
            'currency' => $plan->currency,
            'target_gender' => $plan->target_gender,
            'sizes' => $plan->sizes ?: [],
            'sports' => $plan->sports ?: [],
            'items_per_box' => $plan->items_per_box,
            'branding_type' => $plan->branding_type,
            'sponsor' => $plan->sponsor,
        ];
    }

    private function subscriptionPayload(OutfitSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'monthly_price_cents' => $subscription->monthly_price_cents,
            'sponsor_discount_cents' => $subscription->sponsor_discount_cents,
            'currency' => $subscription->currency,
            'next_delivery_at' => $subscription->next_delivery_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'cancelled_at' => $subscription->cancelled_at,
            'plan' => $subscription->plan ? $this->planPayload($subscription->plan) : null,
            'sponsor' => $subscription->sponsor,
            'deliveries' => $subscription->deliveries,
        ];
    }
}
