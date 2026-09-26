<?php

namespace Tests\Feature;

use App\Support\AdminTwoFactor;
use App\Support\ClubPermissions;
use App\Support\ClubSupportAccessContract;
use Tests\TestCase;

class ClubSupportAccessContractTest extends TestCase
{
    public function test_meta_exposes_explicit_club_support_access_contract(): void
    {
        $contract = $this->getJson('/api/v1/meta')
            ->assertOk()
            ->json('data.catalogs.club_support_access');

        $this->assertSame(ClubSupportAccessContract::VERSION, $contract['version']);
        $this->assertSame('local_contract_ready', $contract['decision']);

        $this->assertTrue($contract['grant_model']['requires_explicit_club_approval']);
        $this->assertContains(ClubPermissions::SUPPORT_EDIT, $contract['grant_model']['approval_actor_permissions']);
        $this->assertSame('SupportAccessService::operatorScope', $contract['grant_model']['support_operator_source']);
        $this->assertSame('support.tickets', $contract['grant_model']['global_platform_support_requires_permission']);
        $this->assertTrue($contract['grant_model']['no_implicit_access_from_membership']);

        $this->assertTrue($contract['purpose_and_scope']['purpose_required']);
        $this->assertTrue($contract['purpose_and_scope']['ticket_reference_required']);
        $this->assertContains('safety_case', $contract['purpose_and_scope']['allowed_purposes']);
        $this->assertContains('department', $contract['purpose_and_scope']['scope_levels']);
        $this->assertTrue($contract['purpose_and_scope']['foreign_department_or_team_rejected']);

        $this->assertSame(60, $contract['lifecycle']['default_duration_minutes']);
        $this->assertSame(240, $contract['lifecycle']['maximum_duration_minutes']);
        $this->assertTrue($contract['lifecycle']['revocable_by_club']);
        $this->assertTrue($contract['lifecycle']['renewal_requires_new_approval']);

        $this->assertTrue($contract['step_up']['required_for_support_operator']);
        $this->assertSame('EnsurePlatformAdminTwoFactor', $contract['step_up']['api_source']);
        $this->assertSame(AdminTwoFactor::STEP_UP_TOKEN_ABILITY, $contract['step_up']['token_ability']);
        $this->assertTrue($contract['step_up']['fresh_step_up_required_for_mutation']);

        $this->assertTrue($contract['session_banner']['visible_to_support_operator']);
        $this->assertTrue($contract['session_banner']['visible_to_club_admins']);
        $this->assertContains('revoke_action', $contract['session_banner']['fields']);
        $this->assertTrue($contract['session_banner']['no_silent_support_sessions']);

        $this->assertTrue($contract['audit']['append_only_required']);
        $this->assertSame('SupportTicketConfidentialAudit', $contract['audit']['hash_chain_pattern']);
        $this->assertContains('session_started', $contract['audit']['events']);
        $this->assertContains('previous_hash', $contract['audit']['metadata']);
        $this->assertTrue($contract['audit']['audit_visible_to_authorized_club_reviewers']);

        $this->assertTrue($contract['privacy_guards']['least_privilege_scope']);
        $this->assertTrue($contract['privacy_guards']['anonymous_safety_report_identity_redacted']);
        $this->assertTrue($contract['privacy_guards']['support_notes_are_internal_only']);
    }
}
