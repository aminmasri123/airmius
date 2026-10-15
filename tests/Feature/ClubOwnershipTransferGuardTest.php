<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteUser;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubOwnershipTransferGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_assign_owner_but_owner_can_transfer_then_delete_personal_account(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $successor = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($admin, ['role' => 'admin', 'roles' => ['admin']]);
        $club->users()->attach($successor, ['role' => 'member', 'roles' => ['member']]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$successor->id}/role", ['role' => 'owner'])
            ->assertForbidden();
        $this->assertSame($owner->id, $club->fresh()->owner_id);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$club->id}/members/{$successor->id}/role", ['role' => 'owner'])
            ->assertOk();
        $this->assertSame($successor->id, $club->fresh()->owner_id);

        app(DeleteUser::class)->delete($owner->fresh());
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('clubs', ['id' => $club->id, 'owner_id' => $successor->id]);
    }
}
