<?php

namespace App\Support;

final class ClubContextSwitchContract
{
    public const VERSION = '2026-09-26.club-context-switch.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'selection_model' => [
                'active_context_is_explicit' => true,
                'global_current_club_is_not_trusted' => true,
                'actions_bind_to_route_or_payload_club_id' => true,
                'team_context_resolves_owning_club' => true,
                'personal_context_remains_separate' => true,
            ],
            'role_boundaries' => [
                'roles_are_evaluated_per_club' => true,
                'department_and_team_scopes_do_not_expand_to_other_clubs' => true,
                'explicit_denials_override_legacy_roles' => true,
                'club_switch_never_grants_navigation_modules' => true,
            ],
            'context_display' => [
                'club_name_required_in_workspace_cards' => true,
                'team_cards_include_club_id_or_club_name' => true,
                'global_search_results_include_context' => true,
                'dangerous_or_financial_actions_require_server_authorization' => true,
            ],
            'cross_club_action_guards' => [
                'foreign_club_ids_return_forbidden_or_not_found',
                'foreign_team_ids_are_revalidated_against_selected_club',
                'saved_view_filters_do_not_grant_access',
                'mobile_deep_links_reload_authoritative_context',
            ],
            'verified_surfaces' => [
                'web_club_cockpit',
                'web_settings_navigation_modules',
                'api_club_resource_capabilities',
                'api_global_search_context',
                'mobile_workspace_center',
                'mobile_club_and_team_deep_links',
            ],
            'decision' => 'local_contract_ready',
        ];
    }
}
