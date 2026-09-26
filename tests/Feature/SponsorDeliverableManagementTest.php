<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorDeliverable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorDeliverableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_owner_can_document_deliverable_with_place_period_responsible_and_evidence(): void
    {
        $owner = User::factory()->create();
        $responsible = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($responsible->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $sponsor = Sponsor::query()->create([
            'club_id' => $club->id,
            'scope' => 'club',
            'name' => 'Stadtwerke',
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson(
            route('api.v1.sponsor-management.deliverables.store', $sponsor),
            [
                'title' => 'Bandenwerbung Heimspiel',
                'location' => 'Sportplatz Nord',
                'starts_at' => '2026-09-01',
                'ends_at' => '2026-09-30',
                'due_at' => '2026-09-15',
                'responsible_user_id' => $responsible->id,
                'status' => 'fulfilled',
                'fulfillment_evidence' => 'Foto im Medienarchiv REF-123 und Abnahme durch Sponsor.',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.location', 'Sportplatz Nord')
            ->assertJsonPath('data.responsible.id', $responsible->id)
            ->assertJsonPath('data.status', 'fulfilled')
            ->assertJsonPath('data.is_overdue', false);

        $deliverable = SponsorDeliverable::query()->sole();
        $this->assertSame($club->id, $deliverable->club_id);
        $this->assertSame($owner->id, $deliverable->fulfilled_by);
        $this->assertNotNull($deliverable->fulfilled_at);

        $audit = Activity::query()->where('type', 'club.sponsor.deliverable.fulfilled')->sole();
        $this->assertSame($club->id, $audit->club_id);
        $this->assertTrue($audit->data['has_evidence']);
        $this->assertArrayNotHasKey('fulfillment_evidence', $audit->data);
    }

    public function test_index_reports_overdue_deliverables_without_cross_club_leakage(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $otherMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $sponsor = Sponsor::query()->create(['club_id' => $club->id, 'scope' => 'club', 'name' => 'Local One']);
        $otherSponsor = Sponsor::query()->create(['club_id' => $otherClub->id, 'scope' => 'club', 'name' => 'Local Two']);

        $sponsor->deliverables()->create([
            'club_id' => $club->id,
            'title' => 'Logo im Programmheft',
            'due_at' => now()->subDay()->toDateString(),
            'status' => 'planned',
        ]);
        $otherSponsor->deliverables()->create([
            'club_id' => $otherClub->id,
            'title' => 'Fremder Nachweis',
            'due_at' => now()->subDay()->toDateString(),
            'status' => 'planned',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson(route('api.v1.sponsor-management.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sponsor->id)
            ->assertJsonPath('data.0.deliverables_summary.overdue', 1)
            ->assertJsonPath('data.0.deliverables.0.is_overdue', true);
    }

    public function test_responsible_user_must_belong_to_the_sponsor_club(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $sponsor = Sponsor::query()->create(['club_id' => $club->id, 'scope' => 'club', 'name' => 'Local One']);

        $this->actingAs($owner, 'sanctum')->postJson(
            route('api.v1.sponsor-management.deliverables.store', $sponsor),
            [
                'title' => 'Social-Media-Post',
                'responsible_user_id' => $outsider->id,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('responsible_user_id');
    }

    public function test_users_cannot_manage_deliverables_for_another_club_sponsor(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $otherMember = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $otherClub = Club::factory()->create(['owner_id' => $otherOwner->id]);
        $sponsor = Sponsor::query()->create(['club_id' => $club->id, 'scope' => 'club', 'name' => 'Local One']);
        $deliverable = $sponsor->deliverables()->create([
            'club_id' => $club->id,
            'title' => 'Banner',
            'status' => 'planned',
        ]);
        $otherClub->users()->attach($otherMember->id, [
            'role' => 'manager',
            'roles' => ['manager'],
            'membership_status' => 'active',
        ]);

        $this->actingAs($otherMember, 'sanctum')->putJson(
            route('api.v1.sponsor-management.deliverables.update', [$sponsor, $deliverable]),
            ['title' => 'Manipulierter Banner'],
        )->assertForbidden();

        $this->assertSame('Banner', $deliverable->refresh()->title);
    }
}
