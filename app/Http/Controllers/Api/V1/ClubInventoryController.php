<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\ClubInventoryMaintenanceRecord;
use App\Models\ClubInventoryMovement;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClubNumberRangeService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubInventoryController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeAccess($request, $club);
        $user = $request->user();

        $items = ClubInventoryItem::query()
            ->where('club_id', $club->id)
            ->withCount([
                'loans as active_loans_count' => fn ($query) => $query->where('status', 'active'),
                'maintenanceRecords as open_maintenance_count' => fn ($query) => $query->whereIn('status', ['open', 'in_progress']),
            ])
            ->with(['parent:id,club_id,name,resource_type', 'children:id,parent_id,name,resource_type,status', 'movements' => fn ($query) => $query->latest('id')->limit(20)])
            ->orderBy('name')
            ->get()
            ->filter(fn (ClubInventoryItem $item) => $this->canAccessItem($item, $user))
            ->values();
        $visibleItemIds = $items->pluck('id');

        $loans = ClubInventoryLoan::query()
            ->where('club_id', $club->id)
            ->whereIn('club_inventory_item_id', $visibleItemIds)
            ->with(['item:id,club_id,club_department_id,team_id,parent_id,resource_type,name,sku', 'borrower:id,name', 'responsibleUser:id,name', 'requester:id,name'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (ClubInventoryLoan $loan) => (int) $loan->borrower_id === (int) $user->id
                || ($loan->item && ClubPermissions::allowsForInventoryItem($loan->item, $user, ClubPermissions::INVENTORY_APPROVE)))
            ->map(function (ClubInventoryLoan $loan) use ($user) {
                $canApproveScope = $loan->item
                    && ClubPermissions::allowsForInventoryItem($loan->item, $user, ClubPermissions::INVENTORY_APPROVE);
                $loan->setAttribute('can_approve', $canApproveScope
                    && (int) ($loan->requested_by ?: $loan->borrower_id) !== (int) $user->id);
                $loan->setAttribute('can_return', (int) $loan->borrower_id === (int) $user->id || $canApproveScope);

                return $loan;
            })
            ->values();

        $editableItemIds = $items
            ->filter(fn (ClubInventoryItem $item) => ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_EDIT))
            ->pluck('id');
        $maintenance = ClubInventoryMaintenanceRecord::query()
            ->where('club_id', $club->id)
            ->whereIn('club_inventory_item_id', $editableItemIds)
            ->with(['item:id,name,sku', 'reporter:id,name', 'responsibleUser:id,name'])
            ->latest('id')
            ->limit(200)
            ->get();
        $canManage = collect([
            ClubPermissions::INVENTORY_EDIT,
            ClubPermissions::INVENTORY_APPROVE,
            ClubPermissions::INVENTORY_DELETE,
        ])->contains(fn (string $permission) => ClubPermissions::allowsAnyInventoryScope($club, $user, $permission));

        return response()->json([
            'data' => [
                'can_manage' => $canManage,
                'can_create' => ClubPermissions::allowsAnyInventoryScope($club, $user, ClubPermissions::INVENTORY_EDIT),
                'can_manage_metadata' => ClubPermissions::allows(
                    $club,
                    $request->user(),
                    ClubPermissions::METADATA_EDIT
                ),
                'items' => $items->map(fn (ClubInventoryItem $item) => $this->itemPayload($item, $user)),
                'loans' => $loans,
                'maintenance' => $maintenance,
            ],
        ]);
    }

    public function store(Request $request, Club $club)
    {
        $data = $this->validateItem($request, $club);
        $this->authorizeItemScope($request, $club, $data, ClubPermissions::INVENTORY_EDIT);
        $data['quantity_available'] = $data['quantity_total'];

        $item = DB::transaction(function () use ($request, $club, $data) {
            $allocation = null;
            if (blank($data['sku'] ?? null)) {
                $allocation = app(ClubNumberRangeService::class)->allocateDefault(
                    $club,
                    'inventory_item',
                    $request->user(),
                    (string) Str::uuid(),
                    fn (string $number) => ! ClubInventoryItem::query()
                        ->where('club_id', $club->id)
                        ->where('sku', $number)
                        ->exists(),
                );
                $data['sku'] = $allocation?->formatted_number;
            }

            $item = $club->inventoryItems()->create($data);
            if ($allocation) {
                app(ClubNumberRangeService::class)->assignTo($allocation, 'inventory_item', $item->id);
            }

            return $item;
        });

        return response()->json(['message' => __('organization.inventory.item_created'), 'data' => $this->itemPayload($item->load(['parent:id,club_id,name,resource_type', 'children:id,parent_id,name,resource_type,status']), $request->user())], 201);
    }

    public function update(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_EDIT);
        $data = $this->validateItem($request, $club, $item);
        $this->authorizeItemScope($request, $club, $data, ClubPermissions::INVENTORY_EDIT);
        $checkedOut = max(0, $item->quantity_total - $item->quantity_available);
        $reserved = (int) ($item->reserved_quantity ?? 0);

        if ($data['quantity_total'] < ($checkedOut + $reserved)) {
            throw ValidationException::withMessages(['quantity_total' => __('organization.inventory.quantity_below_checked_out')]);
        }

        $data['quantity_available'] = $data['quantity_total'] - $checkedOut;
        $item->update($data);

        return response()->json(['message' => __('organization.inventory.item_updated'), 'data' => $this->itemPayload($item->refresh()->load(['parent:id,club_id,name,resource_type', 'children:id,parent_id,name,resource_type,status']), $request->user())]);
    }

    public function destroy(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_DELETE);
        if ($item->loans()->exists() || $item->maintenanceRecords()->exists() || $item->children()->exists()) {
            throw ValidationException::withMessages(['item' => __('organization.inventory.item_has_history')]);
        }
        $item->delete();

        return response()->json(['message' => __('organization.inventory.item_deleted'), 'data' => ['deleted' => true]]);
    }

    public function scan(Request $request, Club $club)
    {
        $this->authorizeAccess($request, $club);
        $data = $request->validate(['qr_token' => ['required', 'string', 'max:2048']]);
        $verified = $this->verifyQrPayload($data['qr_token']);
        abort_unless($verified && (int) $verified['club_id'] === (int) $club->id, 404);

        $item = ClubInventoryItem::query()
            ->where('club_id', $club->id)
            ->whereKey($verified['item_id'])
            ->where('qr_token', $verified['nonce'])
            ->firstOrFail();
        abort_if($item->qr_revoked_at, 410, __('organization.inventory.qr_revoked'));
        abort_unless($this->canAccessItem($item, $request->user()), 403);

        return response()->json(['data' => $this->minimalQrPayload($item, $request->user())]);
    }

    public function reissueQr(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_EDIT);

        $item->forceFill([
            'qr_token' => (string) Str::uuid(),
            'qr_issued_at' => now(),
            'qr_revoked_at' => null,
        ])->save();

        ClubAuditLog::record($club, $request->user(), 'club.inventory.qr_reissued', $item, ['item_id' => $item->id]);

        return response()->json([
            'message' => __('organization.inventory.qr_reissued'),
            'data' => $this->itemPayload($item->refresh(), $request->user()),
        ]);
    }

    public function revokeQr(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_EDIT);

        $item->forceFill(['qr_revoked_at' => now()])->save();
        ClubAuditLog::record($club, $request->user(), 'club.inventory.qr_revoked', $item, ['item_id' => $item->id]);

        return response()->json([
            'message' => __('organization.inventory.qr_revoked'),
            'data' => $this->itemPayload($item->refresh(), $request->user()),
        ]);
    }

    public function checkout(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_VIEW);
        $canApprove = ClubPermissions::allowsForInventoryItem($item, $request->user(), ClubPermissions::INVENTORY_APPROVE);
        $data = $request->validate([
            'borrower_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'booking_priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'rental_type' => ['nullable', Rule::in(['internal', 'external'])],
            'external_renter_name' => ['nullable', 'string', 'max:255'],
            'external_renter_email' => ['nullable', 'email', 'max:255'],
            'external_renter_phone' => ['nullable', 'string', 'max:80'],
            'rental_contract_number' => ['nullable', 'string', 'max:120'],
            'create_claim' => ['boolean'],
            'exception_approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'handover_protocol' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:now'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['starts_at']) && empty($data['due_at'])) {
            throw ValidationException::withMessages(['due_at' => __('organization.inventory.booking_window_required')]);
        }
        if (! empty($data['starts_at']) && strtotime((string) $data['due_at']) <= strtotime((string) $data['starts_at'])) {
            throw ValidationException::withMessages(['due_at' => __('organization.inventory.booking_window_invalid')]);
        }
        if (! empty($data['starts_at'])) {
            $data['starts_at'] = Carbon::parse($data['starts_at'])->toDateTimeString();
            $data['due_at'] = Carbon::parse($data['due_at'])->toDateTimeString();
        }
        $rentalType = $data['rental_type'] ?? 'internal';
        if ($rentalType === 'external' && empty($data['external_renter_name'])) {
            throw ValidationException::withMessages(['external_renter_name' => __('validation.required')]);
        }
        $borrowerId = null;
        if ($rentalType === 'internal') {
            $borrowerId = $canApprove && ! empty($data['borrower_id']) ? (int) $data['borrower_id'] : (int) $request->user()->id;
            $this->ensureActiveMember($club, $borrowerId);
        }

        $loan = DB::transaction(function () use ($request, $club, $item, $data, $borrowerId, $rentalType) {
            $lockedItem = ClubInventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($lockedItem->status === 'active', 422, __('organization.inventory.item_unavailable'));
            $pending = $lockedItem->requires_approval;
            $pricing = $this->rentalPricing($lockedItem, $rentalType, (int) $data['quantity'], $data['starts_at'] ?? null, $data['due_at'] ?? null);
            $issueDecision = $this->authorizeFinancialControl(
                $club,
                $lockedItem,
                $request->user(),
                'issue',
                $this->issueDecisionAmountCents($lockedItem, (int) $data['quantity'], $pricing),
                $data['exception_approved_by'] ?? null,
            );
            $pending = $pending || $issueDecision['requires_approval'];

            if (! empty($data['starts_at'])) {
                $this->reserveWindowCapacity($lockedItem, (int) $data['quantity'], $data['starts_at'], $data['due_at'], null, (int) ($data['booking_priority'] ?? 100));
            } elseif (! $pending) {
                $this->reserveQuantity($lockedItem, (int) $data['quantity']);
            }

            $loan = ClubInventoryLoan::query()->create([
                'club_id' => $club->id,
                'club_inventory_item_id' => $lockedItem->id,
                'borrower_id' => $borrowerId,
                'responsible_user_id' => $borrowerId,
                'external_renter_name' => $rentalType === 'external' ? $data['external_renter_name'] : null,
                'external_renter_email' => $rentalType === 'external' ? ($data['external_renter_email'] ?? null) : null,
                'external_renter_phone' => $rentalType === 'external' ? ($data['external_renter_phone'] ?? null) : null,
                'requested_by' => $request->user()->id,
                'checked_out_by' => $pending ? null : $request->user()->id,
                'quantity' => (int) $data['quantity'],
                'booking_priority' => (int) ($data['booking_priority'] ?? 100),
                'rental_type' => $rentalType,
                'status' => $pending ? 'pending' : 'active',
                'rental_contract_number' => $data['rental_contract_number'] ?? $this->nextRentalContractNumber($club),
                'rental_price_cents' => $pricing['price_cents'],
                'rental_deposit_cents' => $pricing['deposit_cents'],
                'rental_deposit_held_cents' => $pricing['deposit_cents'],
                'rental_price_snapshot' => $pricing,
                'handover_protocol' => $data['handover_protocol'] ?? null,
                'checked_out_at' => $pending ? null : now(),
                'issued_at' => $pending ? null : now(),
                'starts_at' => $data['starts_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            if (! $pending && ($data['create_claim'] ?? false) && ($pricing['price_cents'] + $pricing['deposit_cents']) > 0) {
                $loan->forceFill(['rental_invoice_id' => $this->createRentalInvoice($club, $loan)->id])->save();
            }
            ClubAuditLog::record($club, $request->user(), 'club.inventory.loan_'.($pending ? 'requested' : 'checked_out'), $loan, [
                'item_id' => $lockedItem->id,
                'borrower_id' => $borrowerId,
                'rental_type' => $rentalType,
                'rental_price_cents' => $pricing['price_cents'],
                'rental_deposit_cents' => $pricing['deposit_cents'],
                'quantity' => (int) $loan->quantity,
                'financial_control' => $issueDecision['audit'],
            ]);
            if ($issueDecision['exception']) {
                $this->auditFinancialException($club, $request->user(), $loan, $issueDecision);
            }

            return $loan;
        });

        return response()->json([
            'message' => $loan->status === 'pending'
                ? __('organization.inventory.loan_requested')
                : __('organization.inventory.item_checked_out'),
            'data' => $loan->load(['item:id,name,sku', 'borrower:id,name', 'responsibleUser:id,name']),
        ], 201);
    }

    public function approve(Request $request, Club $club, ClubInventoryLoan $loan)
    {
        $this->ensureLoanClub($club, $loan);
        $this->authorizeItem($request, $loan->item, ClubPermissions::INVENTORY_APPROVE);

        DB::transaction(function () use ($request, $club, $loan): void {
            $lockedLoan = ClubInventoryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless($lockedLoan->status === 'pending', 422, __('organization.inventory.only_pending_can_be_approved'));
            abort_if(
                (int) ($lockedLoan->requested_by ?: $lockedLoan->borrower_id) === (int) $request->user()->id,
                422,
                __('organization.inventory.second_person'),
            );
            $item = ClubInventoryItem::query()->lockForUpdate()->findOrFail($lockedLoan->club_inventory_item_id);
            $pricing = is_array($lockedLoan->rental_price_snapshot) ? $lockedLoan->rental_price_snapshot : [];
            $this->authorizeFinancialControl(
                $club,
                $item,
                $request->user(),
                'issue',
                $this->issueDecisionAmountCents($item, (int) $lockedLoan->quantity, $pricing),
                $request->user()->id,
                false,
                false,
            );
            if ($lockedLoan->starts_at) {
                $this->reserveWindowCapacity($item, $lockedLoan->quantity, $lockedLoan->starts_at, $lockedLoan->due_at, $lockedLoan->id, (int) $lockedLoan->booking_priority);
            } else {
                $this->reserveQuantity($item, $lockedLoan->quantity);
            }
            $updates = ['status' => 'active', 'checked_out_by' => $request->user()->id, 'checked_out_at' => now(), 'issued_at' => now()];
            if (! $lockedLoan->rental_invoice_id && ($lockedLoan->rental_price_cents + $lockedLoan->rental_deposit_cents) > 0) {
                $updates['rental_invoice_id'] = $this->createRentalInvoice($club, $lockedLoan)->id;
            }
            $lockedLoan->update($updates);
            ClubAuditLog::record($club, $request->user(), 'club.inventory.loan_approved', $lockedLoan, [
                'item_id' => $lockedLoan->club_inventory_item_id,
                'borrower_id' => $lockedLoan->borrower_id,
                'rental_type' => $lockedLoan->rental_type,
                'requested_by' => $lockedLoan->requested_by ?: $lockedLoan->borrower_id,
                'quantity' => (int) $lockedLoan->quantity,
            ]);
        });

        return response()->json(['message' => __('organization.inventory.loan_approved'), 'data' => $loan->refresh()->load(['item:id,name,sku', 'borrower:id,name', 'responsibleUser:id,name'])]);
    }

    public function reject(Request $request, Club $club, ClubInventoryLoan $loan)
    {
        $this->ensureLoanClub($club, $loan);
        $this->authorizeItem($request, $loan->item, ClubPermissions::INVENTORY_APPROVE);
        DB::transaction(function () use ($request, $club, $loan): void {
            $lockedLoan = ClubInventoryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless($lockedLoan->status === 'pending', 422, __('organization.inventory.only_pending_can_be_rejected'));
            $lockedLoan->update(['status' => 'rejected', 'returned_to' => $request->user()->id, 'returned_at' => now()]);
            ClubAuditLog::record($club, $request->user(), 'club.inventory.loan_rejected', $lockedLoan, [
                'item_id' => $lockedLoan->club_inventory_item_id,
                'borrower_id' => $lockedLoan->borrower_id,
                'requested_by' => $lockedLoan->requested_by ?: $lockedLoan->borrower_id,
                'quantity' => (int) $lockedLoan->quantity,
            ]);
        });

        return response()->json(['message' => __('organization.inventory.loan_rejected'), 'data' => $loan->refresh()]);
    }

    public function returnLoan(Request $request, Club $club, ClubInventoryLoan $loan)
    {
        $this->ensureLoanClub($club, $loan);
        $canApprove = ClubPermissions::allowsForInventoryItem($loan->item, $request->user(), ClubPermissions::INVENTORY_APPROVE);
        abort_unless($canApprove || (int) $loan->borrower_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'outcome' => ['nullable', Rule::in(['returned', 'lost', 'responsibility_transferred'])],
            'return_condition' => ['nullable', Rule::in(['new', 'good', 'worn', 'damaged'])],
            'damage_description' => ['nullable', 'string', 'max:2000'],
            'responsibility_transferred_to' => ['nullable', 'integer', 'exists:users,id'],
            'deposit_refunded_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'damage_claim_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'return_protocol' => ['nullable', 'array'],
            'create_claim' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $club, $loan, $data): void {
            $lockedLoan = ClubInventoryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_unless($lockedLoan->status === 'active', 422, __('organization.inventory.loan_not_active'));
            $outcome = $data['outcome'] ?? 'returned';
            $transferTo = ! empty($data['responsibility_transferred_to'])
                ? (int) $data['responsibility_transferred_to']
                : null;

            if ($outcome === 'responsibility_transferred') {
                abort_unless($transferTo, 422, __('organization.inventory.transfer_recipient_required'));
                $this->ensureActiveMember($club, $transferTo);
            }

            $item = ClubInventoryItem::query()->lockForUpdate()->findOrFail($lockedLoan->club_inventory_item_id);
            if ($outcome === 'returned') {
            if (! $lockedLoan->starts_at) {
                $item->increment('quantity_available', $lockedLoan->quantity);
            }
            }
            $deposit = (int) ($lockedLoan->rental_deposit_cents ?? 0);
            $refunded = min($deposit, (int) ($data['deposit_refunded_cents'] ?? $deposit));
            $damageClaim = (int) ($data['damage_claim_cents'] ?? 0);
            $lockedLoan->update([
                'status' => $outcome,
                'returned_to' => $request->user()->id,
                'returned_at' => $outcome === 'returned' ? now() : null,
                'lost_at' => $outcome === 'lost' ? now() : null,
                'damaged_at' => ($data['return_condition'] ?? null) === 'damaged' ? now() : null,
                'responsible_user_id' => $outcome === 'responsibility_transferred' ? $transferTo : null,
                'responsibility_transferred_to' => $transferTo,
                'responsibility_transferred_at' => $outcome === 'responsibility_transferred' ? now() : null,
                'return_condition' => $data['return_condition'] ?? null,
                'damage_description' => $data['damage_description'] ?? null,
                'rental_deposit_refunded_cents' => $refunded,
                'rental_deposit_held_cents' => max(0, $deposit - $refunded),
                'rental_damage_claim_cents' => $damageClaim,
                'return_protocol' => $data['return_protocol'] ?? null,
                'notes' => $data['notes'] ?? $lockedLoan->notes,
            ]);
            if (($data['create_claim'] ?? false) && $damageClaim > 0) {
                $this->createRentalInvoice($club, $lockedLoan->refresh(), $damageClaim, 'damage');
            }
        });

        return response()->json(['message' => __('organization.inventory.return_recorded'), 'data' => $loan->refresh()->load(['item:id,name,sku', 'borrower:id,name', 'responsibleUser:id,name'])]);
    }

    public function storeMaintenance(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_EDIT);
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['maintenance', 'cleaning', 'inspection', 'repair'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'recurrence_frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'blocks_resource' => ['boolean'],
        ]);
        if (($data['blocks_resource'] ?? false) && (empty($data['starts_at']) || empty($data['ends_at']))) {
            throw ValidationException::withMessages(['ends_at' => __('organization.inventory.booking_window_required')]);
        }
        $responsibleId = $data['responsible_user_id'] ?? null;
        if ($responsibleId) {
            $this->ensureActiveMember($club, (int) $responsibleId);
        }
        $record = $item->maintenanceRecords()->create([
            ...$data,
            'type' => $data['type'] ?? 'maintenance',
            'recurrence_interval' => $data['recurrence_interval'] ?? 1,
            'club_id' => $club->id,
            'reported_by' => $request->user()->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        if ($record->blocks_resource && $record->starts_at && $record->ends_at) {
            $this->applyMaintenanceResourceLock($record->refresh(), $item);
        }

        return response()->json(['message' => __('organization.inventory.maintenance_created'), 'data' => $record->refresh()->load(['item:id,name,sku', 'responsibleUser:id,name'])], 201);
    }

    public function reportDamage(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_EDIT);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'severity' => ['required', Rule::in(['minor', 'major', 'critical'])],
            'status' => ['nullable', Rule::in(['open', 'in_progress'])],
            'booking_impact' => ['required', Rule::in(['none', 'warning', 'partial_block', 'full_block'])],
            'estimated_cost_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'photos' => ['nullable', 'array', 'max:12'],
            'photos.*.storage_disk' => ['required_with:photos', 'string', 'max:80'],
            'photos.*.storage_path' => ['required_with:photos', 'string', 'max:500'],
            'photos.*.mime_type' => ['required_with:photos', 'string', Rule::in(['image/jpeg', 'image/png', 'image/webp'])],
            'photos.*.size_bytes' => ['required_with:photos', 'integer', 'min:1', 'max:15728640'],
            'photos.*.sha256' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'photos.*.caption' => ['nullable', 'string', 'max:160'],
        ]);
        if (in_array($data['booking_impact'], ['partial_block', 'full_block'], true) && (empty($data['starts_at']) || empty($data['ends_at']))) {
            throw ValidationException::withMessages(['ends_at' => __('organization.inventory.booking_window_required')]);
        }
        if (! empty($data['responsible_user_id'])) {
            $this->ensureActiveMember($club, (int) $data['responsible_user_id']);
        }

        $record = DB::transaction(function () use ($request, $club, $item, $data): ClubInventoryMaintenanceRecord {
            $lockedItem = ClubInventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $photos = $this->protectedDamagePhotoManifest($data['photos'] ?? []);
            $record = $lockedItem->maintenanceRecords()->create([
                'club_id' => $club->id,
                'reported_by' => $request->user()->id,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'type' => 'damage',
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'open',
                'severity' => $data['severity'],
                'booking_impact' => $data['booking_impact'],
                'estimated_cost_cents' => $data['estimated_cost_cents'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'blocks_resource' => in_array($data['booking_impact'], ['partial_block', 'full_block'], true),
                'protected_photo_manifest' => $photos,
                'opened_at' => now(),
                'damage_reported_at' => now(),
            ]);

            $lockedItem->forceFill([
                'condition' => 'damaged',
                'status' => $data['booking_impact'] === 'full_block' ? 'maintenance' : $lockedItem->status,
            ])->save();

            if ($record->blocks_resource && $record->starts_at && $record->ends_at) {
                $this->applyMaintenanceResourceLock($record->refresh(), $lockedItem);
            }

            ClubAuditLog::record($club, $request->user(), 'club.inventory.damage_reported', $record, [
                'item_id' => $lockedItem->id,
                'severity' => $record->severity,
                'booking_impact' => $record->booking_impact,
                'estimated_cost_cents' => $record->estimated_cost_cents,
                'photos_count' => count($photos),
                'blocks_resource' => (bool) $record->blocks_resource,
            ]);

            return $record;
        });

        return response()->json([
            'message' => __('organization.inventory.maintenance_created'),
            'data' => $record->refresh()->load(['item:id,name,sku,status,condition', 'reporter:id,name', 'responsibleUser:id,name']),
        ], 201);
    }

    public function updateMaintenance(Request $request, Club $club, ClubInventoryMaintenanceRecord $maintenance)
    {
        abort_unless((int) $maintenance->club_id === (int) $club->id, 404);
        $this->authorizeItem($request, $maintenance->item, ClubPermissions::INVENTORY_EDIT);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'blocks_resource' => ['boolean'],
        ]);
        if (array_key_exists('responsible_user_id', $data) && $data['responsible_user_id']) {
            $this->ensureActiveMember($club, (int) $data['responsible_user_id']);
        }
        $maintenance->update([
            ...$data,
            'resolved_by' => $data['status'] === 'completed' ? $request->user()->id : null,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);
        if ($maintenance->refresh()->blocks_resource && $maintenance->starts_at && $maintenance->ends_at) {
            $this->applyMaintenanceResourceLock($maintenance, $maintenance->item);
        }
        if ($maintenance->status === 'completed') {
            $this->createNextRecurringMaintenance($maintenance);
        }

        return response()->json(['message' => __('organization.inventory.maintenance_updated'), 'data' => $maintenance->refresh()->load(['item:id,name,sku', 'responsibleUser:id,name'])]);
    }

    private function protectedDamagePhotoManifest(array $photos): array
    {
        return collect($photos)
            ->map(fn (array $photo): array => [
                'storage_disk' => $photo['storage_disk'],
                'storage_path' => $photo['storage_path'],
                'mime_type' => $photo['mime_type'],
                'size_bytes' => (int) $photo['size_bytes'],
                'sha256' => $photo['sha256'] ?? null,
                'caption' => $photo['caption'] ?? null,
                'visibility' => 'inventory_managers',
                'protected' => true,
            ])
            ->values()
            ->all();
    }

    private function applyMaintenanceResourceLock(ClubInventoryMaintenanceRecord $maintenance, ClubInventoryItem $item): void
    {
        $startsAt = $maintenance->starts_at?->toIso8601String();
        $endsAt = $maintenance->ends_at?->toIso8601String();
        if (! $startsAt || ! $endsAt) {
            return;
        }

        $lock = [
            'source' => 'inventory_maintenance',
            'maintenance_id' => $maintenance->id,
            'type' => $maintenance->type,
            'title' => $maintenance->title,
            'booking_impact' => $maintenance->booking_impact,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];

        DB::transaction(function () use ($item, $maintenance, $lock): void {
            $lockedItem = ClubInventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $rules = $lockedItem->booking_rules ?: [];
            $windows = collect($rules['blackout_windows'] ?? [])
                ->reject(fn ($window) => (int) ($window['maintenance_id'] ?? 0) === (int) $maintenance->id)
                ->push($lock)
                ->values()
                ->all();

            $rules['blackout_windows'] = $windows;
            $lockedItem->forceFill(['booking_rules' => $rules])->save();
            $maintenance->forceFill(['resource_lock_snapshot' => $lock])->save();
        });
    }

    private function createNextRecurringMaintenance(ClubInventoryMaintenanceRecord $maintenance): ?ClubInventoryMaintenanceRecord
    {
        if (! $maintenance->recurrence_frequency || ! $maintenance->starts_at || ! $maintenance->ends_at) {
            return null;
        }
        if ($maintenance->children()->exists()) {
            return null;
        }

        $interval = max(1, (int) $maintenance->recurrence_interval);
        $nextStart = $this->nextRecurringAt($maintenance->starts_at, $maintenance->recurrence_frequency, $interval);
        $nextEnd = $this->nextRecurringAt($maintenance->ends_at, $maintenance->recurrence_frequency, $interval);
        if ($maintenance->recurrence_until && $nextStart->toDateString() > $maintenance->recurrence_until->toDateString()) {
            return null;
        }

        $next = $maintenance->replicate([
            'status', 'opened_at', 'completed_at', 'resolved_by', 'cost', 'resource_lock_snapshot',
            'created_at', 'updated_at',
        ]);
        $next->parent_id = $maintenance->id;
        $next->status = 'open';
        $next->opened_at = now();
        $next->starts_at = $nextStart;
        $next->ends_at = $nextEnd;
        $next->completed_at = null;
        $next->resolved_by = null;
        $next->cost = null;
        $next->resource_lock_snapshot = null;
        $next->save();

        if ($next->blocks_resource) {
            $this->applyMaintenanceResourceLock($next, $maintenance->item);
        }

        return $next;
    }

    private function nextRecurringAt(Carbon $date, string $frequency, int $interval): Carbon
    {
        return match ($frequency) {
            'daily' => $date->copy()->addDays($interval),
            'weekly' => $date->copy()->addWeeks($interval),
            'monthly' => $date->copy()->addMonthsNoOverflow($interval),
            'yearly' => $date->copy()->addYearsNoOverflow($interval),
            default => $date->copy(),
        };
    }

    private function validateItem(Request $request, Club $club, ?ClubInventoryItem $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('club_inventory_items')->where('club_id', $club->id)->ignore($item?->id)],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quantity_total' => ['required', 'integer', 'min:1', 'max:100000'],
            'minimum_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reorder_lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'condition' => ['required', Rule::in(['new', 'good', 'worn', 'damaged'])],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'requires_approval' => ['boolean'],
            'article_number' => ['nullable', 'string', 'max:120'],
            'batch_number' => ['nullable', 'string', 'max:120'],
            'purchase_price_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'deposit_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'supplier' => ['nullable', 'string', 'max:180'],
            'purchased_on' => ['nullable', 'date'],
            'club_department_id' => ['nullable', 'integer', Rule::exists('club_departments', 'id')->where('club_id', $club->id)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('club_inventory_items', 'id')->where('club_id', $club->id)],
            'resource_type' => ['nullable', Rule::in(['facility', 'hall', 'field', 'room', 'area', 'locker_room', 'equipment', 'other'])],
            'opening_hours' => ['nullable', 'array'],
            'booking_rules' => ['nullable', 'array'],
            'rental_price_rules' => ['nullable', 'array'],
        ]);

        $data['club_department_id'] = $request->exists('club_department_id')
            ? ($data['club_department_id'] ?? null)
            : $item?->club_department_id;
        $data['team_id'] = $request->exists('team_id')
            ? ($data['team_id'] ?? null)
            : $item?->team_id;
        $data['parent_id'] = $request->exists('parent_id')
            ? ($data['parent_id'] ?? null)
            : $item?->parent_id;
        $data['resource_type'] = $data['resource_type'] ?? $item?->resource_type ?? 'equipment';
        $data['opening_hours'] = $request->exists('opening_hours')
            ? ($data['opening_hours'] ?? null)
            : $item?->opening_hours;
        $data['booking_rules'] = $request->exists('booking_rules')
            ? ($data['booking_rules'] ?? null)
            : $item?->booking_rules;
        $data['rental_price_rules'] = $request->exists('rental_price_rules')
            ? ($data['rental_price_rules'] ?? null)
            : $item?->rental_price_rules;
        $data['minimum_stock'] = $data['minimum_stock'] ?? $item?->minimum_stock ?? 0;
        $data['reorder_lead_time_days'] = $data['reorder_lead_time_days'] ?? $item?->reorder_lead_time_days ?? 0;
        $this->validateOpeningHours($data['opening_hours']);
        $this->validateBookingRules($data['booking_rules']);
        $this->validateRentalPriceRules($data['rental_price_rules']);

        if ($item && $data['parent_id'] && (int) $data['parent_id'] === (int) $item->id) {
            throw ValidationException::withMessages(['parent_id' => __('validation.invalid')]);
        }
        if ($item && $data['parent_id']) {
            $cursor = ClubInventoryItem::query()->where('club_id', $club->id)->find($data['parent_id']);
            while ($cursor) {
                if ((int) $cursor->id === (int) $item->id) {
                    throw ValidationException::withMessages(['parent_id' => __('validation.invalid')]);
                }
                $cursor = $cursor->parent_id
                    ? ClubInventoryItem::query()->where('club_id', $club->id)->find($cursor->parent_id)
                    : null;
            }
        }

        return $data;
    }

    public function recordMovement(Request $request, Club $club, ClubInventoryItem $item)
    {
        $this->ensureItemClub($club, $item);
        $this->authorizeItem($request, $item, ClubPermissions::INVENTORY_EDIT);
        $data = $request->validate([
            'type' => ['required', Rule::in(['purchase', 'consumption', 'reservation', 'reservation_release', 'shrinkage', 'correction'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['required', 'string', 'max:2000'],
            'occurred_on' => ['nullable', 'date'],
            'purchase_price_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'deposit_cents' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'exception_approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'batch_number' => ['nullable', 'string', 'max:120'],
            'supplier' => ['nullable', 'string', 'max:180'],
            'correction_of_id' => ['nullable', 'integer', Rule::exists('club_inventory_movements', 'id')->where('club_id', $club->id)],
        ]);

        $movement = DB::transaction(function () use ($request, $club, $item, $data): ClubInventoryMovement {
            $lockedItem = ClubInventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $before = (int) $lockedItem->quantity_available;
            $quantity = (int) $data['quantity'];
            $delta = match ($data['type']) {
                'purchase' => $quantity,
                'consumption', 'shrinkage' => -$quantity,
                'reservation', 'reservation_release' => 0,
                default => $quantity - $before,
            };
            $after = $before + $delta;
            abort_if($after < 0, 422, __('organization.inventory.quantity_unavailable'));
            $reservedBefore = (int) ($lockedItem->reserved_quantity ?? 0);
            $reservedAfter = match ($data['type']) {
                'reservation' => $reservedBefore + $quantity,
                'reservation_release' => $reservedBefore - $quantity,
                default => $reservedBefore,
            };
            abort_if($reservedAfter < 0 || $reservedAfter > $after, 422, __('organization.inventory.quantity_unavailable'));
            $controlType = $data['type'] === 'purchase'
                ? 'purchase'
                : (in_array($data['type'], ['consumption', 'shrinkage'], true) ? 'issue' : null);
            $financialDecision = $controlType
                ? $this->authorizeFinancialControl(
                    $club,
                    $lockedItem,
                    $request->user(),
                    $controlType,
                    $this->movementDecisionAmountCents($lockedItem, $data['type'], $quantity, $data['purchase_price_cents'] ?? null),
                    $data['exception_approved_by'] ?? null,
                )
                : ['audit' => null, 'exception' => false];

            $correctionOf = null;
            if (! empty($data['correction_of_id'])) {
                $correctionOf = ClubInventoryMovement::query()
                    ->where('club_id', $club->id)
                    ->where('club_inventory_item_id', $lockedItem->id)
                    ->findOrFail($data['correction_of_id']);
            }

            $lockedItem->forceFill([
                'quantity_available' => $after,
                'reserved_quantity' => $reservedAfter,
                'quantity_total' => max((int) $lockedItem->quantity_total + $delta, $after),
                'purchase_price_cents' => $data['purchase_price_cents'] ?? $lockedItem->purchase_price_cents,
                'deposit_cents' => $data['deposit_cents'] ?? $lockedItem->deposit_cents ?? 0,
                'batch_number' => $data['batch_number'] ?? $lockedItem->batch_number,
                'supplier' => $data['supplier'] ?? $lockedItem->supplier,
                'purchased_on' => $data['type'] === 'purchase' ? ($data['occurred_on'] ?? now()->toDateString()) : $lockedItem->purchased_on,
            ])->save();

            $movement = ClubInventoryMovement::query()->create([
                'club_id' => $club->id,
                'club_inventory_item_id' => $lockedItem->id,
                'recorded_by' => $request->user()->id,
                'type' => $data['type'],
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'purchase_price_cents' => $data['purchase_price_cents'] ?? null,
                'deposit_cents' => $data['deposit_cents'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
                'reason' => $data['reason'],
                'correction_of_id' => $correctionOf?->id,
                'correction_snapshot' => $correctionOf?->only([
                    'id', 'type', 'quantity_delta', 'quantity_before', 'quantity_after', 'reason',
                ]) ?? ($data['type'] === 'reservation' || $data['type'] === 'reservation_release' ? [
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'reservable_before' => max(0, $before - $reservedBefore),
                    'reservable_after' => max(0, $after - $reservedAfter),
                ] : null),
            ]);
            ClubAuditLog::record($club, $request->user(), 'club.inventory.movement_recorded', $movement, [
                'item_id' => $lockedItem->id,
                'type' => $movement->type,
                'quantity_delta' => $movement->quantity_delta,
                'quantity_before' => $movement->quantity_before,
                'quantity_after' => $movement->quantity_after,
                'correction_of_id' => $movement->correction_of_id,
                'financial_control' => $financialDecision['audit'],
            ]);
            if ($financialDecision['exception']) {
                $this->auditFinancialException($club, $request->user(), $movement, $financialDecision);
            }

            return $movement;
        });

        return response()->json([
            'message' => __('organization.inventory.item_updated'),
            'data' => [
                'movement' => $movement,
                'item' => $this->itemPayload($item->refresh()->load(['movements' => fn ($query) => $query->latest('id')->limit(20)]), $request->user()),
            ],
        ], 201);
    }

    private function authorizeAccess(Request $request, Club $club): void
    {
        abort_unless(collect([
            ClubPermissions::INVENTORY_VIEW,
            ClubPermissions::INVENTORY_EDIT,
            ClubPermissions::INVENTORY_APPROVE,
            ClubPermissions::INVENTORY_DELETE,
        ])->contains(fn (string $permission) => ClubPermissions::allowsAnyInventoryScope($club, $request->user(), $permission)), 403);
    }

    private function authorizeItem(Request $request, ClubInventoryItem $item, string $permission): void
    {
        abort_unless(ClubPermissions::allowsForInventoryItem($item, $request->user(), $permission), 403);
    }

    private function authorizeItemScope(Request $request, Club $club, array &$data, string $permission): void
    {
        $departmentId = ! empty($data['club_department_id']) ? (int) $data['club_department_id'] : null;
        $teamId = ! empty($data['team_id']) ? (int) $data['team_id'] : null;

        if ($teamId) {
            $team = $club->teams()->findOrFail($teamId);
            $teamDepartmentId = $team->club_department_id ? (int) $team->club_department_id : null;
            if ($departmentId && $teamDepartmentId !== $departmentId) {
                throw ValidationException::withMessages(['team_id' => __('organization.inventory.team_department_mismatch')]);
            }
            $departmentId ??= $teamDepartmentId;
            $data['club_department_id'] = $departmentId;
        }

        abort_unless(ClubPermissions::allowsForInventoryScope(
            $club,
            $request->user(),
            $permission,
            $departmentId,
            $teamId,
        ), 403);
    }

    private function canAccessItem(ClubInventoryItem $item, User $user): bool
    {
        return collect([
            ClubPermissions::INVENTORY_VIEW,
            ClubPermissions::INVENTORY_EDIT,
            ClubPermissions::INVENTORY_APPROVE,
            ClubPermissions::INVENTORY_DELETE,
        ])->contains(fn (string $permission) => ClubPermissions::allowsForInventoryItem($item, $user, $permission));
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
        abort_if($quantity > $this->reservableQuantity($item), 422, __('organization.inventory.quantity_unavailable'));
        $item->decrement('quantity_available', $quantity);
    }

    private function reservableQuantity(ClubInventoryItem $item): int
    {
        return max(0, (int) $item->quantity_available - (int) ($item->reserved_quantity ?? 0));
    }

    private function reserveWindowCapacity(ClubInventoryItem $item, int $quantity, string|\DateTimeInterface $startsAt, string|\DateTimeInterface|null $endsAt, ?int $ignoreLoanId = null, int $bookingPriority = 100): void
    {
        $start = Carbon::parse($startsAt);
        $end = Carbon::parse($endsAt);
        $this->assertBookableWindow($item, $start, $end);
        $reserved = (int) ClubInventoryLoan::query()
            ->where('club_inventory_item_id', $item->id)
            ->whereIn('status', ['pending', 'active'])
            ->where('booking_priority', '<=', $bookingPriority)
            ->when($ignoreLoanId, fn ($query) => $query->whereKeyNot($ignoreLoanId))
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $end)
            ->where('due_at', '>', $start)
            ->sum('quantity');

        abort_if($reserved + $quantity > $item->quantity_total, 422, __('organization.inventory.quantity_unavailable'));
    }

    private function assertBookableWindow(ClubInventoryItem $item, Carbon $start, Carbon $end): void
    {
        foreach ($this->resourceLineage($item) as $resource) {
            foreach (($resource->booking_rules['blackout_windows'] ?? []) as $blackout) {
                $blackoutStart = Carbon::parse($blackout['starts_at'] ?? null);
                $blackoutEnd = Carbon::parse($blackout['ends_at'] ?? null);
                abort_if($start->lt($blackoutEnd) && $end->gt($blackoutStart), 422, __('organization.inventory.booking_window_blocked'));
            }

            if (! $resource->opening_hours) {
                continue;
            }

            abort_unless($this->windowFitsOpeningHours($resource->opening_hours, $start, $end), 422, __('organization.inventory.booking_outside_opening_hours'));
        }
    }

    private function resourceLineage(ClubInventoryItem $item): array
    {
        $lineage = [$item];
        $cursor = $item;

        while ($cursor->parent_id) {
            $cursor = ClubInventoryItem::query()
                ->where('club_id', $item->club_id)
                ->findOrFail($cursor->parent_id);
            $lineage[] = $cursor;
        }

        return $lineage;
    }

    private function windowFitsOpeningHours(array $openingHours, Carbon $start, Carbon $end): bool
    {
        $start = $start->copy()->setTimezone(config('app.timezone'));
        $end = $end->copy()->setTimezone(config('app.timezone'));

        if (! $start->isSameDay($end)) {
            return false;
        }

        $dayKeys = [
            strtolower($start->englishDayOfWeek),
            strtolower($start->shortEnglishDayOfWeek),
            (string) $start->dayOfWeekIso,
        ];

        $windows = collect($dayKeys)
            ->map(fn (string $key) => $openingHours[$key] ?? null)
            ->first(fn ($windows) => is_array($windows));

        if (! is_array($windows)) {
            return false;
        }

        $startMinutes = ((int) $start->format('H')) * 60 + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H')) * 60 + (int) $end->format('i');

        foreach ($windows as $window) {
            if (! is_array($window)) {
                continue;
            }

            $openMinutes = $this->minutesFromTime($window['start'] ?? null);
            $closeMinutes = $this->minutesFromTime($window['end'] ?? null);

            if ($openMinutes !== null && $closeMinutes !== null && $startMinutes >= $openMinutes && $endMinutes <= $closeMinutes) {
                return true;
            }
        }

        return false;
    }

    private function minutesFromTime(?string $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    private function validateOpeningHours(?array $openingHours): void
    {
        if ($openingHours === null) {
            return;
        }

        foreach ($openingHours as $day => $windows) {
            if (! is_array($windows) || ! in_array((string) $day, [
                '1', '2', '3', '4', '5', '6', '7',
                'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun',
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            ], true)) {
                throw ValidationException::withMessages(['opening_hours' => __('validation.invalid')]);
            }

            foreach ($windows as $window) {
                $start = $this->minutesFromTime($window['start'] ?? null);
                $end = $this->minutesFromTime($window['end'] ?? null);
                if ($start === null || $end === null || $end <= $start) {
                    throw ValidationException::withMessages(['opening_hours' => __('validation.invalid')]);
                }
            }
        }
    }

    private function validateBookingRules(?array $bookingRules): void
    {
        if ($bookingRules === null) {
            return;
        }

        foreach (($bookingRules['blackout_windows'] ?? []) as $blackout) {
            $start = Carbon::parse($blackout['starts_at'] ?? null);
            $end = Carbon::parse($blackout['ends_at'] ?? null);
            if ($end->lte($start)) {
                throw ValidationException::withMessages(['booking_rules' => __('validation.invalid')]);
            }
        }

        $financialControls = $bookingRules['financial_controls'] ?? [];
        if ($financialControls !== [] && ! is_array($financialControls)) {
            throw ValidationException::withMessages(['booking_rules' => __('validation.invalid')]);
        }
        foreach (['purchase_warning_cents', 'purchase_approval_cents', 'issue_warning_cents', 'issue_approval_cents'] as $field) {
            if (array_key_exists($field, $financialControls) && (! is_int($financialControls[$field]) || $financialControls[$field] < 0)) {
                throw ValidationException::withMessages(['booking_rules' => __('validation.invalid')]);
            }
        }
    }

    private function authorizeFinancialControl(
        Club $club,
        ClubInventoryItem $item,
        User $actor,
        string $type,
        int $amountCents,
        ?int $exceptionApprovedBy,
        bool $allowPendingApproval = true,
        bool $enforceSeparateApprover = true,
    ): array {
        $controls = $item->booking_rules['financial_controls'] ?? [];
        $warning = (int) ($controls[$type.'_warning_cents'] ?? 0);
        $approval = (int) ($controls[$type.'_approval_cents'] ?? 0);
        $requiresApproval = $approval > 0 && $amountCents > $approval;
        $warningExceeded = $warning > 0 && $amountCents > $warning;

        if (! $requiresApproval) {
            return [
                'requires_approval' => false,
                'exception' => false,
                'audit' => $warningExceeded ? [
                    'type' => $type,
                    'decision' => 'warning_exceeded',
                    'amount_cents' => $amountCents,
                    'warning_cents' => $warning,
                    'approval_cents' => $approval,
                ] : null,
            ];
        }

        if ($allowPendingApproval && $type === 'issue' && ! $exceptionApprovedBy) {
            return [
                'requires_approval' => true,
                'exception' => false,
                'audit' => [
                    'type' => $type,
                    'decision' => 'approval_required',
                    'amount_cents' => $amountCents,
                    'warning_cents' => $warning,
                    'approval_cents' => $approval,
                ],
            ];
        }

        if (! $exceptionApprovedBy) {
            throw ValidationException::withMessages(['exception_approved_by' => __('validation.required')]);
        }

        abort_if($enforceSeparateApprover && (int) $exceptionApprovedBy === (int) $actor->id, 422, __('organization.inventory.second_person'));
        $approver = User::query()->findOrFail($exceptionApprovedBy);
        abort_unless(
            ClubPermissions::allowsForInventoryItem($item, $approver, ClubPermissions::INVENTORY_APPROVE)
                || ClubPermissions::allows($club, $approver, ClubPermissions::FINANCE_APPROVE),
            403
        );

        return [
            'requires_approval' => false,
            'exception' => true,
            'audit' => [
                'type' => $type,
                'decision' => 'exception_approved',
                'amount_cents' => $amountCents,
                'warning_cents' => $warning,
                'approval_cents' => $approval,
                'approved_by' => (int) $exceptionApprovedBy,
            ],
        ];
    }

    private function movementDecisionAmountCents(ClubInventoryItem $item, string $type, int $quantity, ?int $purchasePriceCents): int
    {
        $unitCents = $type === 'purchase'
            ? (int) ($purchasePriceCents ?? $item->purchase_price_cents ?? 0)
            : (int) ($item->purchase_price_cents ?? 0);

        return max(0, $unitCents * max(1, $quantity));
    }

    private function issueDecisionAmountCents(ClubInventoryItem $item, int $quantity, array $pricing): int
    {
        $rentalExposure = (int) ($pricing['price_cents'] ?? 0) + (int) ($pricing['deposit_cents'] ?? 0);
        $replacementValue = (int) ($item->purchase_price_cents ?? 0) * max(1, $quantity);

        return max($rentalExposure, $replacementValue);
    }

    private function auditFinancialException(Club $club, User $actor, ClubInventoryLoan|ClubInventoryMovement $subject, array $decision): void
    {
        ClubAuditLog::record($club, $actor, 'club.inventory.financial_exception_approved', $subject, [
            'item_id' => $subject->club_inventory_item_id,
            'decision_type' => $decision['audit']['type'],
            'amount_cents' => $decision['audit']['amount_cents'],
            'approval_cents' => $decision['audit']['approval_cents'],
            'approved_by' => $decision['audit']['approved_by'],
        ]);
    }

    private function validateRentalPriceRules(?array $rules): void
    {
        if ($rules === null) {
            return;
        }

        foreach (['internal', 'external'] as $type) {
            $rule = $rules[$type] ?? [];
            if (! is_array($rule)) {
                throw ValidationException::withMessages(['rental_price_rules' => __('validation.invalid')]);
            }
            foreach (['hour_cents', 'day_cents', 'flat_cents', 'deposit_cents'] as $field) {
                if (array_key_exists($field, $rule) && (! is_int($rule[$field]) || $rule[$field] < 0)) {
                    throw ValidationException::withMessages(['rental_price_rules' => __('validation.invalid')]);
                }
            }
        }
    }

    private function rentalPricing(ClubInventoryItem $item, string $type, int $quantity, ?string $startsAt, ?string $endsAt): array
    {
        $rules = $item->rental_price_rules[$type] ?? [];
        $unitCents = (int) ($rules['flat_cents'] ?? $rules['day_cents'] ?? $rules['hour_cents'] ?? 0);
        $units = 1;
        $unit = array_key_exists('flat_cents', $rules) ? 'flat' : (array_key_exists('day_cents', $rules) ? 'day' : 'hour');

        if ($startsAt && $endsAt) {
            $minutes = max(1, Carbon::parse($startsAt)->diffInMinutes(Carbon::parse($endsAt)));
            if (array_key_exists('hour_cents', $rules)) {
                $unit = 'hour';
                $unitCents = (int) $rules['hour_cents'];
                $units = (int) ceil($minutes / 60);
            } elseif (array_key_exists('day_cents', $rules)) {
                $unit = 'day';
                $unitCents = (int) $rules['day_cents'];
                $units = (int) ceil($minutes / 1440);
            }
        }

        $quantity = max(1, $quantity);
        $priceCents = $unitCents * $units * $quantity;
        $depositCents = (int) ($rules['deposit_cents'] ?? $item->deposit_cents ?? 0) * $quantity;

        return [
            'rental_type' => $type,
            'unit' => $unit,
            'unit_cents' => $unitCents,
            'units' => $units,
            'quantity' => $quantity,
            'price_cents' => $priceCents,
            'deposit_cents' => $depositCents,
            'rules' => $rules,
        ];
    }

    private function nextRentalContractNumber(Club $club): string
    {
        return 'RENT-'.$club->id.'-'.now()->format('Ymd').'-'.str_pad((string) (ClubInventoryLoan::query()->where('club_id', $club->id)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function createRentalInvoice(Club $club, ClubInventoryLoan $loan, ?int $amountCents = null, string $kind = 'rental'): Invoice
    {
        $amountCents ??= (int) $loan->rental_price_cents + (int) $loan->rental_deposit_cents;

        return Invoice::query()->create([
            'club_id' => $club->id,
            'user_id' => $loan->borrower_id,
            'number' => strtoupper($kind).'-'.$loan->rental_contract_number,
            'title' => $kind === 'damage' ? 'Schadensforderung Vermietung' : 'Vermietung '.$loan->item?->name,
            'description' => $loan->rental_type === 'external'
                ? trim('Externe Vermietung an '.($loan->external_renter_name ?? 'Gast'))
                : 'Interne Vermietung',
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'status' => 'open',
            'claim_status' => 'open',
            'source' => $kind === 'damage' ? 'inventory_rental_damage' : 'inventory_rental',
            'due_date' => now()->addDays(14),
            'issued_at' => now(),
        ]);
    }

    private function ensureItemClub(Club $club, ClubInventoryItem $item): void
    {
        abort_unless((int) $item->club_id === (int) $club->id, 404);
    }

    private function ensureLoanClub(Club $club, ClubInventoryLoan $loan): void
    {
        abort_unless((int) $loan->club_id === (int) $club->id, 404);
    }

    private function itemPayload(ClubInventoryItem $item, User $user): array
    {
        $payload = $item->only([
            'id', 'club_id', 'club_department_id', 'team_id', 'parent_id', 'resource_type', 'opening_hours', 'booking_rules', 'rental_price_rules', 'name', 'sku', 'category', 'location', 'description',
            'article_number', 'batch_number', 'purchase_price_cents', 'deposit_cents', 'supplier', 'purchased_on',
            'quantity_total', 'quantity_available', 'minimum_stock', 'reserved_quantity', 'reorder_lead_time_days', 'condition', 'status', 'requires_approval',
        ]);
        $payload['reservable_quantity'] = $this->reservableQuantity($item);
        $payload['reorder_proposal'] = $this->reorderProposal($item);
        $payload['movements'] = $item->relationLoaded('movements')
            ? $item->movements->map(fn (ClubInventoryMovement $movement) => $movement->only([
                'id', 'type', 'quantity_delta', 'quantity_before', 'quantity_after', 'purchase_price_cents',
                'deposit_cents', 'batch_number', 'supplier', 'occurred_on', 'reason', 'correction_of_id',
                'correction_snapshot',
            ]))->values()->all()
            : [];
        $payload['parent'] = $item->relationLoaded('parent') && $item->parent
            ? $item->parent->only(['id', 'name', 'resource_type'])
            : null;
        $payload['children'] = $item->relationLoaded('children')
            ? $item->children->map(fn (ClubInventoryItem $child) => $child->only(['id', 'name', 'resource_type', 'status']))->values()->all()
            : [];
        $payload['active_loans_count'] = (int) ($item->active_loans_count ?? 0);
        $payload['open_maintenance_count'] = (int) ($item->open_maintenance_count ?? 0);
        $payload['can_checkout'] = ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_VIEW);
        $payload['can_edit'] = ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_EDIT);
        $payload['can_approve'] = ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_APPROVE);
        $payload['can_delete'] = ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_DELETE);

        if ($payload['can_edit']) {
            $signedPayload = $this->signedQrPayload($item);
            $svg = (new Writer(new ImageRenderer(new RendererStyle(256, 4), new SvgImageBackEnd)))->writeString($signedPayload);
            $payload['qr_token'] = $item->qr_token;
            $payload['qr_payload'] = $signedPayload;
            $payload['qr_issued_at'] = $item->qr_issued_at?->toIso8601String();
            $payload['qr_revoked_at'] = $item->qr_revoked_at?->toIso8601String();
            $payload['qr_svg_data_uri'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return $payload;
    }

    private function minimalQrPayload(ClubInventoryItem $item, User $user): array
    {
        return [
            'id' => $item->id,
            'club_id' => $item->club_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'category' => $item->category,
            'location' => $item->location,
            'condition' => $item->condition,
            'status' => $item->status,
            'quantity_available' => (int) $item->quantity_available,
            'quantity_total' => (int) $item->quantity_total,
            'minimum_stock' => (int) ($item->minimum_stock ?? 0),
            'reserved_quantity' => (int) ($item->reserved_quantity ?? 0),
            'reservable_quantity' => $this->reservableQuantity($item),
            'reorder_proposal' => $this->reorderProposal($item),
            'can_checkout' => ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_VIEW),
            'can_edit' => ClubPermissions::allowsForInventoryItem($item, $user, ClubPermissions::INVENTORY_EDIT),
        ];
    }

    private function reorderProposal(ClubInventoryItem $item): array
    {
        $available = (int) $item->quantity_available;
        $reserved = (int) ($item->reserved_quantity ?? 0);
        $minimum = (int) ($item->minimum_stock ?? 0);
        $reservable = max(0, $available - $reserved);
        $recommended = max(0, $minimum - $reservable);

        return [
            'recommended_quantity' => $recommended,
            'is_reorder_needed' => $recommended > 0,
            'reason' => $recommended > 0 ? 'below_minimum_after_reservations' : 'minimum_covered',
            'calculation' => [
                'minimum_stock' => $minimum,
                'quantity_available' => $available,
                'reserved_quantity' => $reserved,
                'reservable_quantity' => $reservable,
                'reorder_lead_time_days' => (int) ($item->reorder_lead_time_days ?? 0),
            ],
        ];
    }

    private function signedQrPayload(ClubInventoryItem $item): string
    {
        $payload = [
            'v' => 1,
            'c' => (int) $item->club_id,
            'i' => (int) $item->id,
            'n' => (string) $item->qr_token,
        ];
        $payload['s'] = $this->qrSignature($payload);

        return 'airmius-inventory:'.rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function verifyQrPayload(string $token): ?array
    {
        if (! str_starts_with($token, 'airmius-inventory:')) {
            return null;
        }

        $encoded = substr($token, strlen('airmius-inventory:'));
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($json)) {
            return null;
        }

        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (($payload['v'] ?? null) !== 1
            || ! is_int($payload['c'] ?? null)
            || ! is_int($payload['i'] ?? null)
            || ! is_string($payload['n'] ?? null)
            || ! is_string($payload['s'] ?? null)
        ) {
            return null;
        }

        $signaturePayload = ['v' => 1, 'c' => $payload['c'], 'i' => $payload['i'], 'n' => $payload['n']];
        if (! hash_equals($this->qrSignature($signaturePayload), $payload['s'])) {
            return null;
        }

        return ['club_id' => $payload['c'], 'item_id' => $payload['i'], 'nonce' => $payload['n']];
    }

    private function qrSignature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
