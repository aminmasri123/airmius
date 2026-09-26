<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\MarketplaceProduct;
use App\Models\Team;
use App\Models\TeamBulkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamBulkOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_shop_manager_creates_team_bulk_order_with_immutable_price_snapshot(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($member->id, ['role' => 'player']);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $product = $this->teamwearProduct($owner, $club, [
            'price_cents' => 6499,
            'teamwear_funded_share_cents' => 1500,
        ]);

        Sanctum::actingAs($owner);
        $created = $this->postJson('/api/v1/commerce/team-bulk-orders', [
            'club_id' => $club->id,
            'team_id' => $team->id,
            'marketplace_product_id' => $product->id,
            'title' => 'U17 Heimtrikot',
            'order_deadline_at' => now()->addWeek()->toIso8601String(),
        ])->assertCreated();

        $bulkOrderId = $created->json('data.id');
        $product->update(['price_cents' => 7999, 'teamwear_funded_share_cents' => 500]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/commerce/team-bulk-orders/{$bulkOrderId}/items", [
            'quantity' => 2,
            'personalization' => ['number' => '10', 'name' => 'Amin'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.unit_price_cents', 6499)
            ->assertJsonPath('data.funded_share_cents', 1500)
            ->assertJsonPath('data.payable_unit_price_cents', 4999);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/commerce/team-bulk-orders/{$bulkOrderId}/supplier-export")
            ->assertOk()
            ->assertJsonPath('data.supplier.name', 'Kit Supplier')
            ->assertJsonPath('data.price_snapshot.price_cents', 6499)
            ->assertJsonPath('data.price_snapshot.funded_share_cents', 1500)
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.lines.0.unit_price_cents', 6499);
    }

    public function test_team_bulk_order_deadline_blocks_late_member_orders(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $team->users()->attach($member->id, ['role' => 'player']);
        $product = $this->teamwearProduct($owner, $club);
        $bulkOrder = TeamBulkOrder::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'marketplace_product_id' => $product->id,
            'created_by' => $owner->id,
            'title' => 'Abgelaufene Trikots',
            'order_window_starts_at' => now()->subWeeks(2),
            'order_deadline_at' => now()->subDay(),
            'supplier_name' => 'Kit Supplier',
            'supplier_reference' => 'KIT-1',
            'unit_price_cents' => 4999,
            'funded_share_cents' => 1000,
            'currency' => 'EUR',
            'price_snapshot' => ['price_cents' => 4999, 'funded_share_cents' => 1000],
        ]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/commerce/team-bulk-orders/{$bulkOrder->id}/items", [
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_deadline_at');
    }

    public function test_team_bulk_order_keeps_club_boundaries_for_team_product_and_export(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $team = Team::factory()->create(['club_id' => $club->id]);
        $otherTeam = Team::factory()->create(['club_id' => $otherClub->id]);
        $product = $this->teamwearProduct($owner, $club);
        $foreignProduct = $this->teamwearProduct($otherOwner, $otherClub);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $team->users()->attach($member->id, ['role' => 'player']);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/commerce/team-bulk-orders', [
            'club_id' => $club->id,
            'team_id' => $otherTeam->id,
            'marketplace_product_id' => $product->id,
            'order_deadline_at' => now()->addWeek()->toIso8601String(),
        ])->assertNotFound();

        $this->postJson('/api/v1/commerce/team-bulk-orders', [
            'club_id' => $club->id,
            'team_id' => $team->id,
            'marketplace_product_id' => $foreignProduct->id,
            'order_deadline_at' => now()->addWeek()->toIso8601String(),
        ])->assertNotFound();

        $bulkOrder = TeamBulkOrder::query()->create([
            'club_id' => $club->id,
            'team_id' => $team->id,
            'marketplace_product_id' => $product->id,
            'created_by' => $owner->id,
            'title' => 'Vereinstrikots',
            'order_window_starts_at' => now()->subDay(),
            'order_deadline_at' => now()->addWeek(),
            'supplier_name' => 'Kit Supplier',
            'supplier_reference' => 'KIT-1',
            'unit_price_cents' => 4999,
            'funded_share_cents' => 1000,
            'currency' => 'EUR',
            'price_snapshot' => ['price_cents' => 4999, 'funded_share_cents' => 1000],
        ]);

        Sanctum::actingAs($otherOwner);
        $this->getJson("/api/v1/commerce/team-bulk-orders/{$bulkOrder->id}/supplier-export")
            ->assertNotFound();
    }

    private function teamwearProduct(User $seller, Club $club, array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::query()->create(array_merge([
            'user_id' => $seller->id,
            'club_id' => $club->id,
            'title' => 'Teamwear Trikot',
            'description' => 'Vereinstrikot mit Personalisierung.',
            'category' => 'equipment',
            'offer_type' => 'physical_product',
            'product_type' => 'teamwear',
            'sku' => 'KIT-1',
            'teamwear_supplier' => 'Kit Supplier',
            'teamwear_funded_share_cents' => 1000,
            'teamwear_personalization_rules' => ['number' => true, 'name' => true],
            'price_cents' => 4999,
            'currency' => 'EUR',
            'status' => 'published',
            'moderation_status' => 'approved',
        ], $overrides));
    }
}
