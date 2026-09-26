<?php

namespace App\Support;

final class ClubContinuityReadinessCatalog
{
    public const VERSION = '2026-09-26.club-continuity-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'budget_grants' => [
                'eligible_costs' => [
                    'project_reference_required',
                    'receipt_reference_required',
                    'funding_program_reference_required',
                    'own_contribution_marker',
                    'deadline_check',
                ],
                'duplicate_use_controls' => [
                    'receipt_reuse_warning',
                    'cost_position_reuse_warning',
                    'funding_deadline_violation_warning',
                    'immutable_finance_source',
                ],
                'reporting' => [
                    'verwendungsnachweis',
                    'activity_report',
                    'source_list',
                    'export_manifest',
                    'web_api_app_regression',
                    'finance_reconciliation',
                ],
            ],
            'sponsors_donations' => [
                'donation_receipts' => [
                    'configured_prerequisites',
                    'four_eyes_approval',
                    'unique_number',
                    'cancellation_history',
                    'sponsoring_membership_fee_separation',
                ],
                'supporter_reporting' => [
                    'progress_update',
                    'thank_you_letter',
                    'supporter_report',
                    'consent_check',
                    'visibility_check',
                ],
                'external_gates' => ['legal_tax_acceptance'],
            ],
            'communications' => [
                'quiet_hours_preferences' => [
                    'personal_channel_preference',
                    'quiet_hours',
                    'mandatory_message_exception',
                    'safety_message_exception',
                    'delivery_reason_visible',
                ],
                'minor_safety' => [
                    'guardian_consent_required',
                    'protection_concept_rules',
                    'team_context_validation',
                    'moderation_path',
                    'privacy_minimization',
                ],
                'regression_matrix' => ['web', 'api', 'app', 'delivery', 'privacy'],
            ],
            'public_relations' => [
                'sponsor_presentation' => [
                    'approved_contract_benefits_only',
                    'no_internal_contacts',
                    'no_payment_data',
                    'visibility_window',
                ],
                'media_library' => [
                    'copyright_owner',
                    'depicted_persons',
                    'consent_purpose',
                    'consent_expiry',
                    'publication_review',
                ],
                'public_internal_matrix' => [
                    'seo',
                    'accessibility',
                    'cache_policy',
                    'web_api_app_parity',
                    'strict_public_projection',
                ],
            ],
            'documents_knowledge' => [
                'signature_process' => [
                    'provider_neutral',
                    'signers',
                    'sequence',
                    'status',
                    'evidence',
                    'cancellation',
                ],
                'internal_handbook' => [
                    'versioning',
                    'approval',
                    'search',
                    'faq',
                    'role_limited_ai_source',
                    'no_answer_without_source',
                ],
                'handover_package' => [
                    'responsibility_change',
                    'time_limited_access',
                    'checklist',
                    'completion_review',
                    'audit',
                ],
                'regression_matrix' => [
                    'files',
                    'malware',
                    'downloads',
                    'versions',
                    'roles',
                    'privacy',
                    'inventory_migration',
                ],
            ],
            'governance_regression' => [
                'automated_matrix' => [
                    'roles',
                    'eligibility_cutoff',
                    'concurrency',
                    'confidentiality',
                    'web_api_app_parity',
                ],
                'external_acceptance' => 'pending',
            ],
            'decision' => 'local_contract_ready_external_gates_open',
        ];
    }
}
