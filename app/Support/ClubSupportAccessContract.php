<?php

namespace App\Support;

final class ClubSupportAccessContract
{
    public const VERSION = '2026-09-26.club-support-access.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'grant_model' => [
                'requires_explicit_club_approval' => true,
                'approval_actor_permissions' => [
                    ClubPermissions::SUPPORT_EDIT,
                    ClubPermissions::SUPPORT_ASSIGN,
                    ClubPermissions::SUPPORT_RESOLVE,
                ],
                'support_operator_source' => 'SupportAccessService::operatorScope',
                'club_scope_source' => 'SupportAccessService::supportClubs',
                'global_platform_support_requires_permission' => 'support.tickets',
                'no_implicit_access_from_membership' => true,
            ],
            'purpose_and_scope' => [
                'purpose_required' => true,
                'ticket_reference_required' => true,
                'allowed_purposes' => [
                    'technical_support',
                    'billing_support',
                    'privacy_request',
                    'safety_case',
                    'migration_assistance',
                ],
                'scope_levels' => ['club', 'department', 'team'],
                'scope_validated_by' => 'SupportAccessService::requesterContext',
                'foreign_department_or_team_rejected' => true,
                'read_only_by_default' => true,
            ],
            'lifecycle' => [
                'default_duration_minutes' => 60,
                'maximum_duration_minutes' => 240,
                'requires_expires_at' => true,
                'revocable_by_club' => true,
                'auto_expires_without_background_privileges' => true,
                'renewal_requires_new_approval' => true,
                'closed_ticket_revokes_access' => true,
            ],
            'step_up' => [
                'required_for_support_operator' => true,
                'web_source' => 'HardenAdminArea',
                'api_source' => 'EnsurePlatformAdminTwoFactor',
                'token_ability' => AdminTwoFactor::STEP_UP_TOKEN_ABILITY,
                'confirmed_two_factor_required' => true,
                'fresh_step_up_required_for_mutation' => true,
            ],
            'session_banner' => [
                'visible_to_support_operator' => true,
                'visible_to_club_admins' => true,
                'fields' => [
                    'club_name',
                    'purpose',
                    'scope_level',
                    'ticket_id',
                    'operator_name',
                    'expires_at',
                    'revoke_action',
                ],
                'banner_required_on_every_support_impersonation_surface' => true,
                'no_silent_support_sessions' => true,
            ],
            'audit' => [
                'append_only_required' => true,
                'hash_chain_pattern' => 'SupportTicketConfidentialAudit',
                'events' => [
                    'requested',
                    'approved',
                    'step_up_completed',
                    'session_started',
                    'data_viewed',
                    'mutation_attempted',
                    'revoked',
                    'expired',
                ],
                'metadata' => [
                    'club_id',
                    'department_id',
                    'team_id',
                    'ticket_id',
                    'purpose',
                    'operator_id',
                    'approved_by_user_id',
                    'ip_hash',
                    'user_agent_hash',
                    'previous_hash',
                ],
                'audited_sensitive_cases' => true,
                'audit_visible_to_authorized_club_reviewers' => true,
            ],
            'privacy_guards' => [
                'least_privilege_scope' => true,
                'data_export_disabled_in_support_session' => true,
                'credentials_and_secrets_never_visible' => true,
                'anonymous_safety_report_identity_redacted' => true,
                'conflict_users_block_assignment' => true,
                'support_notes_are_internal_only' => true,
            ],
            'decision' => 'local_contract_ready',
        ];
    }
}
