<?php

namespace Tests\Unit;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClubMembershipSepaService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ClubMembershipSepaServiceTest extends TestCase
{
    public function test_debit_xml_contains_creditor_debtor_and_invoice_details(): void
    {
        $club = new Club([
            'name' => 'Airmius SV',
            'sepa_creditor_id' => 'DE98ZZZ09999999999',
            'sepa_account_holder' => 'Airmius SV e.V.',
            'sepa_bic' => '',
        ]);
        $club->setRawAttributes(array_merge($club->getAttributes(), [
            'sepa_iban' => 'DE02 1203 0000 0000 2020 51',
        ]));
        $club->id = 42;

        $invoice = new Invoice([
            'user_id' => 7,
            'number' => 'AIR-42-0001',
            'title' => 'Mitgliedsbeitrag Juni',
            'amount' => 19.5,
        ]);
        $invoice->setRelation('user', new User([
            'name' => 'Mira Müller & Söhne',
            'email' => 'mira@example.org',
        ]));

        $memberships = new Collection([
            7 => (object) [
                'sepa_iban' => 'DE89 3704 0044 0532 0130 00',
                'sepa_bic' => 'COBADEFFXXX',
                'sepa_mandate_reference' => 'MANDAT-7',
                'sepa_mandate_signed_on' => '2026-05-01',
            ],
        ]);

        $xml = (new ClubMembershipSepaService())->buildDebitXml($club, new Collection([$invoice]), $memberships);

        $this->assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);
        $this->assertStringContainsString('<CtrlSum>19.50</CtrlSum>', $xml);
        $this->assertStringContainsString('<Nm>Airmius SV e.V.</Nm>', $xml);
        $this->assertStringContainsString('<IBAN>DE02120300000000202051</IBAN>', $xml);
        $this->assertStringContainsString('<Id>DE98ZZZ09999999999</Id>', $xml);
        $this->assertStringContainsString('<EndToEndId>AIR-42-0001</EndToEndId>', $xml);
        $this->assertStringContainsString('<MndtId>MANDAT-7</MndtId>', $xml);
        $this->assertStringContainsString('<Nm>Mira Müller &amp; Söhne</Nm>', $xml);
        $this->assertStringContainsString('<IBAN>DE89370400440532013000</IBAN>', $xml);
        $document = simplexml_load_string($xml);
        $this->assertNotFalse($document);
        $this->assertSame('19.50', (string) $document->CstmrDrctDbtInitn->PmtInf->DrctDbtTxInf->InstdAmt);

        $memberships->get(7)->sepa_iban = 'DE00370400440532013000';
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new ClubMembershipSepaService())->buildDebitXml($club, collect([$invoice]), $memberships);
    }

    public function test_foreign_club_invoice_is_rejected(): void
    {
        $club = new Club(['name' => 'Own club']);
        $club->id = 42;
        $invoice = new Invoice(['club_id' => 43, 'user_id' => 7, 'amount' => 10]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new ClubMembershipSepaService())->buildDebitXml($club, collect([$invoice]), collect([7 => (object) []]));
    }

    public function test_nonpositive_debit_is_rejected(): void
    {
        $club = new Club(['name' => 'Own club']);
        $club->id = 42;
        $invoice = new Invoice(['club_id' => 42, 'user_id' => 7, 'amount' => 0]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new ClubMembershipSepaService())->buildDebitXml($club, collect([$invoice]), collect([7 => (object) []]));
    }
}
