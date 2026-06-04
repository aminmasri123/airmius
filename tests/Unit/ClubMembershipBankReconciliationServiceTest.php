<?php

namespace Tests\Unit;

use App\Services\ClubMembershipBankReconciliationService;
use PHPUnit\Framework\TestCase;

class ClubMembershipBankReconciliationServiceTest extends TestCase
{
    public function test_bank_transaction_rows_are_read_and_normalized(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-bank-test-');
        file_put_contents($path, implode("\n", [
            'Datum;Betrag;Waehrung;Auftraggeber;IBAN;Verwendungszweck',
            '2026-06-03;1.234,56;EUR;Mira Muster;DE89 3704 0044 0532 0130 00;AIR-42-0001 Mitgliedsbeitrag',
        ]));

        try {
            $service = new ClubMembershipBankReconciliationService();
            $rows = $service->readRows($path);

            $this->assertCount(1, $rows);

            $transaction = $service->transactionFromRow($rows[0]);

            $this->assertSame('2026-06-03', $transaction['booking_date']);
            $this->assertSame(1234.56, $transaction['amount']);
            $this->assertSame('EUR', $transaction['currency']);
            $this->assertSame('Mira Muster', $transaction['debtor_name']);
            $this->assertSame('DE89370400440532013000', $transaction['debtor_iban']);
            $this->assertSame('AIR-42-0001 Mitgliedsbeitrag', $transaction['purpose']);
            $this->assertNotEmpty($transaction['transaction_hash']);
        } finally {
            @unlink($path);
        }
    }
}
