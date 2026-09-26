<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeamBulkOrderResource;
use App\Models\Club;
use App\Models\MarketplaceProduct;
use App\Models\Team;
use App\Models\TeamBulkOrder;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamBulkOrderController extends Controller
{
    public function index(Request $request)
    {
        $clubId = $request->integer('club_id');
        $teamId = $request->integer('team_id');

        $orders = TeamBulkOrder::query()
            ->with(['team:id,name,club_id', 'product:id,title,sku'])
            ->withCount('items')
            ->whereHas('club.users', fn ($query) => $query->where('users.id', $request->user()->id))
            ->when($clubId, fn ($query) => $query->where('club_id', $clubId))
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->latest('order_deadline_at')
            ->paginate($this->perPage($request));

        return TeamBulkOrderResource::collection($orders);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'marketplace_product_id' => ['required', 'integer', 'exists:marketplace_products,id'],
            'title' => ['nullable', 'string', 'max:160'],
            'order_window_starts_at' => ['nullable', 'date'],
            'order_deadline_at' => ['required', 'date', 'after:now'],
        ]);

        $club = Club::query()->findOrFail($data['club_id']);
        $team = Team::query()->where('club_id', $club->id)->findOrFail($data['team_id']);
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::COMMERCE_PRODUCTS_EDIT), 403);

        $product = MarketplaceProduct::query()
            ->where('club_id', $club->id)
            ->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->findOrFail($data['marketplace_product_id']);

        $unitPrice = (int) $product->price_cents;
        $fundedShare = max(0, min($unitPrice, (int) $product->teamwear_funded_share_cents));

        $order = TeamBulkOrder::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'marketplace_product_id' => $product->id,
            'created_by' => $request->user()->id,
            'title' => $data['title'] ?? $product->title,
            'order_window_starts_at' => $data['order_window_starts_at'] ?? now(),
            'order_deadline_at' => $data['order_deadline_at'],
            'supplier_name' => $product->teamwear_supplier,
            'supplier_reference' => $product->sku,
            'unit_price_cents' => $unitPrice,
            'funded_share_cents' => $fundedShare,
            'currency' => $product->currency ?: 'EUR',
            'price_snapshot' => [
                'product_id' => $product->id,
                'title' => $product->title,
                'sku' => $product->sku,
                'price_cents' => $unitPrice,
                'funded_share_cents' => $fundedShare,
                'currency' => $product->currency ?: 'EUR',
                'supplier' => $product->teamwear_supplier,
                'variants' => $product->variants,
                'personalization_rules' => $product->teamwear_personalization_rules,
                'captured_at' => now()->toIso8601String(),
            ],
        ]);

        return (new TeamBulkOrderResource($order->load('items.user')->loadCount('items')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, TeamBulkOrder $bulkOrder)
    {
        $this->authorizeClubAccess($request, $bulkOrder);

        return new TeamBulkOrderResource($bulkOrder->load(['items.user'])->loadCount('items'));
    }

    public function order(Request $request, TeamBulkOrder $bulkOrder)
    {
        $this->authorizeTeamMember($request, $bulkOrder);

        if (! $bulkOrder->isAcceptingOrders()) {
            throw ValidationException::withMessages([
                'order_deadline_at' => __('validation.after', ['attribute' => 'order_deadline_at', 'date' => now()->toDateTimeString()]),
            ]);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'personalization' => ['nullable', 'array'],
        ]);

        $item = DB::transaction(function () use ($bulkOrder, $request, $data) {
            return $bulkOrder->items()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'quantity' => (int) $data['quantity'],
                    'personalization' => $data['personalization'] ?? null,
                    'unit_price_cents' => $bulkOrder->unit_price_cents,
                    'funded_share_cents' => $bulkOrder->funded_share_cents,
                    'payable_unit_price_cents' => max(0, $bulkOrder->unit_price_cents - $bulkOrder->funded_share_cents),
                    'currency' => $bulkOrder->currency,
                ]
            );
        });

        return response()->json([
            'data' => [
                'id' => $item->id,
                'team_bulk_order_id' => $bulkOrder->id,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'funded_share_cents' => $item->funded_share_cents,
                'payable_unit_price_cents' => $item->payable_unit_price_cents,
                'currency' => $item->currency,
            ],
        ], 201);
    }

    public function supplierExport(Request $request, TeamBulkOrder $bulkOrder)
    {
        $this->authorizeClubManager($request, $bulkOrder);

        $bulkOrder->load(['team:id,name', 'club:id,name', 'items.user:id,name,email']);

        return response()->json([
            'data' => [
                'bulk_order_id' => $bulkOrder->id,
                'club' => ['id' => $bulkOrder->club_id, 'name' => $bulkOrder->club?->name],
                'team' => ['id' => $bulkOrder->team_id, 'name' => $bulkOrder->team?->name],
                'supplier' => [
                    'name' => $bulkOrder->supplier_name,
                    'reference' => $bulkOrder->supplier_reference,
                ],
                'deadline_at' => $bulkOrder->order_deadline_at?->toIso8601String(),
                'price_snapshot' => $bulkOrder->price_snapshot,
                'lines' => $bulkOrder->items->map(fn ($item) => [
                    'member_name' => $item->user?->name,
                    'member_email' => $item->user?->email,
                    'quantity' => $item->quantity,
                    'personalization' => $item->personalization,
                    'unit_price_cents' => $item->unit_price_cents,
                    'funded_share_cents' => $item->funded_share_cents,
                    'payable_unit_price_cents' => $item->payable_unit_price_cents,
                    'currency' => $item->currency,
                ])->values(),
            ],
        ]);
    }

    private function authorizeClubAccess(Request $request, TeamBulkOrder $bulkOrder): void
    {
        abort_unless(
            $bulkOrder->club()->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists(),
            404
        );
    }

    private function authorizeTeamMember(Request $request, TeamBulkOrder $bulkOrder): void
    {
        $this->authorizeClubAccess($request, $bulkOrder);
        abort_unless($bulkOrder->team()->whereHas('users', fn ($query) => $query->where('users.id', $request->user()->id))->exists(), 403);
    }

    private function authorizeClubManager(Request $request, TeamBulkOrder $bulkOrder): void
    {
        $this->authorizeClubAccess($request, $bulkOrder);
        abort_unless(ClubPermissions::allows($bulkOrder->club, $request->user(), ClubPermissions::COMMERCE_PRODUCTS_EDIT), 403);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}
