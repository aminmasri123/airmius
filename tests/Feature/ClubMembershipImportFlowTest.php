<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\ClubSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
