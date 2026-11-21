<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommerceOrderResource;
use App\Http\Resources\Api\V1\MarketplaceProductResource;
use App\Http\Resources\Api\V1\MarketplaceProductReviewResource;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductReview;
use App\Models\MarketplaceProductWishlist;
use Illuminate\Http\Request;
use App\Support\MarketplaceTaxonomy;
use Illuminate\Validation\Rule;

class CommerceController extends Controller
{
    public function products(Request $request)
    {
        $data = $request->validate([
            ...MarketplaceTaxonomy::filterRules($request),
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'size:2'],
            'min_price_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'max_price_cents' => array_merge(['nullable', 'integer', 'min:0', 'max:999999999'],
                $request->filled('min_price_cents') ? ['gte:min_price_cents'] : []),
            'min_rating' => ['nullable', 'numeric', 'between:1,5'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
        ]);
        $catalog = MarketplaceProduct::query()
            ->where('status', 'published')->where('moderation_status', 'approved')
            ->when($request->filled('country'), function ($query) use ($data) {
                $country = strtoupper($data['country']);
                $query->where(fn ($countries) => $countries->whereNull('available_countries')
                    ->orWhereJsonContains('available_countries', $country));
            });
        // Facets cover the visible catalog, not just the current page or category.
        $categories = (clone $catalog)->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->values();
        $query = MarketplaceTaxonomy::applyFilters(clone $catalog, $data)->with(['user.approvedSellerApplications', 'club', 'inventories'])
            ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
            ->withCount(['publishedReviews as reviews_count',
                'publishedReviews as verified_purchase_reviews_count' => fn ($reviews) => $reviews->where('verified_purchase', true)])
            ->when(filled($data['category'] ?? null), fn ($query) => $query->where('category', $data['category']))
            ->when(filled($data['search'] ?? null), function ($query) use ($data) {
                $term = '%'.trim($data['search']).'%';
                $query->where(fn ($search) => $search->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)->orWhere('category', 'like', $term)
                    ->orWhereHas('user.marketplaceProviderProfile', fn ($provider) => $provider->where('display_name', 'like', $term))
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)));
            })
            ->when(isset($data['min_price_cents']), fn ($query) => $query->where('price_cents', '>=', $data['min_price_cents']))
            ->when(isset($data['max_price_cents']), fn ($query) => $query->where('price_cents', '<=', $data['max_price_cents']))
            ->when(isset($data['min_rating']), fn ($query) => $query->whereIn('id',
                MarketplaceProductReview::query()->select('marketplace_product_id')->where('status', 'published')
                    ->groupBy('marketplace_product_id')->havingRaw('AVG(rating) >= CAST(? AS DECIMAL(10,4))', [$data['min_rating']])));
        foreach ([1, 2, 3, 4, 5] as $rating) {
            $query->withCount(['publishedReviews as rating_'.$rating.'_count' => fn ($reviews) => $reviews->where('rating', $rating)]);
        }
        $sort = $data['sort'] ?? 'newest';
        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $query->orderBy('price_cents', $sort === 'price_asc' ? 'asc' : 'desc')->orderBy('id');
        } else {
            $query->latest()->orderByDesc('id');
        }
        $products = $query->paginate($this->perPage($request))->withQueryString();

        return MarketplaceProductResource::collection($products)->additional([
            'meta' => ['filters' => ['categories' => $categories, 'taxonomy' => MarketplaceTaxonomy::facets($catalog)]],
        ]);
    }

    public function showProduct(Request $request, MarketplaceProduct $product)
    {
        $this->abortUnlessVisible($product, $request);

        return new MarketplaceProductResource($product->loadMissing(['user.approvedSellerApplications', 'club', 'inventories']));
    }

    public function reviews(Request $request, MarketplaceProduct $product)
    {
        $this->abortUnlessVisible($product, $request);

        $reviews = $product->publishedReviews()
            ->with('user.roles')
            ->latest()
            ->paginate($this->perPage($request));

        return MarketplaceProductReviewResource::collection($reviews);
    }

    public function storeReview(Request $request, MarketplaceProduct $product)
    {
        $this->abortUnlessVisible($product, $request);

        $order = $this->verifiedPurchaseOrder($request, $product);

        if (! $order) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => __('platform.commerce.verified_buyer_required'),
                ],
            ], 403);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = MarketplaceProductReview::query()->updateOrCreate(
            [
                'marketplace_product_id' => $product->id,
                'user_id' => $request->user()->id,
            ],
            [
                'commerce_order_id' => $order->id,
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'status' => 'published',
                'verified_purchase' => true,
            ]
        );

        return (new MarketplaceProductReviewResource($review->load('user.roles')))
            ->response()
            ->setStatusCode(201);
    }

    public function wishlist(Request $request)
    {
        $products = MarketplaceProduct::query()
            ->with(['user.approvedSellerApplications', 'club', 'inventories'])
            ->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->whereHas('wishlists', fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->paginate($this->perPage($request));

        return MarketplaceProductResource::collection($products);
    }

    public function storeWishlist(Request $request, MarketplaceProduct $product)
    {
        $this->abortUnlessPublished($product);

        MarketplaceProductWishlist::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'marketplace_product_id' => $product->id,
        ]);

        return response()->json($this->wishlistPayload($request, $product), 201);
    }

    public function destroyWishlist(Request $request, MarketplaceProduct $product)
    {
        $this->abortUnlessPublished($product);

        MarketplaceProductWishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('marketplace_product_id', $product->id)
            ->delete();

        return response()->json($this->wishlistPayload($request, $product));
    }

    public function orders(Request $request)
    {
        $orders = CommerceOrder::query()
            ->with(['club', 'items.orderable', 'returnRequests'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($this->perPage($request));

        return CommerceOrderResource::collection($orders);
    }

    public function showOrder(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return new CommerceOrderResource($order->loadMissing(['club', 'items.orderable', 'returnRequests']));
    }

    private function verifiedPurchaseOrder(Request $request, MarketplaceProduct $product): ?CommerceOrder
    {
        return CommerceOrder::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->where(function ($query) use ($product) {
                $query->where(function ($direct) use ($product) {
                    $direct->where('orderable_type', MarketplaceProduct::class)
                        ->where('orderable_id', $product->id);
                })->orWhereHas('items', function ($items) use ($product) {
                    $items->where('orderable_type', MarketplaceProduct::class)
                        ->where('orderable_id', $product->id);
                });
            })
            ->latest('completed_at')
            ->first();
    }

    private function wishlistPayload(Request $request, MarketplaceProduct $product): array
    {
        $count = MarketplaceProductWishlist::query()
            ->where('marketplace_product_id', $product->id)
            ->count();
        $isWishlisted = MarketplaceProductWishlist::query()
            ->where('marketplace_product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        return [
            'data' => [
                'marketplace_product_id' => $product->id,
                'is_wishlisted' => $isWishlisted,
                'wishlist_count' => $count,
            ],
        ];
    }

    private function abortUnlessVisible(MarketplaceProduct $product, Request $request): void
    {
        abort_unless(
            $this->isPublished($product) || $product->user_id === $request->user()->id,
            404
        );
    }

    private function abortUnlessPublished(MarketplaceProduct $product): void
    {
        abort_unless($this->isPublished($product), 404);
    }

    private function isPublished(MarketplaceProduct $product): bool
    {
        return $product->status === 'published' && $product->moderation_status === 'approved';
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
