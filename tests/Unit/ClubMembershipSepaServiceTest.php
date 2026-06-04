<?php

namespace Tests\Unit;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClubMembershipSepaService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ClubMembershipSepaServiceTest extends TestCase
{
    public function test_debit_xml_contains_creditor_debtor_and_invoice_details(): void
    {
        $club = new Club([
            'name' => 'Airmius SV',
            'sepa_creditor_id' => 'DE98ZZZ09999999999',
            'sepa_account_holder' => 'Airmius SV e.V.',
            'sepa_iban' => 'DE02 1203 0000 0000 2020 51',
            'sepa_bic' => '',
        ]);
        $club->id = 42;

        $invoice = new Invoice([
            'user_id' => 7,
            'number' => 'AIR-42-0001',
            'title' => 'Mitgliedsbeitrag Juni',
            'amount' => 19.5,
        ]);
        $invoice->setRelation('user', new User([
            'name' => 'Mira Muster',
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
        $this->assertStringContainsString('<Nm>Mira Muster</Nm>', $xml);
        $this->assertStringContainsString('<IBAN>DE89370400440532013000</IBAN>', $xml);
    }
}
