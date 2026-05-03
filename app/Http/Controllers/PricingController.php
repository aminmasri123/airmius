<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'target_actor' => $plan->target_actor ?? 'verein',
                'name' => $plan->name,
                'description' => $plan->description,
                'monthly_price_cents' => $plan->monthly_price_cents,
                'yearly_price_cents' => $plan->yearly_price_cents,
                'currency' => $plan->currency,
                'member_limit' => $plan->member_limit,
                'team_limit' => $plan->team_limit,
                'storage_gb' => $plan->storage_gb,
                'features' => $plan->features ?? [],
                'cta_label' => $plan->cta_label,
                'badge' => $plan->badge,
            ]);

        return Inertia::render('Guest/Pricing', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'plans' => $plans,
            'planGroups' => $plans->groupBy('target_actor'),
        ]);
    }
}
