<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\User;
use App\Services\ClubContributionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubContributionComponentAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_contribution_rule_stores_accounting_metadata_and_snapshot(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/clubs/{$club->id}/membership/contribution-rules", [
            'name' => 'Aufnahme 2026',
            'valid_from' => '2026-01-01',
            'billing_interval' => 'once',
            'amount' => '19.99',
            'factor_key' => 'admission',
            'priority' => 20,
            'tax_account' => 'UST-19',
            'accounting_account' => 'SKR49-2110',
            'snapshot' => [
                'approved_by' => 'Mitgliederversammlung 2026',
                'version' => '2026.1',
            ],
        ])
            ->assertCreated();

        $this->assertContains('department', collect($response->json('data.contribution_rule_types'))->pluck('value')->all());

        $rule = ClubContributionRule::query()->where('name', 'Aufnahme 2026')->firstOrFail();

        $this->assertSame('admission', $rule->factor_key);
        $this->assertSame(20, $rule->priority);
        $this->assertSame('UST-19', $rule->tax_account);
        $this->assertSame('SKR49-2110', $rule->accounting_account);
        $this->assertSame('2026.1', $rule->snapshot['version']);
    }

    public function test_calculator_builds_component_snapshot_with_priority_and_cent_rounding(): void
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);

        $club->contributionRules()->createMany([
            ['name' => 'Grundbeitrag', 'valid_from' => '2026-01-01', 'billing_interval' => 'yearly', 'amount' => '100.05', 'factor_key' => 'base', 'priority' => 10, 'tax_account' => 'UST-7', 'accounting_account' => '4000', 'is_active' => true],
            ['name' => 'Abteilung Tennis', 'valid_from' => '2026-01-01', 'billing_interval' => 'yearly', 'amount' => '10.015', 'factor_key' => 'department', 'priority' => 20, 'tax_account' => 'UST-7', 'accounting_account' => '4100', 'is_active' => true],
            ['name' => 'Aufnahme', 'valid_from' => '2026-01-01', 'billing_interval' => 'once', 'amount' => '5.335', 'factor_key' => 'admission', 'priority' => 30, 'accounting_account' => '4200', 'is_active' => true],
            ['name' => 'Energieumlage', 'valid_from' => '2026-01-01', 'billing_interval' => 'yearly', 'amount' => '3.333', 'factor_key' => 'allocation', 'priority' => 40, 'accounting_account' => '4300', 'is_active' => true],
            ['name' => 'Leistungskader', 'valid_from' => '2026-01-01', 'billing_interval' => 'yearly', 'amount' => '7.777', 'factor_key' => 'service', 'priority' => 50, 'accounting_account' => '4400', 'is_active' => true],
            ['name' => 'Ehrenamt Rabatt', 'valid_from' => '2026-01-01', 'billing_interval' => 'yearly', 'amount' => 0, 'factor_key' => 'discount', 'factor_operator' => 'percent', 'factor_value' => '12.5', 'priority' => 60, 'is_active' => true],
        ]);

        $preview = app(ClubContributionCalculator::class)->resolve($club, $applicant, null, '2026-09-26');

        $this->assertSame('100.05', $preview['base_amount']);
        $this->assertSame('26.47', $preview['component_amount']);
        $this->assertSame('12.51', $preview['discount_amount']);
        $this->assertSame('114.01', $preview['amount']);
        $this->assertSame(['base', 'department', 'admission', 'allocation', 'service'], array_column($preview['components'], 'type'));
        $this->assertSame('4100', $preview['snapshot']['components'][1]['accounting_account']);
        $this->assertSame('2026-09-26', $preview['snapshot']['effective_on']);
    }
}
