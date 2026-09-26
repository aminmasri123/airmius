<?php

namespace Tests\Feature;

use App\Support\ClubContextSwitchContract;
use Tests\TestCase;

class ClubContextSwitchContractTest extends TestCase
{
    public function test_meta_exposes_controlled_club_context_switch_contract(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_context_switch');

        $this->assertSame(ClubContextSwitchContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready', $contract['decision']);
        $this->assertTrue($contract['selection_model']['active_context_is_explicit']);
        $this->assertTrue($contract['selection_model']['global_current_club_is_not_trusted']);
        $this->assertTrue($contract['selection_model']['actions_bind_to_route_or_payload_club_id']);
        $this->assertTrue($contract['selection_model']['team_context_resolves_owning_club']);

        $this->assertTrue($contract['role_boundaries']['roles_are_evaluated_per_club']);
        $this->assertTrue($contract['role_boundaries']['club_switch_never_grants_navigation_modules']);
        $this->assertTrue($contract['role_boundaries']['explicit_denials_override_legacy_roles']);

        $this->assertTrue($contract['context_display']['club_name_required_in_workspace_cards']);
        $this->assertTrue($contract['context_display']['global_search_results_include_context']);
        $this->assertContains('foreign_club_ids_return_forbidden_or_not_found', $contract['cross_club_action_guards']);
        $this->assertContains('foreign_team_ids_are_revalidated_against_selected_club', $contract['cross_club_action_guards']);
        $this->assertContains('mobile_workspace_center', $contract['verified_surfaces']);
        $this->assertContains('api_global_search_context', $contract['verified_surfaces']);
    }
}
