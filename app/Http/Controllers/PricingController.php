<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Support\VisitorCountry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index(Request $request, VisitorCountry $visitorCountry)
    {
        $resolvedCountry = $visitorCountry->resolve($request, $request->user()?->country);
        $country = $resolvedCountry['country'];

        $plans = SubscriptionPlan::query()
            ->with('countryPrices')
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (SubscriptionPlan $plan) use ($country) {
                $price = $plan->priceForCountry($country);

                if (! $price['available']) {
                    return null;
                }

                return [
                    'id' => $plan->id,
                    'slug' => $plan->slug,
                    'target_actor' => $plan->target_actor ?? 'verein',
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'monthly_price_cents' => $price['monthly_price_cents'],
                    'yearly_price_cents' => $price['yearly_price_cents'],
                    'currency' => $price['currency'],
                    'pricing_country' => $price['country_code'],
                    'localized_price' => $price['localized'],
                    'base_monthly_price_cents' => $plan->monthly_price_cents,
                    'base_yearly_price_cents' => $plan->yearly_price_cents,
                    'base_currency' => $plan->currency,
                    'member_limit' => $plan->member_limit,
                    'team_limit' => $plan->team_limit,
                    'storage_gb' => $plan->storage_gb,
                    'features' => $plan->features ?? [],
                    'cta_label' => $plan->cta_label,
                    'badge' => $plan->badge,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('Guest/Pricing', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'plans' => $plans,
            'planGroups' => $plans->groupBy('target_actor'),
            'pricingCountry' => $country,
            'pricingCountrySource' => $resolvedCountry['source'],
        ]);
    }
}
