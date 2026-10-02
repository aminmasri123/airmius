<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubDepartment;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\ClubInventoryMaintenanceRecord;
use App\Models\ClubRoleDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_manager_can_open_the_responsive_web_workspace(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'name' => 'QA Inventarverein']);

        $this->actingAs($owner)
            ->get(route('auth.club-inventory.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubInventory/Index')
                ->where('clubs.0.id', $club->id)
                ->where('clubs.0.name', 'QA Inventarverein'));
    }

    public function test_manager_can_create_item_member_can_borrow_and_return_without_overbooking(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'GPS-Uhr',
            'sku' => 'GPS-001',
            'category' => 'Training',
            'location' => 'Materialraum A',
            'quantity_total' => 2,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => false,
        ])->assertCreated()
            ->assertJsonPath('data.quantity_available', 2)
            ->assertJson(fn ($json) => $json->whereType('data.qr_svg_data_uri', 'string')->etc());
        $itemId = $created->json('data.id');

        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.can_manage_metadata', true);

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_manage_metadata', false)
            ->assertJsonMissingPath('data.items.0.qr_token');

        $loan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/checkout", [
            'quantity' => 2,
            'due_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.responsible_user_id', $member->id)
            ->assertJsonPath('data.responsible_user.id', $member->id);
        $loanId = $loan->json('data.id');

        $this->assertDatabaseHas('club_inventory_items', ['id' => $itemId, 'quantity_available' => 0]);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/checkout", ['quantity' => 1])
            ->assertStatus(422);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loanId}/return", [
            'return_condition' => 'good',
        ])->assertOk()->assertJsonPath('data.status', 'returned');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $itemId, 'quantity_available' => 2]);
    }

    public function test_approval_cross_club_boundaries_and_maintenance_lifecycle_are_enforced(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsiderOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $outsiderOwner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Vereinsrad',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => true,
        ]);

        Sanctum::actingAs($member);
        $loan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", ['quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $loanId = $loan->json('data.id');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 1]);

        Sanctum::actingAs($outsiderOwner);
        $this->postJson("/api/v1/clubs/{$otherClub->id}/inventory/loans/{$loanId}/approve")
            ->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loanId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 0]);

        $maintenance = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/maintenance", [
            'title' => 'Bremsen prüfen',
            'description' => 'Hinterradbremse schleift.',
        ])->assertCreated()->assertJsonPath('data.status', 'open');
        $maintenanceId = $maintenance->json('data.id');

        $this->putJson("/api/v1/clubs/{$club->id}/inventory/maintenance/{$maintenanceId}", [
            'status' => 'completed',
            'description' => 'Beläge erneuert.',
            'cost' => 39.90,
        ])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('club_inventory_maintenance_records', [
            'id' => $maintenanceId,
            'resolved_by' => $owner->id,
            'status' => 'completed',
        ]);
    }

    public function test_approval_required_checkout_cannot_be_self_approved_even_by_an_inventory_approver(): void
    {
        $owner = User::factory()->create();
        $secondApprover = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($secondApprover->id, [
            'role' => 'manager', 'roles' => ['manager'], 'membership_status' => 'active',
        ]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Freigabepflichtiges Gerät',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => true,
        ]);

        Sanctum::actingAs($owner);
        $loanId = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_by', $owner->id)
            ->json('data.id');
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loanId}/approve")
            ->assertUnprocessable();
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.loans.0.id', $loanId)
            ->assertJsonPath('data.loans.0.can_approve', false);
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 1]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.inventory.loan_requested',
        ]);
        $this->assertDatabaseMissing('activities', ['type' => 'club.inventory.loan_approved']);

        Sanctum::actingAs($secondApprover);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loanId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 0]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $secondApprover->id,
            'type' => 'club.inventory.loan_approved',
        ]);
    }

    public function test_recurring_maintenance_tasks_assign_responsibles_and_block_resource_windows(): void
    {
        $owner = User::factory()->create();
        $caretaker = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($caretaker->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $club->users()->attach($member->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Sporthalle Feld 1',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
            'resource_type' => 'field',
            'requires_approval' => false,
        ]);

        Sanctum::actingAs($owner);
        $maintenance = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/maintenance", [
            'type' => 'cleaning',
            'title' => 'Woechentliche Grundreinigung',
            'description' => 'Halle sperren, Boden reinigen, Sichtpruefung dokumentieren.',
            'responsible_user_id' => $caretaker->id,
            'starts_at' => '2026-10-05T09:00:00+00:00',
            'ends_at' => '2026-10-05T11:00:00+00:00',
            'recurrence_frequency' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_until' => '2026-10-31',
            'blocks_resource' => true,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'cleaning')
            ->assertJsonPath('data.responsible_user.id', $caretaker->id)
            ->assertJsonPath('data.blocks_resource', true);
        $maintenanceId = $maintenance->json('data.id');

        $this->assertDatabaseHas('club_inventory_maintenance_records', [
            'id' => $maintenanceId,
            'type' => 'cleaning',
            'responsible_user_id' => $caretaker->id,
            'recurrence_frequency' => 'weekly',
            'blocks_resource' => true,
        ]);
        $this->assertSame(
            'inventory_maintenance',
            ClubInventoryItem::query()->find($item->id)->booking_rules['blackout_windows'][0]['source']
        );

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-10-05T10:00:00+00:00',
            'due_at' => '2026-10-05T12:00:00+00:00',
        ])->assertUnprocessable();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/inventory/maintenance/{$maintenanceId}", [
            'status' => 'completed',
            'description' => 'Erledigt.',
            'cost' => 0,
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('club_inventory_maintenance_records', [
            'parent_id' => $maintenanceId,
            'status' => 'open',
            'responsible_user_id' => $caretaker->id,
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 11:00:00',
        ]);
        $this->assertCount(2, ClubInventoryItem::query()->find($item->id)->booking_rules['blackout_windows']);
    }

    public function test_inventory_movements_model_purchase_batch_deposit_consumption_shrinkage_and_corrections(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);
        $itemId = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'Vereinsball',
            'sku' => 'BALL-001',
            'article_number' => 'ART-BALL',
            'category' => 'Spielmaterial',
            'location' => 'Lager',
            'quantity_total' => 5,
            'condition' => 'new',
            'status' => 'active',
            'requires_approval' => false,
            'batch_number' => 'BATCH-A',
            'purchase_price_cents' => 2499,
            'deposit_cents' => 500,
            'supplier' => 'Sporthandel',
            'purchased_on' => '2026-09-01',
        ])->assertCreated()
            ->assertJsonPath('data.article_number', 'ART-BALL')
            ->assertJsonPath('data.batch_number', 'BATCH-A')
            ->assertJsonPath('data.deposit_cents', 500)
            ->json('data.id');

        $purchase = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/movements", [
            'type' => 'purchase',
            'quantity' => 3,
            'reason' => 'Nachkauf Jugendtraining',
            'occurred_on' => '2026-09-10',
            'batch_number' => 'BATCH-B',
            'purchase_price_cents' => 1999,
            'deposit_cents' => 500,
            'supplier' => 'Sporthandel',
        ])->assertCreated()
            ->assertJsonPath('data.item.quantity_available', 8)
            ->assertJsonPath('data.movement.quantity_delta', 3)
            ->json('data.movement.id');

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/movements", [
            'type' => 'consumption',
            'quantity' => 2,
            'reason' => 'Ausgabe an Trainingsgruppe',
        ])->assertCreated()
            ->assertJsonPath('data.item.quantity_available', 6)
            ->assertJsonPath('data.movement.quantity_delta', -2);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/movements", [
            'type' => 'shrinkage',
            'quantity' => 1,
            'reason' => 'Defekt nach Turnier',
        ])->assertCreated()
            ->assertJsonPath('data.item.quantity_available', 5)
            ->assertJsonPath('data.movement.quantity_delta', -1);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/movements", [
            'type' => 'correction',
            'quantity' => 7,
            'reason' => 'Inventurkorrektur mit Belegprüfung',
            'correction_of_id' => $purchase,
        ])->assertCreated()
            ->assertJsonPath('data.item.quantity_available', 7)
            ->assertJsonPath('data.movement.correction_of_id', $purchase)
            ->assertJsonPath('data.movement.correction_snapshot.id', $purchase)
            ->assertJsonPath('data.movement.quantity_before', 5)
            ->assertJsonPath('data.movement.quantity_after', 7);

        $this->assertDatabaseHas('club_inventory_movements', [
            'club_inventory_item_id' => $itemId,
            'type' => 'shrinkage',
            'quantity_delta' => -1,
        ]);
        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'type' => 'club.inventory.movement_recorded',
        ]);
    }

    public function test_inventory_financial_thresholds_warn_require_approval_and_audit_purchase_exceptions(): void
    {
        $owner = User::factory()->create();
        $approver = User::factory()->create();
        $unauthorized = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => $foreignOwner->id]);
        foreach ([$approver, $unauthorized] as $user) {
            $club->users()->attach($user->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        }
        Sanctum::actingAs($owner);
        $this->assignInventoryRole($club, $approver, $this->inventoryRole($club, 'purchase_threshold_approver', [
            ClubPermissions::INVENTORY_VIEW,
            ClubPermissions::INVENTORY_APPROVE,
        ]), 'club', null);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Budgetkontrollierter Materialsatz',
            'quantity_total' => 2,
            'quantity_available' => 2,
            'purchase_price_cents' => 1000,
            'condition' => 'good',
            'status' => 'active',
            'booking_rules' => [
                'financial_controls' => [
                    'purchase_warning_cents' => 2000,
                    'purchase_approval_cents' => 5000,
                    'issue_warning_cents' => 1500,
                    'issue_approval_cents' => 3000,
                ],
            ],
        ]);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/movements", [
            'type' => 'purchase',
            'quantity' => 5,
            'purchase_price_cents' => 1000,
            'reason' => 'Nachkauf innerhalb Freigabegrenze',
        ])->assertCreated()
            ->assertJsonPath('data.movement.quantity_after', 7);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/movements", [
            'type' => 'purchase',
            'quantity' => 6,
            'purchase_price_cents' => 1000,
            'reason' => 'Nachkauf oberhalb Freigabegrenze',
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/movements", [
            'type' => 'purchase',
            'quantity' => 6,
            'purchase_price_cents' => 1000,
            'reason' => 'Nachkauf oberhalb Freigabegrenze',
            'exception_approved_by' => $unauthorized->id,
        ])->assertForbidden();

        $movementId = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/movements", [
            'type' => 'purchase',
            'quantity' => 6,
            'purchase_price_cents' => 1000,
            'reason' => 'Nachkauf oberhalb Freigabegrenze',
            'exception_approved_by' => $approver->id,
        ])->assertCreated()
            ->assertJsonPath('data.movement.quantity_after', 13)
            ->assertJsonPath('data.movement.quantity_delta', 6)
            ->json('data.movement.id');

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.inventory.financial_exception_approved',
            'subject_id' => $movementId,
        ]);
        $activity = Activity::query()
            ->where('type', 'club.inventory.financial_exception_approved')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('purchase', $activity->data['decision_type']);
        $this->assertArrayNotHasKey('reason', $activity->data);

        Sanctum::actingAs($foreignOwner);
        $this->postJson("/api/v1/clubs/{$foreignClub->id}/inventory/{$item->id}/movements", [
            'type' => 'purchase',
            'quantity' => 1,
            'purchase_price_cents' => 1000,
            'reason' => 'Fremdverein darf nicht buchen',
            'exception_approved_by' => $foreignOwner->id,
        ])->assertNotFound();
    }

    public function test_inventory_issue_thresholds_require_four_eyes_or_pending_approval(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $approver = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $foreignClub = Club::factory()->create(['owner_id' => $foreignOwner->id]);
        foreach ([$member, $approver] as $user) {
            $club->users()->attach($user->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        }
        Sanctum::actingAs($owner);
        $this->assignInventoryRole($club, $approver, $this->inventoryRole($club, 'issue_threshold_approver', [
            ClubPermissions::INVENTORY_VIEW,
            ClubPermissions::INVENTORY_APPROVE,
        ]), 'club', null);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Wertvoller Ausgabeartikel',
            'quantity_total' => 3,
            'quantity_available' => 3,
            'purchase_price_cents' => 2000,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => false,
            'booking_rules' => [
                'financial_controls' => [
                    'issue_warning_cents' => 2000,
                    'issue_approval_cents' => 3000,
                ],
            ],
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $pendingLoanId = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 2]);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$pendingLoanId}/approve")
            ->assertForbidden();

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/clubs/{$foreignClub->id}/inventory/loans/{$pendingLoanId}/approve")
            ->assertNotFound();
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$pendingLoanId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 0]);
    }

    public function test_inventory_loan_requester_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_25_000009_add_requester_to_club_inventory_loans.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('club_inventory_loans', 'requested_by'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('club_inventory_loans', 'requested_by'));
    }

    public function test_loan_output_return_deadline_loss_damage_and_responsibility_transfer_are_historized(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $successor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        foreach ([$member, $successor] as $user) {
            $club->users()->attach($user->id, [
                'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
            ]);
        }
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Materialsatz',
            'quantity_total' => 3,
            'quantity_available' => 3,
            'condition' => 'good',
            'status' => 'active',
        ]);

        Sanctum::actingAs($member);
        $returnedLoan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
            'due_at' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated()->json('data.id');
        $lostLoan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
        ])->assertCreated()->json('data.id');
        $transferredLoan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 0]);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$returnedLoan}/return", [
            'return_condition' => 'damaged',
            'damage_description' => 'Display gerissen',
            'notes' => 'Bei Rueckgabe aufgenommen',
        ])->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.return_condition', 'damaged')
            ->assertJsonPath('data.damage_description', 'Display gerissen');
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$lostLoan}/return", [
            'outcome' => 'lost',
            'notes' => 'Nicht auffindbar',
        ])->assertOk()->assertJsonPath('data.status', 'lost');
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$transferredLoan}/return", [
            'outcome' => 'responsibility_transferred',
            'responsibility_transferred_to' => $successor->id,
        ])->assertOk()
            ->assertJsonPath('data.responsibility_transferred_to', $successor->id)
            ->assertJsonPath('data.responsible_user_id', $successor->id)
            ->assertJsonPath('data.responsible_user.id', $successor->id);

        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $returnedLoan,
            'return_condition' => 'damaged',
            'damage_description' => 'Display gerissen',
            'responsible_user_id' => null,
        ]);
        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $lostLoan,
            'responsible_user_id' => null,
        ]);
        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $transferredLoan,
            'responsible_user_id' => $successor->id,
        ]);
        $this->assertNotNull(ClubInventoryLoan::query()->findOrFail($returnedLoan)->issued_at);
        $this->assertNotNull(ClubInventoryLoan::query()->findOrFail($returnedLoan)->damaged_at);
        $this->assertNotNull(ClubInventoryLoan::query()->findOrFail($lostLoan)->lost_at);
        $this->assertNotNull(ClubInventoryLoan::query()->findOrFail($transferredLoan)->responsibility_transferred_at);
        $this->assertDatabaseHas('club_inventory_items', ['id' => $item->id, 'quantity_available' => 1]);
    }

    public function test_inventory_handover_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_26_000034_historize_inventory_loans_for_access_handover.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('club_inventory_loans', 'issued_at'));
        $this->assertFalse(Schema::hasColumn('club_access_handover_reviews', 'inventory_loan_snapshot'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('club_inventory_loans', 'issued_at'));
        $this->assertTrue(Schema::hasColumn('club_access_handover_reviews', 'inventory_loan_snapshot'));
    }

    public function test_inventory_loan_responsible_user_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_09_26_000057_add_responsible_user_to_club_inventory_loans.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('club_inventory_loans', 'responsible_user_id'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('club_inventory_loans', 'responsible_user_id'));
    }

    public function test_inventory_actions_are_separated_and_limited_to_department_and_team_scopes(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $borrower = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($actor->id, [
            'role' => 'financial_controller', 'roles' => ['financial_controller'], 'membership_status' => 'active',
        ]);
        $club->users()->attach($borrower->id, [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false]);
        $otherDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false]);
        $team = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $department->id]);
        $otherTeam = Team::factory()->create(['club_id' => $club->id, 'club_department_id' => $otherDepartment->id]);
        $teamItem = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'team_id' => $team->id,
            'name' => 'Jugend-Bälle',
            'quantity_total' => 3,
            'quantity_available' => 3,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => true,
        ]);
        $otherItem = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $otherDepartment->id,
            'team_id' => $otherTeam->id,
            'name' => 'Senioren-Bälle',
            'quantity_total' => 2,
            'quantity_available' => 2,
            'condition' => 'good',
            'status' => 'active',
        ]);
        $viewer = $this->inventoryRole($club, 'inventory_viewer', [ClubPermissions::INVENTORY_VIEW]);
        $editor = $this->inventoryRole($club, 'inventory_editor', [ClubPermissions::INVENTORY_EDIT]);
        $approver = $this->inventoryRole($club, 'inventory_approver', [ClubPermissions::INVENTORY_APPROVE]);
        $deleter = $this->inventoryRole($club, 'inventory_deleter', [ClubPermissions::INVENTORY_DELETE]);

        Sanctum::actingAs($owner);
        $this->assignInventoryRole($club, $actor, $viewer, 'department', $department->id);

        Sanctum::actingAs($actor);
        $this->get(route('auth.club-inventory.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/ClubInventory/Index')
                ->where('clubs.0.id', $club->id));
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $teamItem->id)
            ->assertJsonPath('data.items.0.can_checkout', true)
            ->assertJsonPath('data.items.0.can_edit', false)
            ->assertJsonMissing(['id' => $otherItem->id]);
        $this->putJson("/api/v1/clubs/{$club->id}/inventory/{$teamItem->id}", $this->itemPayload($teamItem, ['name' => 'Nicht erlaubt']))
            ->assertForbidden();

        Sanctum::actingAs($borrower);
        $loan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$teamItem->id}/checkout", ['quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $loanId = $loan->json('data.id');

        Sanctum::actingAs($owner);
        $this->assignInventoryRole($club, $actor, $editor, 'team', $team->id);

        Sanctum::actingAs($actor);
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.items.0.can_checkout', false)
            ->assertJsonPath('data.items.0.can_edit', true)
            ->assertJsonPath('data.items.0.can_approve', false);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'club_department_id' => $department->id,
            'team_id' => $team->id,
            'name' => 'Neue Leibchen',
            'quantity_total' => 10,
            'condition' => 'new',
            'status' => 'active',
            'requires_approval' => false,
        ])->assertCreated();
        $cleanItemId = $created->json('data.id');
        $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'club_department_id' => $otherDepartment->id,
            'team_id' => $otherTeam->id,
            'name' => 'Fremder Bestand',
            'quantity_total' => 1,
            'condition' => 'new',
            'status' => 'active',
        ])->assertForbidden();
        $this->putJson("/api/v1/clubs/{$club->id}/inventory/{$teamItem->id}", $this->itemPayload($teamItem, [
            'club_department_id' => $otherDepartment->id,
            'team_id' => $otherTeam->id,
        ]))->assertForbidden();

        Sanctum::actingAs($owner);
        $this->assignInventoryRole($club, $actor, $approver, 'team', $team->id);

        Sanctum::actingAs($actor);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/loans/{$loanId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->putJson("/api/v1/clubs/{$club->id}/inventory/{$teamItem->id}", $this->itemPayload($teamItem, ['name' => 'Nicht editierbar']))
            ->assertForbidden();
        $this->deleteJson("/api/v1/clubs/{$club->id}/inventory/{$cleanItemId}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->assignInventoryRole($club, $actor, $deleter, 'team', $team->id);

        Sanctum::actingAs($actor);
        $this->deleteJson("/api/v1/clubs/{$club->id}/inventory/{$teamItem->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item');
        $this->deleteJson("/api/v1/clubs/{$club->id}/inventory/{$cleanItemId}")->assertOk();
        $this->deleteJson("/api/v1/clubs/{$club->id}/inventory/{$otherItem->id}")->assertForbidden();
        $this->assertDatabaseMissing('club_inventory_items', ['id' => $cleanItemId]);
        $this->assertDatabaseHas('club_inventory_items', ['id' => $teamItem->id]);
        $this->assertDatabaseHas('club_inventory_items', ['id' => $otherItem->id]);
    }

    public function test_hierarchical_resources_use_atomic_capacity_windows_without_breaking_legacy_loans(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $facilityId = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'Sportanlage Nord',
            'sku' => 'FAC-NORD',
            'resource_type' => 'facility',
            'quantity_total' => 1,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => false,
        ])->assertCreated()
            ->assertJsonPath('data.resource_type', 'facility')
            ->json('data.id');

        $roomId = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'Halle 1',
            'sku' => 'ROOM-1',
            'resource_type' => 'room',
            'parent_id' => $facilityId,
            'quantity_total' => 2,
            'condition' => 'good',
            'status' => 'active',
            'requires_approval' => false,
        ])->assertCreated()
            ->assertJsonPath('data.parent.id', $facilityId)
            ->assertJsonPath('data.resource_type', 'room')
            ->json('data.id');

        Sanctum::actingAs($member);
        $firstStart = now()->addDays(2)->setMinute(0)->setSecond(0);
        $firstEnd = (clone $firstStart)->addHours(2);
        $bookingId = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$roomId}/checkout", [
            'quantity' => 2,
            'starts_at' => $firstStart->toIso8601String(),
            'due_at' => $firstEnd->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.quantity', 2)
            ->json('data.id');

        $this->assertDatabaseHas('club_inventory_loans', [
            'id' => $bookingId,
            'club_inventory_item_id' => $roomId,
            'starts_at' => $firstStart->toDateTimeString(),
            'due_at' => $firstEnd->toDateTimeString(),
        ]);
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.loans.0.id', $bookingId)
            ->assertJsonPath('data.loans.0.item.id', $roomId)
            ->assertJsonPath('data.loans.0.quantity', 2);

        $this->assertDatabaseHas('club_inventory_items', ['id' => $roomId, 'quantity_available' => 2]);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$roomId}/checkout", [
            'quantity' => 1,
            'starts_at' => (clone $firstStart)->addHour()->toIso8601String(),
            'due_at' => (clone $firstEnd)->addHour()->toIso8601String(),
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$roomId}/checkout", [
            'quantity' => 1,
            'starts_at' => (clone $firstEnd)->addMinute()->toIso8601String(),
            'due_at' => (clone $firstEnd)->addHours(2)->toIso8601String(),
        ])->assertCreated();

        $ball = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Legacy Ballwagen',
            'resource_type' => 'equipment',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$ball->id}/checkout", ['quantity' => 1])->assertCreated();
        $this->assertDatabaseHas('club_inventory_items', ['id' => $ball->id, 'quantity_available' => 0]);
    }

    public function test_resource_hierarchy_rejects_self_parent_and_descendant_cycles(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $parent = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Anlage',
            'resource_type' => 'facility',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);
        $child = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'parent_id' => $parent->id,
            'name' => 'Teilflaeche',
            'resource_type' => 'area',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/inventory/{$parent->id}", $this->itemPayload($parent, [
            'parent_id' => $parent->id,
        ]))->assertJsonValidationErrors('parent_id');
        $this->putJson("/api/v1/clubs/{$club->id}/inventory/{$parent->id}", $this->itemPayload($parent, [
            'parent_id' => $child->id,
        ]))->assertJsonValidationErrors('parent_id');
    }

    public function test_hierarchical_resource_opening_hours_blackouts_priorities_and_tenants_are_enforced(): void
    {
        $this->travelTo(Carbon::parse('2026-09-27T08:00:00+00:00'));
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $otherClub->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $facilityId = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'Campus',
            'resource_type' => 'facility',
            'opening_hours' => [
                'monday' => [['start' => '08:00', 'end' => '20:00']],
            ],
            'quantity_total' => 1,
            'condition' => 'good',
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $fieldId = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'Court A',
            'resource_type' => 'field',
            'parent_id' => $facilityId,
            'opening_hours' => [
                'monday' => [['start' => '09:00', 'end' => '12:00']],
            ],
            'booking_rules' => [
                'blackout_windows' => [[
                    'starts_at' => '2026-09-28T10:30:00+00:00',
                    'ends_at' => '2026-09-28T11:00:00+00:00',
                    'reason' => 'Pflege',
                ]],
            ],
            'quantity_total' => 1,
            'condition' => 'good',
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.parent.id', $facilityId)
            ->assertJsonPath('data.opening_hours.monday.0.start', '09:00')
            ->json('data.id');

        $otherClubField = ClubInventoryItem::query()->create([
            'club_id' => $otherClub->id,
            'name' => 'Andere Halle',
            'resource_type' => 'field',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$fieldId}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-09-28T09:30:00+00:00',
            'due_at' => '2026-09-28T10:30:00+00:00',
        ])->assertCreated()
            ->assertJsonPath('data.booking_priority', 100);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$fieldId}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-09-28T10:45:00+00:00',
            'due_at' => '2026-09-28T11:15:00+00:00',
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$fieldId}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-09-28T08:40:00+00:00',
            'due_at' => '2026-09-28T09:10:00+00:00',
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$fieldId}/checkout", [
            'quantity' => 1,
            'booking_priority' => 50,
            'starts_at' => '2026-09-28T09:45:00+00:00',
            'due_at' => '2026-09-28T10:15:00+00:00',
        ])->assertCreated()
            ->assertJsonPath('data.booking_priority', 50);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$fieldId}/checkout", [
            'quantity' => 1,
            'booking_priority' => 200,
            'starts_at' => '2026-09-28T09:50:00+00:00',
            'due_at' => '2026-09-28T10:10:00+00:00',
        ])->assertUnprocessable();

        $this->postJson("/api/v1/clubs/{$otherClub->id}/inventory/{$fieldId}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-09-28T08:00:00+00:00',
            'due_at' => '2026-09-28T08:30:00+00:00',
        ])->assertNotFound();

        $this->postJson("/api/v1/clubs/{$otherClub->id}/inventory/{$otherClubField->id}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-09-28T08:00:00+00:00',
            'due_at' => '2026-09-28T08:30:00+00:00',
        ])->assertCreated();
    }

    public function test_damage_report_protects_photo_manifest_tracks_cost_status_and_blocks_bookability(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'name' => 'Kunstrasenplatz',
            'resource_type' => 'field',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/damage-reports", [
            'title' => 'Torraum aufgerissen',
            'description' => 'Riss im Belag nahe Strafraum.',
            'severity' => 'critical',
            'booking_impact' => 'full_block',
            'estimated_cost_cents' => 125000,
            'starts_at' => '2026-09-28T08:00:00+00:00',
            'ends_at' => '2026-09-28T18:00:00+00:00',
            'photos' => [[
                'storage_disk' => 'private',
                'storage_path' => 'clubs/1/inventory/damage/photo.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 204800,
                'sha256' => str_repeat('a', 64),
                'caption' => 'Nahaufnahme mit Mitglied im Hintergrund',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'damage')
            ->assertJsonPath('data.severity', 'critical')
            ->assertJsonPath('data.booking_impact', 'full_block')
            ->assertJsonPath('data.estimated_cost_cents', 125000)
            ->assertJsonPath('data.protected_photo_manifest.0.protected', true)
            ->assertJsonPath('data.protected_photo_manifest.0.visibility', 'inventory_managers')
            ->assertJsonPath('data.item.status', 'maintenance')
            ->assertJsonPath('data.item.condition', 'damaged');

        $this->assertDatabaseHas('club_inventory_maintenance_records', [
            'club_inventory_item_id' => $item->id,
            'type' => 'damage',
            'severity' => 'critical',
            'booking_impact' => 'full_block',
            'estimated_cost_cents' => 125000,
            'blocks_resource' => true,
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/checkout", [
            'quantity' => 1,
            'starts_at' => '2026-09-28T10:00:00+00:00',
            'due_at' => '2026-09-28T11:00:00+00:00',
        ])->assertUnprocessable();

        $activity = Activity::query()->where('type', 'club.inventory.damage_reported')->firstOrFail();
        $this->assertSame(1, $activity->data['photos_count']);
        $this->assertArrayNotHasKey('protected_photo_manifest', $activity->data);
        $this->assertStringNotContainsString('Nahaufnahme', json_encode($activity->data, JSON_THROW_ON_ERROR));
    }

    public function test_damage_report_respects_inventory_scopes_and_club_boundaries(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $otherOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $club->users()->attach($actor->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);
        $department = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Jugend', 'is_public' => false]);
        $otherDepartment = ClubDepartment::query()->create(['club_id' => $club->id, 'name' => 'Senioren', 'is_public' => false]);
        $item = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $department->id,
            'name' => 'Jugendhalle',
            'resource_type' => 'hall',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);
        $otherItem = ClubInventoryItem::query()->create([
            'club_id' => $club->id,
            'club_department_id' => $otherDepartment->id,
            'name' => 'Seniorenhalle',
            'resource_type' => 'hall',
            'quantity_total' => 1,
            'quantity_available' => 1,
            'condition' => 'good',
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);
        $this->assignInventoryRole(
            $club,
            $actor,
            $this->inventoryRole($club, 'damage_editor', [ClubPermissions::INVENTORY_EDIT]),
            'department',
            $department->id,
        );

        Sanctum::actingAs($actor);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$item->id}/damage-reports", [
            'title' => 'Prallwand locker',
            'severity' => 'major',
            'booking_impact' => 'warning',
        ])->assertCreated();
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$otherItem->id}/damage-reports", [
            'title' => 'Falscher Scope',
            'severity' => 'minor',
            'booking_impact' => 'none',
        ])->assertForbidden();
        $this->postJson("/api/v1/clubs/{$otherClub->id}/inventory/{$item->id}/damage-reports", [
            'title' => 'Falscher Verein',
            'severity' => 'minor',
            'booking_impact' => 'none',
        ])->assertNotFound();

        $this->assertSame(1, ClubInventoryMaintenanceRecord::query()->where('type', 'damage')->count());
    }

    public function test_inventory_qr_lifecycle_uses_signed_minimal_payload_reissue_and_revoke(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, ['role' => 'member', 'roles' => ['member'], 'membership_status' => 'active']);

        Sanctum::actingAs($owner);
        $created = $this->postJson("/api/v1/clubs/{$club->id}/inventory", [
            'name' => 'QR Leibchen',
            'quantity_total' => 4,
            'condition' => 'good',
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.qr_revoked_at', null)
            ->assertJson(fn ($json) => $json
                ->whereType('data.qr_payload', 'string')
                ->whereType('data.qr_issued_at', 'string')
                ->whereType('data.qr_svg_data_uri', 'string')
                ->etc());

        $itemId = $created->json('data.id');
        $payload = $created->json('data.qr_payload');

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/scan", [
            'qr_token' => $payload,
        ])->assertOk()
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonMissingPath('data.qr_token')
            ->assertJsonMissingPath('data.qr_payload');

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/scan", [
            'qr_token' => $this->tamperQrPayload($payload),
        ])->assertNotFound();

        Sanctum::actingAs($owner);
        $reissued = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/qr/reissue")
            ->assertOk()
            ->assertJsonPath('data.qr_revoked_at', null)
            ->json('data.qr_payload');
        $this->assertNotSame($payload, $reissued);

        $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/qr/revoke")
            ->assertOk()
            ->assertJson(fn ($json) => $json->whereType('data.qr_revoked_at', 'string')->etc());

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/clubs/{$club->id}/inventory/scan", [
            'qr_token' => $reissued,
        ])->assertGone();
    }

    private function inventoryRole(Club $club, string $key, array $permissions): ClubRoleDefinition
    {
        return ClubRoleDefinition::query()->create([
            'club_id' => $club->id,
            'key' => $key,
            'name' => str_replace('_', ' ', ucfirst($key)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
    }

    private function assignInventoryRole(Club $club, User $actor, ClubRoleDefinition $role, string $scopeType, ?int $scopeId): void
    {
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$actor->id}/role-definitions", [
            'assignments' => [[
                'role_definition_id' => $role->id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ]],
        ])->assertOk();
    }

    private function itemPayload(ClubInventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'club_department_id' => $item->club_department_id,
            'team_id' => $item->team_id,
            'parent_id' => $item->parent_id,
            'resource_type' => $item->resource_type ?? 'equipment',
            'opening_hours' => $item->opening_hours,
            'booking_rules' => $item->booking_rules,
            'name' => $item->name,
            'sku' => $item->sku,
            'category' => $item->category,
            'location' => $item->location,
            'description' => $item->description,
            'article_number' => $item->article_number,
            'batch_number' => $item->batch_number,
            'purchase_price_cents' => $item->purchase_price_cents,
            'deposit_cents' => $item->deposit_cents,
            'supplier' => $item->supplier,
            'purchased_on' => $item->purchased_on?->toDateString(),
            'quantity_total' => $item->quantity_total,
            'condition' => $item->condition,
            'status' => $item->status,
            'requires_approval' => (bool) $item->requires_approval,
        ], $overrides);
    }

    private function tamperQrPayload(string $payload): string
    {
        $prefix = 'airmius-inventory:';
        $encoded = substr($payload, strlen($prefix));
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $decoded = json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);
        $decoded['i']++;
        $tampered = rtrim(strtr(base64_encode(json_encode($decoded, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $prefix.$tampered;
    }
}
