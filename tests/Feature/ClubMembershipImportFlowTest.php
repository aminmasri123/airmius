<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMembershipImportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_import_reports_invalid_rows_skips_duplicates_and_normalizes_values(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();

        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $starter->id,
                'status' => 'active',
            ],
        );

        $csv = implode("\n", [
            'Name;E-Mail;Mitgliedschaft;Beitrag;Intervall;Naechste_Rechnung;SEPA_Aktiv',
            'Mira Muster;mira@example.org;Prüfung;1.234,56;halbjährlich;2026-06-01;ja',
            'Mira Duplicate;mira@example.org;aktiv;99,99;monthly;2026-07-01;nein',
            'Ohne Mail;ungueltig;aktiv;12,50;monthly;;',
            'Pause Person;pause@example.org;pausiert;12,50;monatlich;2026-08-01;yes',
        ]);
        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $this->actingAs($owner)
            ->post(route('auth.club-memberships.email-members.import', $club), [
                'file' => $file,
                'send_invitation' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import fertig: 2 gespeichert, 0 verknüpft, 0 eingeladen, 2 übersprungen.')
            ->assertSessionHas('import_report', function (array $report) {
                $this->assertSame(2, $report['total_errors']);
                $this->assertSame(3, $report['errors'][0]['row']);
                $this->assertSame('mira@example.org', $report['errors'][0]['email']);
                $this->assertSame(4, $report['errors'][1]['row']);
                $this->assertSame('ungueltig', $report['errors'][1]['email']);

                return true;
            });

        $mira = ClubExternalMember::query()->where('email', 'mira@example.org')->firstOrFail();
        $pause = ClubExternalMember::query()->where('email', 'pause@example.org')->firstOrFail();

        $this->assertSame('Mira Muster', $mira->name);
        $this->assertSame('pending', $mira->membership_status);
        $this->assertSame('1234.56', $mira->contribution_amount);
        $this->assertSame('semi_yearly', $mira->contribution_interval);
        $this->assertSame('2026-06-01', $mira->contribution_next_invoice_on->toDateString());
        $this->assertTrue($mira->sepa_mandate_active);

        $this->assertSame('paused', $pause->membership_status);
        $this->assertSame('monthly', $pause->contribution_interval);
    }

    public function test_api_membership_import_preview_detects_duplicates_without_writing_members(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            [
                'subscription_plan_id' => $starter->id,
                'status' => 'active',
            ],
        );

        $csv = implode("\n", [
            'Name;E-Mail;Mitgliedschaft;Beitrag;Intervall',
            'Neue Person;new@example.org;aktiv;12,50;monatlich',
            'Doppelt;new@example.org;aktiv;10,00;monatlich',
            'Ohne Mail;ungueltig;aktiv;9,00;monatlich',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
            'file' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.total_rows', 3)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.error_count', 2)
            ->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.rows.0.email', 'new@example.org')
            ->assertJsonPath('data.rows.0.action', 'create_external_member')
            ->assertJsonPath('data.errors.0.row', 3)
            ->assertJsonPath('data.errors.1.row', 4);

        $this->assertDatabaseCount('club_external_members', 0);
    }

    public function test_api_membership_import_preview_accepts_manager_column_mapping(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $starter = SubscriptionPlan::query()->where('slug', 'starter')->firstOrFail();
        ClubSubscription::query()->updateOrCreate(
            ['club_id' => $club->id],
            ['subscription_plan_id' => $starter->id, 'status' => 'active'],
        );

        $csv = implode("\n", [
            'Person;Kontaktadresse;Status;Familiengruppe;Jahresbeitrag',
            'Ada Beispiel;ada@example.org;aktiv;family-7;120,00',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
            'file' => UploadedFile::fake()->createWithContent('mapped.csv', $csv),
            'mapping' => json_encode([
                'name' => 0,
                'email' => 1,
                'membership_status' => 2,
                'family_group_key' => 3,
                'contribution_amount' => 4,
            ], JSON_THROW_ON_ERROR),
        ])
            ->assertOk()
            ->assertJsonPath('data.needs_mapping', false)
            ->assertJsonPath('data.mapping.email', 1)
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.rows.0.name', 'Ada Beispiel')
            ->assertJsonPath('data.rows.0.email', 'ada@example.org')
            ->assertJsonPath('data.rows.0.family_group_key', 'family-7');

        $this->assertDatabaseCount('club_external_members', 0);
    }

    public function test_downloaded_excel_template_places_helpful_dropdowns_in_the_correct_columns(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $response = $this->get('/api/v1/club-members/import-template', [
            'Accept' => 'application/json',
        ])->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'airmius-template-test-');
        file_put_contents($path, $response->streamedContent());

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            $this->assertIsString($sheet);
            $this->assertStringContainsString('sqref="D5:D1000"', $sheet);
            $this->assertStringContainsString('active,non_member,pending,paused,former', $sheet);
            $this->assertStringContainsString('sqref="H5:H1000"', $sheet);
            $this->assertStringContainsString('four_monthly,semi_yearly', $sheet);
            $this->assertStringContainsString('sqref="N5:N1000"', $sheet);
            $this->assertStringContainsString('<mergeCell ref="A1:Q1"/>', $sheet);
        } finally {
            @unlink($path);
        }
    }
}
