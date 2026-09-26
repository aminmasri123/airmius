<?php

namespace Tests\Feature;

use App\Support\ClubSportWorkforceReadinessCatalog;
use Tests\TestCase;

class ClubSportWorkforceReadinessCatalogTest extends TestCase
{
    public function test_meta_exposes_training_competition_learning_workforce_and_volunteer_contracts(): void
    {
        $catalog = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_sport_workforce_readiness');

        $this->assertSame(ClubSportWorkforceReadinessCatalog::VERSION, $catalog['version']);
        $this->assertSame('local_contract_ready_external_gates_open', $catalog['decision']);

        $this->assertContains('strict_opt_in', $catalog['training_development']['public_release']);
        $this->assertContains('foreign_club', $catalog['training_development']['regression_matrix']);

        $this->assertContains('editorial_review', $catalog['competition_results']['match_report_publication']);
        $this->assertContains('correction_history', $catalog['competition_results']['match_report_publication']);
        $this->assertContains('invoice_reference', $catalog['competition_results']['finance_link']);

        $this->assertContains('public_booking', $catalog['learning_offers']['end_to_end_channels']);
        $this->assertContains('guest_access', $catalog['learning_offers']['end_to_end_channels']);
        $this->assertContains('waitlist', $catalog['learning_offers']['regression_matrix']);

        $this->assertContains('honorarium', $catalog['workforce']['compensation_processes']);
        $this->assertContains('travel_costs', $catalog['workforce']['compensation_processes']);
        $this->assertContains('minimal_personal_data', $catalog['workforce']['payroll_export']);

        $this->assertContains('volunteer_certificate', $catalog['volunteers']['recognition']);
        $this->assertContains('thank_you_letter', $catalog['volunteers']['recognition']);
        $this->assertContains('notifications', $catalog['volunteers']['regression_matrix']);
    }
}
