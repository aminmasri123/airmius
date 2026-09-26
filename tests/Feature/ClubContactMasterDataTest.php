<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubContactMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_contacts_without_changing_existing_bank_details(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'country' => 'DE',
            'sepa_account_holder' => 'Airmius SC',
            'sepa_iban' => 'DE02120300000000202051',
            'sepa_bic' => 'BYLADEM1001',
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'contact_email' => ' INFO@AIRMIUS.DE ',
            'contact_phone' => ' +49 221 123456 ',
            'website_url' => 'https://www.airmius.de/verein',
            'contact_details_public' => true,
            'contact_persons' => [[
                'name' => '  Alex Beispiel ',
                'role' => ' Vorstand ',
                'email' => ' ALEX@AIRMIUS.DE ',
                'phone' => ' +49 221 555 ',
                'is_public' => true,
            ]],
        ]))->assertOk()
            ->assertJsonPath('data.contact_email', 'info@airmius.de')
            ->assertJsonPath('data.contact_persons.0.name', 'Alex Beispiel')
            ->assertJsonPath('data.contact_persons.0.email', 'alex@airmius.de');

        $club->refresh();
        $this->assertSame('+49 221 123456', $club->contact_phone);
        $this->assertSame('Vorstand', $club->contact_persons[0]['role']);
        $this->assertSame('DE02120300000000202051', $club->sepa_iban);
        $this->assertSame('BYLADEM1001', $club->sepa_bic);

        $activity = Activity::query()
            ->where('club_id', $club->id)
            ->where('type', 'club.contact_master_data.updated')
            ->latest('id')->firstOrFail();
        $this->assertContains('contact_persons', $activity->data['changed_fields']);
        $this->assertStringNotContainsString('alex@airmius.de', json_encode($activity->data));
    }

    public function test_contact_visibility_hides_private_data_and_private_contact_people(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'country' => 'DE',
            'is_listed' => true,
            'verification_status' => 'verified',
            'contact_email' => 'verein@example.test',
            'contact_phone' => '+49 123',
            'contact_details_public' => false,
            'contact_persons' => [
                ['name' => 'Öffentlich', 'role' => 'Presse', 'email' => 'public@example.test', 'phone' => null, 'is_public' => true],
                ['name' => 'Intern', 'role' => 'Kasse', 'email' => 'private@example.test', 'phone' => null, 'is_public' => false],
            ],
        ]);
        $club->users()->syncWithoutDetaching([$member->id => [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]]);
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.contact_email')
            ->assertJsonMissingPath('data.contact_persons')
            ->assertJsonMissingPath('data.profile.contact_email');

        $club->update(['contact_details_public' => true]);
        $response = $this->getJson("/api/v1/clubs/{$club->id}")->assertOk();
        $response->assertJsonPath('data.contact_email', 'verein@example.test')
            ->assertJsonPath('data.contact_persons.0.name', 'Öffentlich')
            ->assertJsonCount(1, 'data.contact_persons')
            ->assertJsonPath('data.profile.contact_persons.0.name', 'Öffentlich')
            ->assertJsonCount(1, 'data.profile.contact_persons');
        $this->assertStringNotContainsString('private@example.test', $response->getContent());

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'contact_email' => 'changed@example.test',
        ]))->assertForbidden();
    }

    public function test_contact_validation_rejects_invalid_values_and_more_than_twenty_people(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'country' => 'DE']);
        Sanctum::actingAs($owner);

        $people = array_fill(0, 21, [
            'name' => 'Kontakt',
            'role' => null,
            'email' => null,
            'phone' => null,
            'is_public' => false,
        ]);

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'contact_email' => 'keine-mail',
            'contact_phone' => 'call me maybe',
            'website_url' => 'javascript:alert(1)',
            'contact_persons' => $people,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contact_email',
                'contact_phone',
                'website_url',
                'contact_persons',
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
