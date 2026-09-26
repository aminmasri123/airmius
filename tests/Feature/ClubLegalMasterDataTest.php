<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubLegalMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_private_legal_master_data_with_audit_metadata(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'country' => 'DE']);
        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'registry_authority' => '  Amtsgericht Köln  ',
            'registry_number' => ' VR 12345 ',
            'federation_affiliations' => [[
                'name' => '  Fußball-Verband Mittelrhein ',
                'member_number' => ' FVM-77 ',
                'valid_from' => '2024-01-01',
                'valid_until' => null,
            ]],
            'tax_authority' => ' Finanzamt Köln ',
            'tax_number' => ' 123/456/78901 ',
            'vat_id' => ' de 123 456 789 ',
            'tax_status' => 'nonprofit',
            'tax_exemption_valid_until' => '2027-12-31',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.registry_authority', 'Amtsgericht Köln')
            ->assertJsonPath('data.registry_number', 'VR 12345')
            ->assertJsonPath('data.vat_id', 'DE123456789')
            ->assertJsonPath('data.federation_affiliations.0.name', 'Fußball-Verband Mittelrhein');

        $club->refresh();
        $this->assertSame('Finanzamt Köln', $club->tax_authority);
        $this->assertSame('123/456/78901', $club->tax_number);
        $this->assertSame('nonprofit', $club->tax_status);
        $this->assertSame('FVM-77', $club->federation_affiliations[0]['member_number']);

        $this->assertDatabaseHas('activities', [
            'club_id' => $club->id,
            'user_id' => $owner->id,
            'type' => 'club.legal_master_data.updated',
        ]);
        $activity = Activity::query()->where('club_id', $club->id)->where('type', 'club.legal_master_data.updated')->latest('id')->firstOrFail();
        $this->assertContains('tax_number', $activity->data['changed_fields']);
        $this->assertStringNotContainsString('123/456/78901', json_encode($activity->data));
    }

    public function test_member_cannot_update_or_receive_private_legal_master_data(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'country' => 'DE',
            'is_listed' => true,
            'verification_status' => 'verified',
            'registry_authority' => 'Amtsgericht Geheim',
            'registry_number' => 'VR-SECRET',
            'tax_number' => 'SECRET-TAX',
        ]);
        $club->users()->syncWithoutDetaching([$member->id => [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]]);
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.registry_authority')
            ->assertJsonMissingPath('data.tax_number')
            ->assertJsonMissingPath('data.profile.registry_authority')
            ->assertJsonMissingPath('data.profile.tax_number');

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'tax_number' => 'CHANGED',
        ]))->assertForbidden();

        $this->assertSame('SECRET-TAX', $club->fresh()->tax_number);
    }

    public function test_legal_master_data_validation_rejects_invalid_shapes_and_statuses(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'country' => 'DE']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'tax_status' => 'exempt_forever',
            'vat_id' => '12345',
            'federation_affiliations' => [[
                'name' => '',
                'valid_from' => '2026-12-31',
                'valid_until' => '2026-01-01',
            ]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tax_status',
                'vat_id',
                'federation_affiliations.0.name',
                'federation_affiliations.0.valid_until',
            ]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Testverein',
            'sport_type' => 'Fußball',
            'country' => 'DE',
            ...$overrides,
        ];
    }
}
