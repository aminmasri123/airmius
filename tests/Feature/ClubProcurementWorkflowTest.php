<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubBudget;
use App\Models\ClubFinanceEntry;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryMovement;
use App\Models\ClubProcurementRequest;
use App\Models\ClubRoleAssignment;
use App\Models\ClubRoleDefinition;
use App\Models\ClubYearPeriod;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_runs_from_request_to_approval_order_partial_receipt_inventory_and_accounting(): void
    {
        $requester = User::factory()->create(['name' => 'Request Lead']);
        $approver = User::factory()->create(['name' => 'Finance Approver']);
        $club = Club::factory()->create(['owner_id' => $requester->id]);
        $club->users()->attach($approver->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $this->assignRole($club, $approver, [ClubPermissions::FINANCE_VIEW, ClubPermissions::FINANCE_APPROVE], $requester);
        $period = $this->businessPeriod($club);
        $budget = ClubBudget::query()->create([
            'club_id' => $club->id,
            'club_year_period_id' => $period->id,
            'scope_type' => 'club',
            'name' => 'Material',
            'version' => 1,
            'approval_status' => 'approved',
            'planned_income_cents' => 0,
            'planned_expense_cents' => 100000,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($requester);
        $procurementId = $this->postJson("/api/v1/clubs/{$club->id}/procurements", [
            'club_budget_id' => $budget->id,
            'title' => 'Training balls and cones',
            'supplier' => 'Sport Supplier',
            'status' => 'submitted',
            'finance_account' => '4000',
            'reference' => 'PO-2026-1',
            'description' => 'Internal free text must not leak into audit.',
            'items' => [
                ['name' => 'Ball', 'sku' => 'BALL', 'quantity_requested' => 10, 'unit_price_cents' => 1200],
                ['name' => 'Cone', 'sku' => 'CONE', 'quantity_requested' => 5, 'unit_price_cents' => 300],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.estimated_total_cents', 13500)
            ->json('data.id');

        $this->putJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/approval", ['status' => 'approved'])
            ->assertUnprocessable();

        Sanctum::actingAs($approver);
        $this->putJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/approval", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', 'Finance Approver');

        Sanctum::actingAs($requester);
        $payload = $this->postJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/order")
            ->assertOk()
            ->assertJsonPath('data.status', 'ordered')
            ->assertJsonPath('data.ordered_total_cents', 13500)
            ->json('data');

        $ballLine = collect($payload['items'])->firstWhere('sku', 'BALL');
        $coneLine = collect($payload['items'])->firstWhere('sku', 'CONE');

        $this->postJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/receipts", [
            'received_on' => '2026-09-26',
            'reference' => 'DEL-1',
            'note' => 'First carton arrived.',
            'items' => [
                ['id' => $ballLine['id'], 'quantity_received' => 4],
                ['id' => $coneLine['id'], 'quantity_received' => 5],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'partially_received')
            ->assertJsonPath('data.received_total_cents', 6300);

        $this->postJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/receipts", [
            'received_on' => '2026-09-28',
            'reference' => 'DEL-2',
            'items' => [
                ['id' => $ballLine['id'], 'quantity_received' => 6],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.received_total_cents', 13500);

        $this->assertDatabaseHas('club_inventory_items', ['club_id' => $club->id, 'sku' => 'BALL', 'quantity_available' => 10]);
        $this->assertDatabaseHas('club_inventory_items', ['club_id' => $club->id, 'sku' => 'CONE', 'quantity_available' => 5]);
        $this->assertSame(3, ClubInventoryMovement::query()->where('club_id', $club->id)->where('type', 'purchase')->count());
        $this->assertSame(0, ClubFinanceEntry::where('club_id', $club->id)->count());
        $this->getJson("/api/v1/clubs/{$club->id}/budgets")->assertOk()
            ->assertJsonPath('data.budgets.0.financial_report.reserved_expense_cents', 13500)
            ->assertJsonPath('data.budgets.0.financial_report.actual_expense_cents', 0);
        foreach (ClubProcurementRequest::findOrFail($procurementId)->receipts as $receipt) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $this->postJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/receipts/{$receipt->id}/payment", [
                    'paid_on' => '2026-09-30', 'account' => 'bank',
                ])->assertOk();
            }
        }
        $this->getJson("/api/v1/clubs/{$club->id}/budgets")->assertOk()
            ->assertJsonPath('data.budgets.0.financial_report.reserved_expense_cents', 0)
            ->assertJsonPath('data.budgets.0.financial_report.actual_expense_cents', 13500);
        Sanctum::actingAs($approver);
        $this->putJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/approval", ['status' => 'approved'])->assertUnprocessable();
        $this->assertSame(2, ClubFinanceEntry::query()->where('club_id', $club->id)->where('category', '4000')->count());
        $this->assertSame('135.00', number_format((float) ClubFinanceEntry::query()->where('club_id', $club->id)->sum('amount'), 2, '.', ''));

        $audit = Activity::query()->where('type', 'club.procurement.received')->latest('id')->firstOrFail();
        $this->assertSame('procurement', $audit->data['entity_type']);
        $this->assertArrayNotHasKey('description', $audit->data);
        $this->assertStringNotContainsString('First carton', json_encode($audit->data, JSON_THROW_ON_ERROR));
    }

    public function test_procurement_enforces_club_boundaries_budget_approval_and_received_quantities(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => User::factory()]);
        $period = $this->businessPeriod($club);
        $foreignPeriod = $this->businessPeriod($foreignClub);
        $foreignBudget = ClubBudget::query()->create($this->budgetPayload($foreignClub, $foreignPeriod, ['approval_status' => 'approved', 'approved_at' => now()]));
        $draftBudget = ClubBudget::query()->create($this->budgetPayload($club, $period, ['approval_status' => 'draft']));
        $approvedBudget = ClubBudget::query()->create($this->budgetPayload($club, $period, ['approval_status' => 'approved', 'approved_at' => now(), 'name' => 'Approved']));
        $foreignItem = ClubInventoryItem::query()->create([
            'club_id' => $foreignClub->id,
            'name' => 'Foreign stock',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/procurements", $this->payload(['club_budget_id' => $foreignBudget->id]))
            ->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/procurements", $this->payload(['club_budget_id' => $draftBudget->id]))
            ->assertUnprocessable();
        $this->postJson("/api/v1/clubs/{$club->id}/procurements", $this->payload([
            'club_budget_id' => $approvedBudget->id,
            'items' => [['club_inventory_item_id' => $foreignItem->id, 'name' => 'Bad', 'quantity_requested' => 1, 'unit_price_cents' => 100]],
        ]))->assertUnprocessable();

        $procurementId = $this->postJson("/api/v1/clubs/{$club->id}/procurements", $this->payload([
            'club_budget_id' => $approvedBudget->id,
            'status' => 'submitted',
        ]))->assertCreated()->json('data.id');
        ClubProcurementRequest::query()->findOrFail($procurementId)->forceFill([
            'status' => 'approved',
            'approved_by' => $owner->id,
            'approved_at' => now(),
        ])->save();
        $lineId = $this->postJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/order")
            ->assertOk()
            ->json('data.items.0.id');

        $this->postJson("/api/v1/clubs/{$club->id}/procurements/{$procurementId}/receipts", [
            'received_on' => '2026-10-01',
            'items' => [['id' => $lineId, 'quantity_received' => 3]],
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$foreignClub->id}/procurements/{$procurementId}/receipts", [
            'received_on' => '2026-10-01',
            'items' => [['id' => $lineId, 'quantity_received' => 1]],
        ])->assertNotFound();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Procurement',
            'supplier' => 'Supplier',
            'status' => 'draft',
            'items' => [
                ['name' => 'Item', 'quantity_requested' => 2, 'unit_price_cents' => 500],
            ],
        ], $overrides);
    }

    private function budgetPayload(Club $club, ClubYearPeriod $period, array $overrides = []): array
    {
        return array_replace([
            'club_id' => $club->id,
            'club_year_period_id' => $period->id,
            'scope_type' => 'club',
            'name' => 'Budget',
            'version' => 1,
            'approval_status' => 'draft',
            'planned_income_cents' => 0,
            'planned_expense_cents' => 100000,
        ], $overrides);
    }

    private function businessPeriod(Club $club): ClubYearPeriod
    {
        return ClubYearPeriod::query()->create([
            'club_id' => $club->id,
            'type' => 'business',
            'name' => 'Geschäftsjahr '.$club->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    private function assignRole(Club $club, User $member, array $permissions, User $owner): void
    {
        $role = ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => 'procurement_'.uniqid(),
            'name' => 'Procurement role',
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        ClubRoleAssignment::query()->create([
            'club_id' => $club->id,
            'club_role_definition_id' => $role->id,
            'user_id' => $member->id,
            'scope_type' => 'club',
            'scope_id' => null,
            'scope_key' => 'club',
            'assigned_by' => $owner->id,
        ]);
    }
}
