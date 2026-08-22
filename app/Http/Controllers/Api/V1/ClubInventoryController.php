<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\ClubInventoryMaintenanceRecord;
use App\Support\ClubPermissions;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubInventoryController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $canManage = $this->authorizeAccess($request, $club);

        $items = ClubInventoryItem::query()
            ->where('club_id', $club->id)
            ->withCount([
                'loans as active_loans_count' => fn ($query) => $query->where('status', 'active'),
                'maintenanceRecords as open_maintenance_count' => fn ($query) => $query->whereIn('status', ['open', 'in_progress']),
            ])
            ->orderBy('name')
            ->get();

        $loans = ClubInventoryLoan::query()
            ->where('club_id', $club->id)
            ->when(! $canManage, fn ($query) => $query->where('borrower_id', $request->user()->id))
            ->with(['item:id,name,sku', 'borrower:id,name'])
            ->latest('id')
            ->limit(200)
            ->get();

        $maintenance = $canManage
            ? ClubInventoryMaintenanceRecord::query()
                ->where('club_id', $club->id)
                ->with(['item:id,name,sku', 'reporter:id,name'])
                ->latest('id')
                ->limit(200)
                ->get()
            : collect();

        return response()->json([
            'data' => [
                'can_manage' => $canManage,
                'items' => $items->map(fn (ClubInventoryItem $item) => $this->itemPayload($item, $canManage)),
                'loans' => $loans,
                'maintenance' => $maintenance,
            ],
        ]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $this->validateItem($request, $club);
        $data['quantity_available'] = $data['quantity_total'];

        $item = $club->inventoryItems()->create($data);

        return response()->json(['message' => __('organization.inventory.item_created'), 'data' => $this->itemPayload($item, true)], 201);
    }

    public function update(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->authorizeManage($request, $club);
        $this->ensureItemClub($club, $item);
        $data = $this->validateItem($request, $club, $item);
        $checkedOut = max(0, $item->quantity_total - $item->quantity_available);

        if ($data['quantity_total'] < $checkedOut) {
            throw ValidationException::withMessages(['quantity_total' => __('organization.inventory.quantity_below_checked_out')]);
        }

        $data['quantity_available'] = $data['quantity_total'] - $checkedOut;
        $item->update($data);

        return response()->json(['message' => __('organization.inventory.item_updated'), 'data' => $this->itemPayload($item->refresh(), true)]);
    }

    public function scan(Request $request, Club $club)
    {
        $canManage = $this->authorizeAccess($request, $club);
        $data = $request->validate(['qr_token' => ['required', 'uuid']]);
        $item = ClubInventoryItem::query()->where('club_id', $club->id)->where('qr_token', $data['qr_token'])->firstOrFail();

        return response()->json(['data' => $this->itemPayload($item, $canManage)]);
    }

    public function checkout(Request $request, Club $club, ClubInventoryItem $item)
    {
        $canManage = $this->authorizeAccess($request, $club);
        $this->ensureItemClub($club, $item);
        $data = $request->validate([
            'borrower_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $borrowerId = $canManage && ! empty($data['borrower_id']) ? (int) $data['borrower_id'] : (int) $request->user()->id;
        $this->ensureActiveMember($club, $borrowerId);

        $loan = DB::transaction(function () use ($request, $club, $item, $data, $borrowerId, $canManage) {
            $lockedItem = ClubInventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($lockedItem->status === 'active', 422, __('organization.inventory.item_unavailable'));
            $pending = $lockedItem->requires_approval && ! $canManage;

            if (! $pending) {
                $this->reserveQuantity($lockedItem, (int) $data['quantity']);
            }

            return ClubInventoryLoan::query()->create([
                'club_id' => $club->id,
                'club_inventory_item_id' => $lockedItem->id,
                'borrower_id' => $borrowerId,
                'checked_out_by' => $pending ? null : $request->user()->id,
                'quantity' => (int) $data['quantity'],
                'status' => $pending ? 'pending' : 'active',
                'checked_out_at' => $pending ? null : now(),
                'due_at' => $data['due_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => $loan->status === 'pending'
                ? __('organization.inventory.loan_requested')
                : __('organization.inventory.item_checked_out'),
            'data' => $loan->load(['item:id,name,sku', 'borrower:id,name']),
        ], 201);
    }

    public function approve(Request $request, Club $club, ClubInventoryLoan $loan)
    {
        $this->authorizeManage($request, $club);
        $this->ensureLoanClub($club, $loan);

        DB::transaction(function () use ($request, $loan): void {
            $lockedLoan = ClubInventoryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless($lockedLoan->status === 'pending', 422, __('organization.inventory.only_pending_can_be_approved'));
            $item = ClubInventoryItem::query()->lockForUpdate()->findOrFail($lockedLoan->club_inventory_item_id);
            $this->reserveQuantity($item, $lockedLoan->quantity);
            $lockedLoan->update(['status' => 'active', 'checked_out_by' => $request->user()->id, 'checked_out_at' => now()]);
        });

        return response()->json(['message' => __('organization.inventory.loan_approved'), 'data' => $loan->refresh()->load(['item:id,name,sku', 'borrower:id,name'])]);
    }

    public function reject(Request $request, Club $club, ClubInventoryLoan $loan)
    {
        $this->authorizeManage($request, $club);
        $this->ensureLoanClub($club, $loan);
        abort_unless($loan->status === 'pending', 422, __('organization.inventory.only_pending_can_be_rejected'));
        $loan->update(['status' => 'rejected', 'returned_to' => $request->user()->id, 'returned_at' => now()]);

        return response()->json(['message' => __('organization.inventory.loan_rejected'), 'data' => $loan->refresh()]);
    }

    public function returnLoan(Request $request, Club $club, ClubInventoryLoan $loan)
    {
        $canManage = $this->authorizeAccess($request, $club);
        $this->ensureLoanClub($club, $loan);
        abort_unless($canManage || (int) $loan->borrower_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'return_condition' => ['nullable', Rule::in(['new', 'good', 'worn', 'damaged'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $loan, $data): void {
            $lockedLoan = ClubInventoryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless($lockedLoan->status === 'active', 422, __('organization.inventory.loan_not_active'));
            $item = ClubInventoryItem::query()->lockForUpdate()->findOrFail($lockedLoan->club_inventory_item_id);
            $item->increment('quantity_available', $lockedLoan->quantity);
            $lockedLoan->update([
                'status' => 'returned',
                'returned_to' => $request->user()->id,
                'returned_at' => now(),
                'return_condition' => $data['return_condition'] ?? null,
                'notes' => $data['notes'] ?? $lockedLoan->notes,
            ]);
        });

        return response()->json(['message' => __('organization.inventory.return_recorded'), 'data' => $loan->refresh()->load(['item:id,name,sku', 'borrower:id,name'])]);
    }

    public function storeMaintenance(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->authorizeManage($request, $club);
        $this->ensureItemClub($club, $item);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);
        $record = $item->maintenanceRecords()->create([
            ...$data,
            'club_id' => $club->id,
            'reported_by' => $request->user()->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json(['message' => __('organization.inventory.maintenance_created'), 'data' => $record->load('item:id,name,sku')], 201);
    }

    public function updateMaintenance(Request $request, Club $club, ClubInventoryMaintenanceRecord $maintenance)
    {
        $this->authorizeManage($request, $club);
        abort_unless((int) $maintenance->club_id === (int) $club->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);
        $maintenance->update([
            ...$data,
            'resolved_by' => $data['status'] === 'completed' ? $request->user()->id : null,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return response()->json(['message' => __('organization.inventory.maintenance_updated'), 'data' => $maintenance->refresh()->load('item:id,name,sku')]);
    }

    private function validateItem(Request $request, Club $club, ?ClubInventoryItem $item = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('club_inventory_items')->where('club_id', $club->id)->ignore($item?->id)],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quantity_total' => ['required', 'integer', 'min:1', 'max:100000'],
            'condition' => ['required', Rule::in(['new', 'good', 'worn', 'damaged'])],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'requires_approval' => ['boolean'],
        ]);
    }

    private function authorizeAccess(Request $request, Club $club): bool
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::INVENTORY_VIEW), 403);

        return ClubPermissions::allows($club, $request->user(), ClubPermissions::INVENTORY_MANAGE);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::INVENTORY_MANAGE), 403);
    }

    private function ensureActiveMember(Club $club, int $userId): void
    {
        $isOwner = (int) $club->owner_id === $userId;
        $isActive = $club->users()->where('users.id', $userId)->where(function ($query) {
            $query->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active');
        })->exists();
        abort_unless($isOwner || $isActive, 422, __('organization.inventory.borrower_not_active_member'));
    }

    private function reserveQuantity(ClubInventoryItem $item, int $quantity): void
    {
        abort_if($quantity > $item->quantity_available, 422, __('organization.inventory.quantity_unavailable'));
        $item->decrement('quantity_available', $quantity);
    }

    private function ensureItemClub(Club $club, ClubInventoryItem $item): void
    {
        abort_unless((int) $item->club_id === (int) $club->id, 404);
    }

    private function ensureLoanClub(Club $club, ClubInventoryLoan $loan): void
    {
        abort_unless((int) $loan->club_id === (int) $club->id, 404);
    }

    private function itemPayload(ClubInventoryItem $item, bool $canManage): array
    {
        $payload = $item->only([
            'id', 'club_id', 'name', 'sku', 'category', 'location', 'description',
            'quantity_total', 'quantity_available', 'condition', 'status', 'requires_approval',
        ]);
        $payload['active_loans_count'] = (int) ($item->active_loans_count ?? 0);
        $payload['open_maintenance_count'] = (int) ($item->open_maintenance_count ?? 0);

        if ($canManage) {
            $svg = (new Writer(new ImageRenderer(new RendererStyle(256, 4), new SvgImageBackEnd)))->writeString((string) $item->qr_token);
            $payload['qr_token'] = $item->qr_token;
            $payload['qr_svg_data_uri'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return $payload;
    }
}
