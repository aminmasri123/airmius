<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubBrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_palette_letterhead_and_document_templates(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'country' => 'DE']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'brand_primary_color' => '#1d4ed8',
            'brand_secondary_color' => '#0f172a',
            'brand_accent_color' => '#f59e0b',
            'letterhead_settings' => [
                'show_logo' => true,
                'header' => '  Airmius Sportverein e. V. ',
                'address_line' => ' Musterweg 1 · 50667 Köln ',
                'footer' => ' Vorstand: Alex Beispiel ',
            ],
            'document_templates' => [[
                'name' => '  Standardbrief ',
                'type' => 'letter',
                'header' => ' Persönliche Mitteilung ',
                'footer' => ' Mit sportlichen Grüßen ',
                'is_default' => true,
            ]],
        ]))->assertOk()
            ->assertJsonPath('data.brand_primary_color', '#1D4ED8')
            ->assertJsonPath('data.letterhead_settings.header', 'Airmius Sportverein e. V.')
            ->assertJsonPath('data.document_templates.0.name', 'Standardbrief');

        $club->refresh();
        $this->assertSame('#0F172A', $club->brand_secondary_color);
        $this->assertSame('Musterweg 1 · 50667 Köln', $club->letterhead_settings['address_line']);
        $this->assertTrue($club->document_templates[0]['is_default']);

        $activity = Activity::query()->where('club_id', $club->id)
            ->where('type', 'club.branding.updated')->latest('id')->firstOrFail();
        $this->assertContains('document_templates', $activity->data['changed_fields']);
        $this->assertStringNotContainsString('Persönliche Mitteilung', json_encode($activity->data));
    }

    public function test_public_view_receives_palette_but_not_private_document_configuration(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create([
            'owner_id' => $owner->id,
            'country' => 'DE',
            'is_listed' => true,
            'verification_status' => 'verified',
            'brand_primary_color' => '#112233',
            'letterhead_settings' => ['show_logo' => true, 'header' => 'Intern'],
            'document_templates' => [['name' => 'Intern', 'type' => 'letter', 'header' => null, 'footer' => null, 'is_default' => true]],
        ]);
        $club->users()->syncWithoutDetaching([$member->id => [
            'role' => 'member', 'roles' => ['member'], 'membership_status' => 'active',
        ]]);
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/clubs/{$club->id}")
            ->assertOk()
            ->assertJsonPath('data.brand_primary_color', '#112233')
            ->assertJsonMissingPath('data.letterhead_settings')
            ->assertJsonMissingPath('data.document_templates')
            ->assertJsonMissingPath('data.profile.letterhead_settings');

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'brand_primary_color' => '#FFFFFF',
        ]))->assertForbidden();
    }

    public function test_branding_validation_rejects_invalid_colors_and_ambiguous_defaults(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'country' => 'DE']);
        Sanctum::actingAs($owner);

        $templates = [
            ['name' => 'Brief A', 'type' => 'letter', 'header' => null, 'footer' => null, 'is_default' => true],
            ['name' => 'Brief B', 'type' => 'letter', 'header' => null, 'footer' => null, 'is_default' => true],
        ];

        $this->putJson("/api/v1/clubs/{$club->id}", $this->payload([
            'brand_primary_color' => 'red',
            'letterhead_settings' => ['show_logo' => true, 'footer' => str_repeat('x', 501)],
            'document_templates' => $templates,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'brand_primary_color',
                'letterhead_settings.footer',
                'document_templates',
            ]);
    }

    public function test_logo_upload_is_audited_without_copying_storage_path_into_audit_data(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id, 'country' => 'DE']);
        Sanctum::actingAs($owner);

        $this->post("/api/v1/clubs/{$club->id}/images", [
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        ], ['Accept' => 'application/json'])->assertOk();

        $activity = Activity::query()->where('club_id', $club->id)
            ->where('type', 'club.branding.updated')->latest('id')->firstOrFail();
        $this->assertSame(['logo'], $activity->data['changed_fields']);
        $this->assertStringNotContainsString('clubs/', json_encode($activity->data));
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Testverein', 'sport_type' => 'Fußball', 'country' => 'DE', ...$overrides];
    }
}
