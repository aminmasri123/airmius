<?php

namespace App\Support;

use App\Services\LegalContentLocalizer;

final class GovernanceAssuranceRegistry
{
    public const CONTRACT = 'governance-assurance.v1';

    public const GATE_IDS = [
        'legal_release_approval',
        'dpia_approval',
        'external_penetration_test',
    ];

    public const LEGAL_KEYS = [
        'provider_identity',
        'multilingual_public_legal_pages_and_source_precedence',
        'terms_and_role_model',
        'privacy_processing_and_legal_bases',
        'consent_cookies_and_product_analytics',
        'minors_and_parental_governance',
        'commerce_withdrawal_refunds_and_billing',
        'marketplace_sponsor_ads_and_agency',
        'processors_transfers_routing_push_and_ai',
        'store_privacy_declarations',
        'retention_rights_and_incidents',
        'version_bound_approval',
    ];

    public const DPIA_PROCESSING_KEYS = [
        'health_training_nutrition_and_body_data',
        'precise_location_routes_and_live_tracking',
        'minors_guardians_and_vulnerable_people',
        'matching_profiling_and_recruiting',
        'personalized_ads_sponsors_and_conversion',
        'product_analytics_and_behavioral_metrics',
        'ai_assistance_images_and_recommendations',
        'community_chat_files_and_moderation',
        'payments_marketplace_refunds_and_payouts',
        'club_tenant_membership_and_administration',
    ];

    public const DPIA_SECTION_KEYS = [
        'systematic_description_scope_context_and_purposes',
        'necessity_proportionality_and_legal_basis',
        'data_subjects_data_flows_processors_and_transfers',
        'risk_to_rights_and_freedoms',
        'controls_safeguards_security_and_residual_risk',
        'dpo_advice_and_accountability_decision',
        'data_subject_or_representative_views_when_appropriate',
        'article_36_prior_consultation_when_residual_risk_is_high',
        'change_trigger_and_periodic_review',
    ];

    public const PENTEST_SCOPE_KEYS = [
        'public_guest_discovery_checkout_and_token_pages',
        'identity_authentication_mfa_recovery_and_sessions',
        'authorization_roles_tenant_and_object_isolation',
        'api_inventory_bola_mass_assignment_and_data_exposure',
        'admin_super_admin_support_and_impersonation_boundaries',
        'chat_realtime_notifications_files_and_uploads',
        'commerce_marketplace_billing_refunds_payouts_and_webhooks',
        'recruiting_matching_profile_sharing_and_consent',
        'training_health_nutrition_gps_routes_and_events',
        'ads_sponsors_agency_attribution_and_reporting',
        'elearning_blog_feed_story_and_user_content',
        'mobile_secure_storage_transport_push_and_deep_links',
        'input_validation_business_logic_rate_limits_and_abuse',
        'headers_cors_storage_backups_cloud_and_deployment',
        'privacy_export_correction_erasure_and_consent_withdrawal',
        'dependencies_secrets_logging_errors_and_supply_chain',
    ];

    public const PENTEST_ACCEPTANCE_KEYS = [
        'independent_authorized_assessor',
        'exact_release_and_signed_mobile_build',
        'approved_rules_of_engagement_and_test_window',
        'non_destructive_test_data_and_incident_contact',
        'all_scope_items_exercised_or_explicitly_blocked',
        'critical_and_high_findings_closed_and_retested',
        'remaining_risk_owned_dated_and_formally_accepted',
        'executive_report_and_remediation_retest_references',
    ];

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'release_version' => ReleaseReadinessReport::VERSION,
            'gate_ids' => self::GATE_IDS,
            'role_separation' => [
                'legal_release_approval' => 'Legal / Data Protection',
                'dpia_approval' => 'Data Protection Officer',
                'external_penetration_test' => 'Independent Security Assessor',
                'manifest_custodian' => 'Release Management',
                'self_approval_allowed' => false,
                'waiver_allowed' => false,
            ],
            'legal_routes' => [
                'legal.imprint',
                'policy.show',
                'legal.account-deletion',
                'legal.data-erasure',
                'terms.show',
                'legal.community',
                'legal.minors',
                'legal.cookies',
                'legal.withdrawal',
                'legal.reporting',
            ],
            'legal_requirements' => self::LEGAL_KEYS,
            'legal_localization' => [
                'contract' => LegalContentLocalizer::CONTRACT,
                'source_locale' => SupportedLocale::DEFAULT,
                'locales' => SupportedLocale::ALL,
                'server_only_catalogs' => true,
                'safe_source_fallback' => true,
                'external_legal_and_native_review_required' => true,
            ],
            'dpia_processing_families' => self::DPIA_PROCESSING_KEYS,
            'dpia_required_sections' => self::DPIA_SECTION_KEYS,
            'penetration_test_scope' => self::PENTEST_SCOPE_KEYS,
            'penetration_test_acceptance' => self::PENTEST_ACCEPTANCE_KEYS,
            'evidence_policy' => [
                'local_evidence_is_authoritative' => false,
                'output_evidence_references' => false,
                'stores_personal_data' => false,
                'stores_security_findings' => false,
                'stores_raw_urls_or_paths' => false,
                'stores_secrets_or_credentials' => false,
                'stores_reviewer_identity' => false,
                'short_reference_only' => true,
            ],
            'artifacts' => [
                'app/Console/Commands/AuditLegalReadiness.php',
                'app/Http/Controllers/LegalPageController.php',
                'app/Services/LegalContentLocalizer.php',
                'app/Support/SecurityPrivacyAcceptanceRegistry.php',
                'app/Support/SecurityPrivacyReadinessReport.php',
                'config/legal.php',
                'config/product_analytics.php',
                'docs/DATA_PROCESSING_PROVIDERS.md',
                'docs/LEGAL_REVIEW_PACK.md',
                'docs/PRIVACY_RIGHTS_PROCESS.md',
                'docs/SECURITY_PRIVACY_INCIDENT_RUNBOOK.md',
                'docs/SECURITY_REVIEW.md',
                'mobile/airmius_mobile/store_listing/privacy/app_store_privacy_draft.md',
                'mobile/airmius_mobile/store_listing/privacy/play_data_safety_draft.md',
                'mobile/airmius_mobile/store_listing/privacy/privacy_submission_matrix.md',
                'resources/release/platform_release_gates.json',
                'resources/legal/de.json',
                'resources/legal/en.json',
                'resources/legal/fr.json',
                'resources/legal/ar.json',
                'scripts/translate_visible_ui_offline.py',
                'scripts/merge_visible_ui_translations.php',
                'tests/Feature/LegalPagesTest.php',
                'tests/Feature/LocalizedLegalGuestPagesTest.php',
                'tests/Feature/ProductAnalyticsPrivacyTest.php',
                'tests/Feature/SecurityPrivacyAcceptanceContractTest.php',
                'tests/Feature/GuestExperienceOptimizationTest.php',
            ],
            'authoritative_sources' => [
                'gdpr' => 'https://eur-lex.europa.eu/eli/reg/2016/679/oj',
                'edpb_dpia' => 'https://www.edpb.europa.eu/endorsed-wp29-guidelines_en',
                'owasp_wstg' => 'https://owasp.org/www-project-web-security-testing-guide/latest/',
                'bsi_pentest' => 'https://www.bsi.bund.de/SharedDocs/Downloads/DE/BSI/Sicherheitsberatung/Pentest_Webcheck/Leitfaden_Penetrationstest.pdf',
            ],
        ];
    }
}
