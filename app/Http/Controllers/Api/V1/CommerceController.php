<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommerceOrderResource;
use App\Http\Resources\Api\V1\MarketplaceProductResource;
use App\Models\CommerceOrder;
use App\Models\MarketplaceProduct;
use Illuminate\Http\Request;

class CommerceController extends Controller
{
    public function products(Request $request)
    {
        $products = MarketplaceProduct::query()
            ->with(['user', 'club'])
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
        abort_unless(
            $product->status === 'published' && $product->moderation_status === 'approved'
                || $product->user_id === $request->user()->id,
            404
        );

        return new MarketplaceProductResource($product->loadMissing(['user', 'club']));
    }

    public function orders(Request $request)
    {
        $orders = CommerceOrder::query()
            ->with(['club', 'items'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($this->perPage($request));

        return CommerceOrderResource::collection($orders);
    }

    public function showOrder(Request $request, CommerceOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return new CommerceOrderResource($order->loadMissing(['club', 'items']));
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
