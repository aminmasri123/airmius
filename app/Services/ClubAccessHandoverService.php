<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubAccessHandoverReview;
use App\Models\ClubInventoryLoan;
use App\Models\ClubPermissionDelegation;
use App\Models\ClubRoleAssignment;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClubAccessHandoverService
{
    public function ensure(Club $club, User $departing, CarbonInterface|string $dueOn): ?ClubAccessHandoverReview
    {
        $snapshot = $this->assignmentSnapshot($club, $departing);
        $delegationCount = $this->delegationCount($club, $departing);
        $inventoryLoanSnapshot = $this->inventoryLoanSnapshot($club, $departing);
        if ($snapshot === [] && $delegationCount === 0 && $inventoryLoanSnapshot === []) {
            return null;
        }

        $dueDate = Carbon::parse($dueOn)->startOfDay();
        $review = ClubAccessHandoverReview::query()->firstOrCreate([
            'club_id' => $club->id,
            'departing_user_id' => $departing->id,
            'due_on' => $dueDate,
        ], [
            'status' => 'pending',
            'assignment_snapshot' => $snapshot,
            'delegation_count' => $delegationCount,
            'inventory_loan_snapshot' => $inventoryLoanSnapshot,
        ]);

        if ($review->status === 'pending'
            && ($review->assignment_snapshot !== $snapshot
                || $review->delegation_count !== $delegationCount
                || ($review->inventory_loan_snapshot ?? []) !== $inventoryLoanSnapshot)) {
            $review->update([
                'assignment_snapshot' => $snapshot,
                'delegation_count' => $delegationCount,
                'inventory_loan_snapshot' => $inventoryLoanSnapshot,
            ]);
        }

        return $review->refresh();
    }

    public function propose(
        ClubAccessHandoverReview $review,
        User $actor,
        string $decision,
        ?User $successor,
        ?string $note,
    ): ClubAccessHandoverReview {
        return DB::transaction(function () use ($review, $actor, $decision, $successor, $note): ClubAccessHandoverReview {
            $review = ClubAccessHandoverReview::query()->lockForUpdate()->findOrFail($review->id);
            abort_unless(in_array($review->status, ['pending', 'proposed', 'stale'], true), 422);
            $club = Club::query()->findOrFail($review->club_id);
            $departing = User::query()->findOrFail($review->departing_user_id);
            $snapshot = $this->assignmentSnapshot($club, $departing);
            $inventoryLoanSnapshot = $this->inventoryLoanSnapshot($club, $departing);

            if ($decision === 'assign_successor') {
                $this->assertSuccessor($club, $departing, $successor);
                $this->assertCanTransfer($club, $actor, $snapshot);
            } else {
                $successor = null;
            }

            $review->update([
                'status' => 'proposed',
                'decision' => $decision,
                'successor_user_id' => $successor?->id,
                'assignment_snapshot' => $snapshot,
                'delegation_count' => $this->delegationCount($club, $departing),
                'inventory_loan_snapshot' => $inventoryLoanSnapshot,
                'proposed_by' => $actor->id,
                'proposed_at' => now(),
                'proposal_note' => filled($note) ? trim((string) $note) : null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            ClubAuditLog::record($club, $actor, 'club.role_access_handover.proposed', $review, [
                'departing_user_id' => $departing->id,
                'decision' => $decision,
                'successor_user_id' => $successor?->id,
                'assignment_count' => count($snapshot),
                'inventory_loan_count' => count($inventoryLoanSnapshot),
            ]);

            return $review->refresh();
        });
    }

    public function approve(ClubAccessHandoverReview $review, User $actor): ClubAccessHandoverReview
    {
        return DB::transaction(function () use ($review, $actor): ClubAccessHandoverReview {
            $review = ClubAccessHandoverReview::query()->lockForUpdate()->findOrFail($review->id);
            abort_unless($review->status === 'proposed' && $review->proposed_by, 422);
            abort_if((int) $review->proposed_by === (int) $actor->id, 422, __('validation.access_handover_second_person'));
            $club = Club::query()->findOrFail($review->club_id);
            $departing = User::query()->findOrFail($review->departing_user_id);
            $snapshot = $this->assignmentSnapshot($club, $departing);
            $inventoryLoanSnapshot = $this->inventoryLoanSnapshot($club, $departing);
            $this->assertSnapshotCurrent($review, $snapshot);
            $this->assertInventoryLoansCurrent($review, $inventoryLoanSnapshot);

            if ($review->decision === 'assign_successor') {
                $successor = User::query()->find($review->successor_user_id);
                $proposer = User::query()->find($review->proposed_by);
                $this->assertSuccessor($club, $departing, $successor);
                abort_unless($proposer, 422);
                $this->assertCanTransfer($club, $proposer, $snapshot);
                $this->assertCanTransfer($club, $actor, $snapshot);
            }

            $review->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            ClubAuditLog::record($club, $actor, 'club.role_access_handover.approved', $review, [
                'departing_user_id' => $departing->id,
                'decision' => $review->decision,
                'successor_user_id' => $review->successor_user_id,
                'assignment_count' => count($snapshot),
                'inventory_loan_count' => count($inventoryLoanSnapshot),
            ]);

            return $review->refresh();
        });
    }

    /** @return array{transferred: int, status: string} */
    public function applyForMembershipEnd(Club $club, User $departing, CarbonInterface|string $dueOn): array
    {
        return DB::transaction(function () use ($club, $departing, $dueOn): array {
            $review = ClubAccessHandoverReview::query()
                ->where('club_id', $club->id)
                ->where('departing_user_id', $departing->id)
                ->whereDate('due_on', Carbon::parse($dueOn)->toDateString())
                ->lockForUpdate()
                ->first();
            if (! $review || $review->status !== 'approved') {
                return ['transferred' => 0, 'status' => $review?->status ?? 'missing'];
            }

            $snapshot = $this->assignmentSnapshot($club, $departing);
            $inventoryLoanSnapshot = $this->inventoryLoanSnapshot($club, $departing);
            if ($review->assignment_snapshot !== $snapshot || ($review->inventory_loan_snapshot ?? []) !== $inventoryLoanSnapshot) {
                $review->update(['status' => 'stale']);

                return ['transferred' => 0, 'status' => 'stale'];
            }

            if ($inventoryLoanSnapshot !== []) {
                $review->update(['status' => 'stale']);

                return ['transferred' => 0, 'status' => 'stale'];
            }

            $transferred = 0;
            if ($review->decision === 'assign_successor') {
                $successor = User::query()->find($review->successor_user_id);
                $proposer = User::query()->find($review->proposed_by);
                $approver = User::query()->find($review->approved_by);
                if (! $proposer || ! $approver || ! $this->isActiveMember($club, $successor)
                    || ! $this->canTransfer($club, $proposer, $snapshot)
                    || ! $this->canTransfer($club, $approver, $snapshot)) {
                    $review->update(['status' => 'stale']);

                    return ['transferred' => 0, 'status' => 'stale'];
                }
                foreach ($snapshot as $assignment) {
                    $created = ClubRoleAssignment::query()->firstOrCreate([
                        'club_id' => $club->id,
                        'club_role_definition_id' => $assignment['role_definition_id'],
                        'user_id' => $successor->id,
                        'scope_key' => $assignment['scope_key'],
                    ], [
                        'scope_type' => $assignment['scope_type'],
                        'scope_id' => $assignment['scope_id'],
                        'assigned_by' => $review->approved_by,
                    ]);
                    $transferred += $created->wasRecentlyCreated ? 1 : 0;
                }
            }

            $review->update(['status' => 'applied', 'applied_at' => now()]);
            ClubAuditLog::record($club, User::query()->find($review->approved_by), 'club.role_access_handover.applied', $review, [
                'departing_user_id' => $departing->id,
                'decision' => $review->decision,
                'successor_user_id' => $review->successor_user_id,
                'transferred_assignments' => $transferred,
            ]);

            return ['transferred' => $transferred, 'status' => 'applied'];
        });
    }

    /** @return list<array{role_definition_id: int, role_definition_updated_at: ?string, permissions: list<string>, scope_type: string, scope_id: ?int, scope_key: string}> */
    private function assignmentSnapshot(Club $club, User $user): array
    {
        return ClubRoleAssignment::query()
            ->where('club_role_assignments.club_id', $club->id)
            ->where('club_role_assignments.user_id', $user->id)
            ->whereHas('roleDefinition', fn ($query) => $query->where('is_active', true))
            ->with('roleDefinition:id,permissions,updated_at')
            ->orderBy('club_role_definition_id')
            ->orderBy('scope_key')
            ->get()
            ->map(fn (ClubRoleAssignment $assignment) => [
                'role_definition_id' => (int) $assignment->club_role_definition_id,
                'role_definition_updated_at' => $assignment->roleDefinition?->updated_at?->toJSON(),
                'permissions' => collect($assignment->roleDefinition?->permissions ?? [])->sort()->values()->all(),
                'scope_type' => $assignment->scope_type,
                'scope_id' => $assignment->scope_id ? (int) $assignment->scope_id : null,
                'scope_key' => $assignment->scope_key,
            ])->values()->all();
    }

    private function delegationCount(Club $club, User $user): int
    {
        return ClubPermissionDelegation::query()
            ->where('club_id', $club->id)
            ->whereNull('revoked_at')
            ->where('ends_at', '>=', now())
            ->where(fn ($query) => $query->where('grantor_user_id', $user->id)->orWhere('grantee_user_id', $user->id))
            ->count();
    }

    /** @return list<array{id: int, item_id: int, item_name: ?string, quantity: int, status: string, issued_at: ?string, due_at: ?string}> */
    private function inventoryLoanSnapshot(Club $club, User $user): array
    {
        return ClubInventoryLoan::query()
            ->where('club_id', $club->id)
            ->where('borrower_id', $user->id)
            ->where('status', 'active')
            ->with('item:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ClubInventoryLoan $loan) => [
                'id' => (int) $loan->id,
                'item_id' => (int) $loan->club_inventory_item_id,
                'item_name' => $loan->item?->name,
                'quantity' => (int) $loan->quantity,
                'status' => $loan->status,
                'issued_at' => $loan->issued_at?->toJSON(),
                'due_at' => $loan->due_at?->toJSON(),
            ])->values()->all();
    }

    private function assertSnapshotCurrent(ClubAccessHandoverReview $review, array $snapshot): void
    {
        if ($review->assignment_snapshot !== $snapshot) {
            throw ValidationException::withMessages(['review' => __('validation.access_handover_changed')]);
        }
    }

    private function assertInventoryLoansCurrent(ClubAccessHandoverReview $review, array $snapshot): void
    {
        if (($review->inventory_loan_snapshot ?? []) !== $snapshot) {
            throw ValidationException::withMessages(['review' => __('validation.access_handover_changed')]);
        }
    }

    private function assertSuccessor(Club $club, User $departing, ?User $successor): void
    {
        if (! $successor || $successor->is($departing) || ! $this->isActiveMember($club, $successor)) {
            throw ValidationException::withMessages(['successor_user_id' => __('validation.access_handover_successor')]);
        }
    }

    private function isActiveMember(Club $club, ?User $user): bool
    {
        return $user && $club->users()->where('users.id', $user->id)
            ->where(fn ($query) => $query->whereNull('club_user.membership_status')->orWhere('club_user.membership_status', 'active'))
            ->exists();
    }

    private function assertCanTransfer(Club $club, User $actor, array $snapshot): void
    {
        abort_unless($this->canTransfer($club, $actor, $snapshot), 403);
    }

    private function canTransfer(Club $club, User $actor, array $snapshot): bool
    {
        $roles = $club->roleDefinitions()->whereIn('id', collect($snapshot)->pluck('role_definition_id'))->get()->keyBy('id');

        return collect($snapshot)->every(function (array $assignment) use ($club, $actor, $roles): bool {
            $role = $roles->get($assignment['role_definition_id']);

            return $role && collect($role->permissions)->every(fn (string $permission) => $assignment['scope_type'] === 'club'
                ? ClubPermissions::allows($club, $actor, $permission)
                : ClubPermissions::allowsInScope($club, $actor, $permission, $assignment['scope_type'], (int) $assignment['scope_id']));
        });
    }
}
