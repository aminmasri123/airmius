<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubBudget;
use App\Models\ClubFinanceEntry;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryMovement;
use App\Models\ClubProcurementItem;
use App\Models\ClubProcurementRequest;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubProcurementController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeProcurement($request, $club, ClubPermissions::FINANCE_VIEW);

        $requests = ClubProcurementRequest::query()
            ->where('club_id', $club->id)
            ->with(['budget.yearPeriod', 'items.inventoryItem:id,name,quantity_available', 'requester:id,name', 'approver:id,name', 'orderer:id,name'])
            ->latest('id')
            ->get();

        return response()->json(['data' => [
            'procurement_requests' => $requests->map(fn (ClubProcurementRequest $procurement) => $this->payload($procurement))->values(),
            'can_manage' => ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT),
            'can_approve' => ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_APPROVE),
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeProcurement($request, $club, ClubPermissions::FINANCE_EDIT);

        $data = $this->validatedRequest($request, $club);

        $procurement = DB::transaction(function () use ($club, $request, $data) {
            $items = $data['items'];
            unset($data['items']);

            $procurement = ClubProcurementRequest::query()->create(array_replace($data, [
                'club_id' => $club->id,
                'requested_by' => $request->user()->id,
                'estimated_total_cents' => $this->totalCents($items, 'quantity_requested'),
                'ordered_total_cents' => 0,
                'received_total_cents' => 0,
                'status' => $data['status'] ?? 'draft',
                'submitted_at' => ($data['status'] ?? 'draft') === 'submitted' ? now() : null,
            ]));

            foreach ($items as $item) {
                $procurement->items()->create(array_replace($item, [
                    'quantity_ordered' => 0,
                    'quantity_received' => 0,
                ]));
            }

            $this->audit($club, $request, 'club.procurement.requested', $procurement);

            return $procurement;
        });

        return response()->json(['data' => $this->payload($procurement->refresh()->load(['budget.yearPeriod', 'items.inventoryItem', 'requester', 'approver', 'orderer']))], 201);
    }

    public function approve(Request $request, Club $club, ClubProcurementRequest $procurement)
    {
        $this->authorizeExisting($request, $club, $procurement, ClubPermissions::FINANCE_APPROVE);
        abort_if((int) $procurement->requested_by === (int) $request->user()->id, 422, __('validation.invalid'));

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $procurement->forceFill([
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'approved' ? $request->user()->id : null,
            'approved_at' => $data['status'] === 'approved' ? now() : null,
        ])->save();

        $this->audit($club, $request, $data['status'] === 'approved' ? 'club.procurement.approved' : 'club.procurement.rejected', $procurement);

        return response()->json(['data' => $this->payload($procurement->refresh()->load(['budget.yearPeriod', 'items.inventoryItem', 'requester', 'approver', 'orderer']))]);
    }

    public function order(Request $request, Club $club, ClubProcurementRequest $procurement)
    {
        $this->authorizeExisting($request, $club, $procurement, ClubPermissions::FINANCE_EDIT);
        abort_unless($procurement->status === 'approved', 422);

        $procurement = DB::transaction(function () use ($request, $club, $procurement) {
            $procurement->items()->lockForUpdate()->get()->each(function (ClubProcurementItem $item): void {
                $item->forceFill(['quantity_ordered' => $item->quantity_requested])->save();
            });

            $procurement->forceFill([
                'status' => 'ordered',
                'ordered_by' => $request->user()->id,
                'ordered_at' => now(),
                'ordered_total_cents' => $procurement->items()->get()->sum(fn (ClubProcurementItem $item) => $item->quantity_requested * $item->unit_price_cents),
            ])->save();

            $this->audit($club, $request, 'club.procurement.ordered', $procurement);

            return $procurement;
        });

        return response()->json(['data' => $this->payload($procurement->refresh()->load(['budget.yearPeriod', 'items.inventoryItem', 'requester', 'approver', 'orderer']))]);
    }

    public function receive(Request $request, Club $club, ClubProcurementRequest $procurement)
    {
        $this->authorizeExisting($request, $club, $procurement, ClubPermissions::FINANCE_EDIT);
        abort_unless(in_array($procurement->status, ['ordered', 'partially_received'], true), 422);

        $data = $request->validate([
            'received_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', Rule::exists('club_procurement_items', 'id')],
            'items.*.quantity_received' => ['required', 'integer', 'min:1', 'max:999999'],
        ]);

        $receipt = DB::transaction(function () use ($request, $club, $procurement, $data) {
            $itemsById = $procurement->items()->lockForUpdate()->get()->keyBy('id');
            $lines = collect($data['items']);
            $totalCents = 0;

            foreach ($lines as $line) {
                $item = $itemsById->get((int) $line['id']);
                abort_unless($item, 404);

                $quantity = (int) $line['quantity_received'];
                $remaining = max(0, (int) $item->quantity_ordered - (int) $item->quantity_received);
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(['items' => __('validation.invalid')]);
                }

                $totalCents += $quantity * (int) $item->unit_price_cents;
                $this->applyInventoryReceipt($club, $request, $procurement, $item, $quantity, $data['received_on']);
                $item->increment('quantity_received', $quantity);
            }

            $financeEntry = ClubFinanceEntry::query()->create([
                'club_id' => $club->id,
                'user_id' => $request->user()->id,
                'type' => 'expense',
                'account' => 'bank',
                'category' => $procurement->finance_account ?: 'procurement',
                'title' => 'Procurement '.$procurement->id,
                'amount' => round($totalCents / 100, 2),
                'booked_on' => $data['received_on'],
                'reference' => $data['reference'] ?? $procurement->reference,
                'description' => 'Generated from approved procurement receipt.',
            ]);

            $receipt = $procurement->receipts()->create([
                'club_id' => $club->id,
                'received_by' => $request->user()->id,
                'club_finance_entry_id' => $financeEntry->id,
                'received_on' => $data['received_on'],
                'total_cents' => $totalCents,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $procurement->refresh();
            $ordered = (int) $procurement->items()->sum('quantity_ordered');
            $received = (int) $procurement->items()->sum('quantity_received');
            $procurement->forceFill([
                'received_total_cents' => (int) $procurement->received_total_cents + $totalCents,
                'status' => $received >= $ordered ? 'received' : 'partially_received',
                'completed_at' => $received >= $ordered ? now() : null,
            ])->save();

            $this->audit($club, $request, 'club.procurement.received', $procurement, [
                'receipt_id' => $receipt->id,
                'finance_entry_id' => $financeEntry->id,
                'receipt_total_cents' => $totalCents,
                'status' => $procurement->status,
            ]);

            return $receipt;
        });

        return response()->json(['data' => $this->payload($procurement->refresh()->load(['budget.yearPeriod', 'items.inventoryItem', 'requester', 'approver', 'orderer'])) + [
            'last_receipt_id' => $receipt->id,
        ]]);
    }

    private function applyInventoryReceipt(Club $club, Request $request, ClubProcurementRequest $procurement, ClubProcurementItem $item, int $quantity, string $receivedOn): void
    {
        $inventory = $item->inventoryItem;
        if (! $inventory) {
            $inventory = ClubInventoryItem::query()->create([
                'club_id' => $club->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => 'procurement',
                'supplier' => $procurement->supplier,
                'purchase_price_cents' => $item->unit_price_cents,
                'purchased_on' => $receivedOn,
                'quantity_total' => 0,
                'quantity_available' => 0,
                'condition' => 'good',
                'status' => 'active',
            ]);
            $item->forceFill(['club_inventory_item_id' => $inventory->id])->save();
        }

        $before = (int) $inventory->quantity_available;
        $inventory->forceFill([
            'quantity_total' => (int) $inventory->quantity_total + $quantity,
            'quantity_available' => $before + $quantity,
            'purchase_price_cents' => $item->unit_price_cents,
            'supplier' => $procurement->supplier ?: $inventory->supplier,
            'purchased_on' => $receivedOn,
        ])->save();

        ClubInventoryMovement::query()->create([
            'club_id' => $club->id,
            'club_inventory_item_id' => $inventory->id,
            'recorded_by' => $request->user()->id,
            'type' => 'purchase',
            'quantity_delta' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $before + $quantity,
            'purchase_price_cents' => $item->unit_price_cents,
            'supplier' => $procurement->supplier,
            'occurred_on' => $receivedOn,
            'reason' => 'procurement:'.$procurement->id,
        ]);
    }

    private function validatedRequest(Request $request, Club $club): array
    {
        $data = $request->validate([
            'club_budget_id' => ['nullable', 'integer', Rule::exists('club_budgets', 'id')->where('club_id', $club->id)],
            'title' => ['required', 'string', 'max:180'],
            'supplier' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', Rule::in(['draft', 'submitted'])],
            'finance_account' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.club_inventory_item_id' => ['nullable', 'integer', Rule::exists('club_inventory_items', 'id')->where('club_id', $club->id)],
            'items.*.name' => ['required', 'string', 'max:180'],
            'items.*.sku' => ['nullable', 'string', 'max:120'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.quantity_requested' => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.unit_price_cents' => ['required', 'integer', 'min:0', 'max:999999999'],
        ]);

        if (! empty($data['club_budget_id'])) {
            $budget = ClubBudget::query()->where('club_id', $club->id)->findOrFail($data['club_budget_id']);
            if ($budget->approval_status !== 'approved') {
                throw ValidationException::withMessages(['club_budget_id' => __('validation.invalid')]);
            }
        }

        return $data;
    }

    private function totalCents(array $items, string $quantityField): int
    {
        return collect($items)->sum(fn (array $item) => (int) $item[$quantityField] * (int) $item['unit_price_cents']);
    }

    private function authorizeProcurement(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizeExisting(Request $request, Club $club, ClubProcurementRequest $procurement, string $permission): void
    {
        abort_unless((int) $procurement->club_id === (int) $club->id, 404);
        $this->authorizeProcurement($request, $club, $permission);
    }

    private function payload(ClubProcurementRequest $procurement): array
    {
        return [
            'id' => $procurement->id,
            'club_budget_id' => $procurement->club_budget_id,
            'budget_name' => $procurement->budget?->name,
            'title' => $procurement->title,
            'supplier' => $procurement->supplier,
            'status' => $procurement->status,
            'estimated_total_cents' => $procurement->estimated_total_cents,
            'ordered_total_cents' => $procurement->ordered_total_cents,
            'received_total_cents' => $procurement->received_total_cents,
            'finance_account' => $procurement->finance_account,
            'reference' => $procurement->reference,
            'requested_by' => $procurement->requester?->name,
            'approved_by' => $procurement->approver?->name,
            'ordered_by' => $procurement->orderer?->name,
            'items' => $procurement->items->map(fn (ClubProcurementItem $item) => [
                'id' => $item->id,
                'club_inventory_item_id' => $item->club_inventory_item_id,
                'inventory_name' => $item->inventoryItem?->name,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'quantity_requested' => $item->quantity_requested,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'unit_price_cents' => $item->unit_price_cents,
            ])->values(),
        ];
    }

    private function audit(Club $club, Request $request, string $type, ClubProcurementRequest $procurement, array $extra = []): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $procurement, array_replace([
            'entity_type' => 'procurement',
            'status' => $procurement->status,
            'budget_id' => $procurement->club_budget_id,
            'estimated_total_cents' => $procurement->estimated_total_cents,
        ], $extra));
    }
}
