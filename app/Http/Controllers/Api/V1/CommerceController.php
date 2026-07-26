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

class CommerceController extends Controller
{
    public function products(Request $request)
    {
        $products = MarketplaceProduct::query()
            ->with(['user.approvedSellerApplications', 'club', 'inventories'])
            ->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('country'), function ($query) use ($request) {
                $country = strtoupper((string) $request->string('country'));

                $query->where(function ($countries) use ($country) {
                    $countries
                        ->whereNull('available_countries')
                        ->orWhereJsonContains('available_countries', $country);
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return MarketplaceProductResource::collection($products);
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
                    'message' => 'Nur verifizierte Kaeufer koennen dieses Produkt bewerten.',
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
