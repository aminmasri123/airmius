<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/clubs/{$club->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonMissingPath('data.items.0.qr_token');

        $loan = $this->postJson("/api/v1/clubs/{$club->id}/inventory/{$itemId}/checkout", [
            'quantity' => 2,
            'due_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.status', 'active');
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
}
