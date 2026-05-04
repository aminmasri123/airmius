<?php

namespace App\Http\Controllers;

use App\Models\OutfitSubscriptionPlan;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminOutfitSubscriptionPlanController extends Controller
{
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
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'logo', 'website']),
            'summary' => [
                'plans' => OutfitSubscriptionPlan::query()->count(),
                'activePlans' => OutfitSubscriptionPlan::query()->where('is_active', true)->count(),
                'sponsoredPlans' => OutfitSubscriptionPlan::query()->whereNotNull('sponsor_id')->count(),
                'subscriptions' => \App\Models\OutfitSubscription::query()->count(),
            ],
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

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'sponsor_id' => ['nullable', 'exists:sponsors,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
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
}
