<?php

namespace App\Support;

final class ClubPilotAcceptanceRegistry
{
    public const CONTRACT = 'club-pilot.v1';

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'cohort' => [
                'minimum_clubs' => 3,
                'maximum_clubs' => 5,
                'duration_weeks' => ['minimum' => 6, 'maximum' => 8],
                'selection_criteria' => [
                    'named_club_owner_and_backup_admin',
                    'verified_or_verification_scheduled',
                    'representative_member_import_available',
                    'trainer_and_member_test_group_available',
                    'support_and_rollback_contact_confirmed',
                    'data_processing_terms_confirmed',
                ],
                'evidence_identity_rule' => 'Use pilot-01 through pilot-05 aliases; never store club names, user IDs, contact data, member numbers, or free text in release evidence.',
            ],
            'journeys' => [
                'member_lifecycle',
                'coach_week',
                'athlete_day',
            ],
            'guest_acceptance' => [
                'club_discovery' => [
                    'source' => 'resources/js/Pages/Guest/Vereine.vue',
                    'test' => 'tests/Feature/PublicDiscoverySeoTest.php',
                ],
                'public_membership_entry' => [
                    'source' => 'app/Services/ClubMembershipLifecycleService.php',
                    'test' => 'tests/Feature/ClubMembershipLifecycleIntegrationTest.php',
                ],
                'public_privacy_and_accessibility' => [
                    'source' => 'resources/js/Components/Guest/Nav.vue',
                    'test' => 'tests/Feature/GuestExperienceOptimizationTest.php',
                ],
            ],
            'readiness_sources' => [
                'onboarding' => [
                    'owner' => 'Customer Success',
                    'source' => 'app/Services/ClubOnboardingService.php',
                    'contract' => '2026-08-09.club-onboarding.v1',
                ],
                'support_sla' => [
                    'owner' => 'Support / Operations',
                    'source' => 'app/Services/SupportSlaService.php',
                    'contract' => '2026-08-09.support-sla.v1',
                ],
                'privacy_safe_analytics' => [
                    'owner' => 'Product / Data Protection',
                    'source' => 'app/Services/ProductAnalyticsService.php',
                    'contract' => 'consent_and_minimum_group_required',
                ],
                'release_preflight' => [
                    'owner' => 'Release Management',
                    'source' => 'app/Support/ReleaseReadinessReport.php',
                    'contract' => '2026-08-09',
                ],
            ],
            'metrics' => [
                self::metric('onboarding_completion', 'increase', 'percentage_points', 10, 'ClubOnboardingService'),
                self::metric('weekly_active_member_rate', 'increase', 'percentage_points', 5, 'consented_aggregate'),
                self::metric('training_documentation_rate', 'increase', 'percentage_points', 10, 'consented_aggregate'),
                self::metric('membership_admin_cycle_hours', 'decrease', 'percent', 20, 'customer_success_aggregate'),
                self::metric('support_resolution_within_sla', 'increase', 'percentage_points', 5, 'SupportSlaService'),
                self::metric('critical_incidents', 'maximum', 'count', 0, 'incident_register'),
            ],
            'checkpoints' => [
                ['key' => 'baseline', 'week' => 0, 'owner' => 'Customer Success / Product'],
                ['key' => 'activation', 'week' => 1, 'owner' => 'Customer Success'],
                ['key' => 'adoption', 'week' => 3, 'owner' => 'Product / Customer Success'],
                ['key' => 'stability', 'week' => 6, 'owner' => 'SRE / Support'],
                ['key' => 'exit', 'week' => 8, 'owner' => 'Product Lead'],
            ],
            'rollback' => [
                'owner' => 'SRE / Product',
                'runbook' => 'docs/CLUB_PILOT_RUNBOOK.md',
                'must_be_rehearsed' => true,
                'preserve_user_data' => true,
                'triggers' => [
                    'confirmed_cross_tenant_access',
                    'critical_privacy_or_security_incident',
                    'irreconcilable_billing_state',
                    'repeated_data_loss_or_restore_failure',
                    'critical_journey_unavailable_beyond_slo',
                ],
            ],
            'evidence' => [
                'template' => 'resources/release/club_pilot_evidence.template.json',
                'local_path' => 'resources/release/club_pilot_evidence.local.json',
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'external_gate' => 'club_pilot',
            ],
            'responsibility' => [
                'responsible' => 'Customer Success',
                'accountable' => 'Product Lead',
                'consulted' => ['SRE', 'Support', 'Security / Data Protection', 'Club Product'],
                'informed' => ['CTO', 'Management', 'Engineering'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function metric(string $key, string $direction, string $unit, int $minimumChange, string $source): array
    {
        return [
            'key' => $key,
            'direction' => $direction,
            'unit' => $unit,
            'minimum_change' => $minimumChange,
            'source' => $source,
            'personal_data_allowed' => false,
        ];
    }
}
