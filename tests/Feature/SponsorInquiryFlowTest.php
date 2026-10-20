<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SponsorInquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notification_target_loads_only_the_requested_sponsor(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $target = Sponsor::query()->create(['name' => 'Antrag A', 'scope' => 'platform']);
        Sponsor::query()->create(['name' => 'Antrag B', 'scope' => 'platform']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/sponsor-management?sponsor_id='.$target->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_club_owner_can_inquire_once_and_foreign_club_cannot_read_or_withdraw_it(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $sponsorOwner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'city' => 'Berlin']);
        $otherClub = Club::factory()->create(['owner_id' => $other->id]);
        $sponsor = Sponsor::query()->create([
            'name' => 'Testmarke', 'scope' => 'platform', 'owner_user_id' => $sponsorOwner->id,
            'verification_status' => 'verified',
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/sponsor-inquiries')->assertOk()
            ->assertJsonPath('clubs.0.id', $club->id);
        $payload = [
            'club_id' => $club->id,
            'sponsor_id' => $sponsor->id,
            'subject' => 'Jugendturnier',
            'message' => 'Wir suchen Unterstützung für unser Jugendturnier in Berlin.',
        ];
        $created = $this->postJson('/api/v1/sponsor-inquiries', $payload)->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/sponsor-inquiries', $payload)->assertUnprocessable();
        $inquiryId = $created->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/sponsor-inquiries')->assertOk()
            ->assertJsonMissing(['id' => $inquiryId]);
        $this->postJson('/api/v1/sponsor-inquiries', [...$payload, 'club_id' => $otherClub->id])
            ->assertCreated();
        $this->deleteJson("/api/v1/sponsor-inquiries/{$inquiryId}")->assertForbidden();

        Sanctum::actingAs($sponsorOwner);
        $this->postJson("/api/v1/sponsor-inquiries/{$inquiryId}/respond", [
            'decision' => 'interested', 'note' => 'Bitte Termin vereinbaren.',
        ])->assertOk()->assertJsonPath('data.status', 'interested');
        $this->postJson("/api/v1/sponsor-inquiries/{$inquiryId}/respond", [
            'decision' => 'declined',
        ])->assertStatus(409);
        $this->assertDatabaseHas('sponsor_inquiries', [
            'id' => $inquiryId,
            'status' => 'interested',
            'response_note' => 'Bitte Termin vereinbaren.',
        ]);
        $this->assertSame(2, SponsorInquiry::query()->count());
    }

    public function test_inquiry_is_rejected_when_sponsor_has_no_responsible_user(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $sponsor = Sponsor::query()->create([
            'name' => 'Unbesetzter Sponsor', 'scope' => 'platform',
            'verification_status' => 'verified',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/sponsor-inquiries', [
            'club_id' => $club->id,
            'sponsor_id' => $sponsor->id,
            'subject' => 'Jugendturnier',
            'message' => 'Wir suchen Unterstützung für unser Jugendturnier in Berlin.',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('sponsor_inquiries', 0);
    }
}
