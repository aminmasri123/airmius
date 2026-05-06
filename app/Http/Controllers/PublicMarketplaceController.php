<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\OutfitSubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicMarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'in:product,course,camp,service,outfit_subscription'],
        ]);

        $products = collect();

        if (($filters['category'] ?? null) !== 'outfit_subscription') {
            $products = MarketplaceProduct::query()
            ->with(['user:id,name', 'club:id,name'])
            ->where('status', 'published')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product));
        }

        $featuredProducts = MarketplaceProduct::query()
            ->with(['user:id,name', 'club:id,name'])
            ->where('status', 'published')
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (MarketplaceProduct $product) => $this->productCard($product));

        $outfitPlans = collect();

        if (! isset($filters['category']) || in_array($filters['category'], ['', 'outfit_subscription'], true)) {
            $outfitPlans = OutfitSubscriptionPlan::query()
                ->with('sponsor:id,name,logo,website')
                ->where('is_active', true)
                ->where('is_public', true)
                ->when($filters['search'] ?? null, function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
                })
                ->orderBy('sort_order')
                ->orderBy('monthly_price_cents')
                ->get()
                ->map(fn (OutfitSubscriptionPlan $plan) => $this->outfitPlanCard($plan));
        }

        return Inertia::render('Guest/Marketplace', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'filters' => $filters,
            'products' => $products,
            'featuredProducts' => $featuredProducts,
            'outfitPlans' => $outfitPlans,
            'sportCategories' => $this->sportCategories(),
            'categories' => [
                ['value' => '', 'label' => 'Alle'],
                ['value' => 'product', 'label' => 'Produkte'],
                ['value' => 'outfit_subscription', 'label' => 'Outfit-Abos'],
                ['value' => 'course', 'label' => 'Kurse'],
                ['value' => 'camp', 'label' => 'Camps'],
                ['value' => 'service', 'label' => 'Services'],
            ],
        ]);
    }

    public function show(MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);

        $product->load(['user:id,name', 'club:id,name']);

        return Inertia::render('Guest/MarketplaceProductShow', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'category' => $product->category,
                'price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'provider_name' => $product->club?->name ?: $product->user?->name,
            ],
        ]);
    }

    public function checkout(Request $request, MarketplaceProduct $product)
    {
        abort_unless($product->status === 'published', 404);

        $data = $request->validate([
            'guest_name' => ['required_without:login_checkout', 'nullable', 'string', 'max:255'],
            'guest_email' => ['required_without:login_checkout', 'nullable', 'email', 'max:255'],
            'provider' => ['required', Rule::in(['stripe', 'paypal', 'bank_transfer'])],
            'accepted_terms' => ['accepted'],
        ]);

        if ($request->user()) {
            $order = CommerceOrder::create([
                'user_id' => $request->user()->id,
                'club_id' => $product->club_id,
                'orderable_type' => $product::class,
                'orderable_id' => $product->id,
                'type' => 'marketplace_product',
                'provider' => $data['provider'],
                'amount_cents' => $product->price_cents,
                'commission_cents' => (int) floor($product->price_cents * ($product->commission_percent / 100)),
                'currency' => $product->currency,
                'status' => 'pending',
            ]);

            return app(CommerceCheckoutController::class)->startPublicCheckout($order);
        }

        $order = CommerceOrder::create([
            'guest_name' => $data['guest_name'],
            'guest_email' => strtolower($data['guest_email']),
            'access_token' => Str::random(64),
            'club_id' => $product->club_id,
            'orderable_type' => $product::class,
            'orderable_id' => $product->id,
            'type' => 'marketplace_product',
            'provider' => $data['provider'],
            'amount_cents' => $product->price_cents,
            'commission_cents' => (int) floor($product->price_cents * ($product->commission_percent / 100)),
            'currency' => $product->currency,
            'status' => 'pending',
        ]);

        return app(CommerceCheckoutController::class)->startPublicCheckout($order);
    }

    private function productCard(MarketplaceProduct $product): array
    {
        $rating = 42 + ($product->id % 8);

        return [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'category' => $product->category,
            'price_cents' => $product->price_cents,
            'old_price_cents' => $product->price_cents > 0 ? (int) round($product->price_cents * 1.18) : null,
            'currency' => $product->currency,
            'provider_name' => $product->club?->name ?: $product->user?->name,
            'show_url' => route('guest.marketplace.products.show', $product),
            'rating' => number_format($rating / 10, 1, ',', '.'),
            'sold_count' => 12 + ($product->id * 7 % 240),
            'badge' => match ($product->category) {
                'course' => 'Top Kurs',
                'camp' => 'Camp',
                'service' => 'Service',
                default => $product->id % 2 === 0 ? 'Deal' : 'Neu',
            },
            'visual_icon' => match ($product->category) {
                'course' => 'las la-chalkboard-teacher',
                'camp' => 'las la-campground',
                'service' => 'las la-hands-helping',
                default => 'las la-dumbbell',
            },
        ];
    }

    private function outfitPlanCard(OutfitSubscriptionPlan $plan): array
    {
        $effectivePrice = max(0, (int) $plan->monthly_price_cents - (int) $plan->sponsor_discount_cents);

        return [
            'id' => $plan->id,
            'title' => $plan->name,
            'description' => $plan->description,
            'category' => 'outfit_subscription',
            'price_cents' => $effectivePrice,
            'old_price_cents' => $plan->sponsor_discount_cents ? $plan->monthly_price_cents : null,
            'currency' => $plan->currency,
            'provider_name' => $plan->sponsor ? 'Subventioniert von '.$plan->sponsor->name : 'Airmius Outfit-Abo',
            'show_url' => route('login'),
            'rating' => '4,8',
            'sold_count' => 24 + ($plan->id * 5 % 180),
            'badge' => $plan->sponsor_discount_cents ? 'Sponsor Deal' : 'Monatsabo',
            'visual_icon' => 'las la-tshirt',
            'items_per_box' => $plan->items_per_box,
            'sports' => $plan->sports ?: [],
        ];
    }

    private function sportCategories(): array
    {
        return [
            ['label' => 'Running', 'icon' => 'las la-running', 'query' => 'lauf'],
            ['label' => 'Fussball', 'icon' => 'las la-futbol', 'query' => 'fussball'],
            ['label' => 'Fitness', 'icon' => 'las la-dumbbell', 'query' => 'fitness'],
            ['label' => 'Teamsport', 'icon' => 'las la-users', 'query' => 'team'],
            ['label' => 'Recovery', 'icon' => 'las la-heartbeat', 'query' => 'recovery'],
            ['label' => 'Camps', 'icon' => 'las la-campground', 'query' => 'camp'],
            ['label' => 'Kurse', 'icon' => 'las la-video', 'query' => 'kurs'],
            ['label' => 'Services', 'icon' => 'las la-hands-helping', 'query' => 'analyse'],
        ];
    }
}
