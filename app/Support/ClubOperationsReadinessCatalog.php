<?php

namespace App\Support;

final class ClubOperationsReadinessCatalog
{
    public const VERSION = '2026-09-26.club-operations-readiness.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'governance' => [
                'minutes_lifecycle' => [
                    'draft',
                    'review',
                    'approved',
                    'distributed',
                    'final_immutable',
                ],
                'minutes_access' => ['meeting_recipients', 'governance_managers', 'auditors_by_permission'],
                'decision_handover' => [
                    'creates_work_task',
                    'responsible_user_required',
                    'deadline_required',
                    'handover_reference_required',
                    'term_and_reelection_context',
                ],
                'regression_matrix' => [
                    'role_scope',
                    'eligibility_cutoff',
                    'conflict_of_interest',
                    'confidentiality',
                    'web_api_app_parity',
                ],
                'external_acceptance' => 'pending',
            ],
            'events_travel' => [
                'audit_types' => ['festival', 'excursion', 'tournament', 'anniversary', 'travel'],
                'registration' => [
                    'capacity_limit',
                    'waitlist',
                    'ticket_reference',
                    'qr_check_in',
                    'deduplicated_gate_list',
                ],
                'logistics' => [
                    'helpers',
                    'setup_teardown',
                    'providers',
                    'tasks',
                    'carpool',
                    'bus',
                    'accommodation',
                    'rooms',
                    'catering',
                ],
                'minor_and_emergency' => [
                    'guardian_consent_check',
                    'emergency_contact_minimized',
                    'deadline_tracking',
                    'travel_documents',
                ],
                'finance' => [
                    'person_team_club_cost_split',
                    'cancellation',
                    'replacement',
                    'refund_workflow',
                ],
                'external_gates' => ['real_device_qr_gate', 'finance_acceptance'],
            ],
            'teamwear' => [
                'payment_and_fulfillment' => [
                    'shop_checkout_or_invoice_reference',
                    'payment_status',
                    'partial_delivery',
                    'goods_receipt',
                    'issue_to_member',
                    'pickup_confirmation',
                    'inventory_reference',
                ],
                'lifecycle' => [
                    'notification',
                    'shortage',
                    'complaint',
                    'replacement',
                    'refund',
                    'audited_status_history',
                ],
                'regression_matrix' => ['web_api_app', 'variants', 'concurrency', 'payment', 'roles'],
            ],
            'clubhouse_commerce' => [
                'bookable_resources' => [
                    'rooms',
                    'hospitality_areas',
                    'combined_services',
                    'price_rules',
                    'booking_rules',
                ],
                'staffing' => [
                    'bar',
                    'kitchen',
                    'cleaning',
                    'service_hours',
                    'qualification_check',
                ],
                'cash_register_adapter' => [
                    'provider_neutral',
                    'daily_close',
                    'handover',
                    'difference_record',
                    'export',
                    'offline_sync_boundary',
                ],
                'procurement' => ['suppliers', 'recurring_order_suggestions', 'accounting_reference'],
                'external_provider_staging' => 'pending',
            ],
            'safeguarding' => [
                'concept' => [
                    'rules',
                    'contact_persons',
                    'versions',
                    'public_visibility',
                    'internal_visibility',
                ],
                'confidential_channel' => [
                    'minimal_required_data',
                    'optional_anonymity',
                    'secure_upload_required',
                    'abuse_protection',
                    'tenant_scope_validation',
                ],
                'training_and_evidence' => [
                    'review_status',
                    'review_due_at',
                    'avoid_document_copies_by_default',
                    'evidence_reference_only',
                ],
                'accident_and_insurance' => [
                    'accident_report',
                    'necessary_information_only',
                    'attachments',
                    'emergency_access',
                    'insurance_contract',
                    'coverage',
                    'reporting_deadline',
                    'claim_report',
                ],
                'assignment_rules' => [
                    'independent_case_assignment',
                    'complaint_against_member_trainer_official',
                    'substitution',
                    'escalation',
                    'conflict_user_exclusion',
                ],
                'external_privacy_and_domain_acceptance' => 'pending',
            ],
            'decision' => 'local_contract_ready_external_gates_open',
        ];
    }
}
