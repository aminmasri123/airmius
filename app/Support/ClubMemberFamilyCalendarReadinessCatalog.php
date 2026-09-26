<?php

namespace App\Support;

final class ClubMemberFamilyCalendarReadinessCatalog
{
    public const VERSION = '2026-09-26.club-member-family-calendar-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'former_membership' => [
                'retention_deletion_gate' => [
                    'legal_matrix_required',
                    'domain_acceptance_required',
                    'controlled_staging_copy_required',
                    'production_deletion_disabled_until_go',
                ],
                'external_completion' => 'pending',
            ],
            'family_youth' => [
                'relationships' => [
                    'club_bound_validation',
                    'sensitive_visibility',
                    'required_contact_rules',
                    'deletion_history_by_type',
                    'web_api_app_capture',
                    'legacy_payer_compatibility',
                ],
                'child_permissions' => [
                    'profile',
                    'events',
                    'attendance',
                    'bookings',
                    'documents',
                    'payments',
                    'pickup',
                    'consents',
                    'templates',
                    'audit_notifications',
                ],
                'consents' => [
                    'versioned_definition',
                    'purpose_bound',
                    'medium_bound',
                    'event_bound',
                    'proof',
                    'withdrawal_history',
                    'server_side_enforcement',
                ],
                'coming_of_age' => [
                    'configurable_cutoff',
                    'read_only_preview',
                    'adult_decision',
                    'guardian_right_revocation',
                    'documented_exception',
                ],
                'youth_structure' => [
                    'age_rules',
                    'youth_groups',
                    'leaders',
                    'visibility',
                    'youth_rep_term',
                    'limited_governance_rights',
                ],
            ],
            'member_portal_card' => [
                'notification_visibility' => [
                    'web_api_app_preferences',
                    'mandatory_messages',
                    'optional_channels',
                    'visibility_scope',
                    'delivery_reason',
                ],
                'digital_card' => [
                    'short_lived_signed_qr',
                    'revocation',
                    'minimal_verification_result',
                    'abuse_protection',
                    'audit',
                ],
                'assisted_admin' => [
                    'identity_check',
                    'representation',
                    'print_or_mail',
                    'full_audit',
                    'no_smartphone_path',
                ],
                'regression_matrix' => [
                    'member',
                    'minor',
                    'guardian',
                    'former_member',
                    'multi_club',
                    'locked_account',
                    'browser_real_device_gate',
                ],
            ],
            'teams_groups' => [
                'season_rollover' => [
                    'preview',
                    'copy_rules',
                    'conflict_check',
                    'selective_takeover',
                    'atomic_rollback',
                ],
                'team_workspace' => [
                    'documents',
                    'messages',
                    'tasks',
                    'central_permissions',
                    'web_api_app_parity',
                    'regression',
                ],
            ],
            'calendar_attendance' => [
                'conflict_checker' => [
                    'trainers',
                    'participants',
                    'rooms',
                    'fields',
                    'resources',
                    'warning_or_hard_block',
                ],
                'change_notifications' => [
                    'targeted_recipients',
                    'cancellation',
                    'deduplication',
                    'channel_preferences',
                    'delivery_status',
                ],
                'personal_calendar' => [
                    'personal_view',
                    'secure_ical_subscription',
                    'printable_overview',
                    'web_api_app',
                    'timezone_dst_series_regression',
                ],
            ],
            'decision' => 'local_contract_ready_external_gates_open',
        ];
    }
}
