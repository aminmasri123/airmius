<?php

namespace Tests\Unit;

use App\Services\ClubMembershipImportService;
use PHPUnit\Framework\TestCase;

class ClubMembershipImportServiceTest extends TestCase
{
    public function test_csv_rows_are_read_and_member_data_is_normalized(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-members-test-');
        file_put_contents($path, implode("\n", [
            'Name;E-Mail;Mitgliedschaft;Lizenznummer;Lizenz_Gueltig_Bis;Beitrag;Intervall;Naechste_Rechnung;IBAN;BIC;SEPA_Aktiv',
            'Mira Muster;mira@example.org;Prüfung;LIC-2026;2026-12-31;1.234,56;monatlich;2026-06-01;DE02 1203 0000 0000 2020 51;BYLADEM1001;ja',
            'Ohne Mail;ungueltig;aktiv;12,50;monthly;;;;',
        ]));

        try {
            $service = new ClubMembershipImportService;
            $rows = $service->readRows($path, 'csv');

            $this->assertCount(2, $rows);

            $member = $service->memberDataFromRow($rows[0]);

            $this->assertSame('mira@example.org', $member['email']);
            $this->assertSame('pending', $member['membership_status']);
            $this->assertSame('LIC-2026', $member['athlete_license_number']);
            $this->assertSame('2026-12-31', $member['athlete_license_valid_until']);
            $this->assertSame(1234.56, $member['contribution_amount']);
            $this->assertSame('monthly', $member['contribution_interval']);
            $this->assertSame('2026-06-01', $member['contribution_next_invoice_on']);
            $this->assertSame('DE02120300000000202051', $member['sepa_iban']);
            $this->assertSame('BYLADEM1001', $member['sepa_bic']);
            $this->assertTrue($member['sepa_mandate_active']);
            $this->assertNull($service->memberDataFromRow($rows[1]));
        } finally {
            @unlink($path);
        }
    }

    public function test_excel_template_keeps_dropdowns_aligned_after_license_validity_column(): void
    {
        $path = (new ClubMembershipImportService)->buildTemplate();

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            $this->assertIsString($sheet);
            $this->assertStringContainsString('Lizenz_Gueltig_Bis', $sheet);
            $this->assertStringContainsString('sqref="H5:H1000"', $sheet);
            $this->assertStringContainsString('sqref="N5:N1000"', $sheet);
            $this->assertStringContainsString('<mergeCell ref="A1:Q1"/>', $sheet);
        } finally {
            @unlink($path);
        }
    }
}
