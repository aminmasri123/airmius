<?php

namespace Tests\Unit;

use App\Support\ClubMembershipInput;
use PHPUnit\Framework\TestCase;

class ClubMembershipInputTest extends TestCase
{
    public function test_next_invoice_date_is_inferred_from_joined_on_and_interval(): void
    {
        $cases = [
            'monthly' => '2026-05-01',
            'quarterly' => '2026-04-01',
            'four_monthly' => '2026-05-01',
            'semi_yearly' => '2026-01-01',
            'yearly' => '2026-01-01',
            'once' => '2026-05-17',
        ];

        foreach ($cases as $interval => $expected) {
            $this->assertSame($expected, ClubMembershipInput::normalizedNextInvoiceDate([
                'contribution_amount' => 36,
                'contribution_interval' => $interval,
                'joined_on' => '2026-05-17',
            ]));
        }
    }

    public function test_explicit_next_invoice_date_wins(): void
    {
        $this->assertSame('2026-08-15', ClubMembershipInput::normalizedNextInvoiceDate([
            'contribution_amount' => 36,
            'contribution_interval' => 'yearly',
            'joined_on' => '2026-05-17',
            'contribution_next_invoice_on' => '2026-08-15',
        ]));
    }
}
