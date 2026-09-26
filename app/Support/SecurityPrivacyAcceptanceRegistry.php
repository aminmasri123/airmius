<?php

namespace App\Support;

use App\Support\Privacy\DataClassification;
use App\Support\Privacy\ProcessingPurpose;

final class SecurityPrivacyAcceptanceRegistry
{
    public const CONTRACT = 'security-privacy-acceptance.v1';

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'principles' => [
                'purpose_limitation' => 'GDPR Article 5(1)(b)',
                'data_minimization' => 'GDPR Article 5(1)(c)',
                'storage_limitation' => 'GDPR Article 5(1)(e)',
                'integrity_confidentiality' => 'GDPR Article 5(1)(f)',
                'privacy_by_design' => 'GDPR Article 25',
                'security_of_processing' => 'GDPR Article 32',
                'breach_notification' => 'GDPR Articles 33 and 34',
                'dpia' => 'GDPR Article 35',
            ],
            'classifications' => array_map(
                static fn (DataClassification $classification): string => $classification->value,
                DataClassification::cases(),
            ),
            'purposes' => array_map(
                static fn (ProcessingPurpose $purpose): string => $purpose->value,
                ProcessingPurpose::cases(),
            ),
            'technical_controls' => [
                'security_headers' => self::control(
                    'Security Engineering',
                    ['app/Http/Middleware/ApplySecurityHeaders.php', 'config/airmius_security.php'],
                    ['tests/Feature/SecurityHeadersTest.php'],
                ),
                'admin_least_privilege' => self::control(
                    'Platform Security',
                    ['app/Support/AdminTwoFactor.php', 'routes/admin.php'],
                    ['tests/Feature/AdminAreaSecurityTest.php'],
                ),
                'privacy_rights' => self::control(
                    'Data Protection / Backend',
                    ['app/Services/UserPrivacyExportService.php', 'app/Services/UserDataErasureService.php'],
                    ['tests/Feature/PrivacyRightsProcessTest.php', 'tests/Feature/UserDataErasureTest.php'],
                ),
                'upload_isolation' => self::control(
                    'Security / Platform',
                    ['app/Support/UploadStorage.php', 'app/Http/Controllers/Api/V1/UploadController.php'],
                    ['tests/Feature/UploadValidationTest.php'],
                ),
                'tenant_and_chat_isolation' => self::control(
                    'Backend / Security',
                    ['app/Policies/FilePolicy.php', 'app/Policies/ConversationPolicy.php'],
                    ['tests/Feature/ChatSecurityTest.php', 'tests/Feature/FileManagerFeatureTest.php'],
                ),
                'backup_restore' => self::control(
                    'SRE / Operations',
                    ['app/Console/Commands/CreateDatabaseBackup.php', 'app/Console/Commands/RestoreDatabaseBackup.php'],
                    ['tests/Feature/DatabaseBackupRestoreTest.php'],
                ),
            ],
            'guest_controls' => [
                'tokenized_order_pages' => self::control(
                    'Commerce / Security',
                    ['app/Http/Controllers/CommerceCheckoutController.php', 'routes/web.php'],
                    ['tests/Feature/GuestExperienceOptimizationTest.php'],
                ),
                'public_intake_consent' => self::control(
                    'Product / Data Protection',
                    ['app/Http/Controllers/Api/V1/PublicAgencyController.php', 'app/Http/Controllers/Api/V1/PublicRecruitingController.php'],
                    ['tests/Feature/AgencyAdsOptimizationContractTest.php', 'tests/Feature/RecruitingPipelineContractTest.php'],
                ),
                'public_rate_limits' => self::control(
                    'Platform Engineering',
                    ['routes/api.php', 'routes/guest.php'],
                    ['tests/Feature/ApiRateLimitContractTest.php', 'tests/Feature/PublicContentApiTest.php'],
                ),
                'public_discovery_minimization' => self::control(
                    'Product / Data Protection',
                    ['app/Services/PublicDiscoveryService.php'],
                    ['tests/Feature/PublicDiscoverySeoTest.php'],
                ),
                'cookie_and_tracking_consent' => self::control(
                    'Product / Data Protection',
                    ['app/Http/Middleware/HandleInertiaRequests.php', 'config/product_analytics.php'],
                    ['tests/Feature/CookieTrackingConsentTest.php', 'tests/Feature/ProductAnalyticsPrivacyTest.php'],
                ),
            ],
            'privacy_rights_routes' => [
                'auth.settings.privacy.export',
                'auth.settings.privacy.correct',
                'auth.settings.privacy.withdraw-consents',
                'api.v1.privacy.export',
                'api.v1.privacy.correct',
                'api.v1.privacy.withdraw-consents',
            ],
            'notices_and_consents' => [
                'contract' => 'airmius.privacy-notice-consent.v1',
                'versioned_notices_required' => true,
                'purpose_bound_consent_required' => true,
                'evidence_fields' => [
                    'notice_version',
                    'purpose',
                    'granted_at',
                    'withdrawn_at',
                    'actor_id',
                    'source_surface',
                    'locale',
                    'ip_hash',
                    'user_agent_hash',
                ],
                'withdrawal_routes' => [
                    'auth.settings.privacy.withdraw-consents',
                    'api.v1.privacy.withdraw-consents',
                ],
                'impact_assessment' => [
                    'required_for_high_risk_processing' => true,
                    'links_to_processing_inventory' => true,
                    'external_dpia_gate' => 'dpia_approval',
                ],
            ],
            'retention' => [
                'ad_events' => self::retention(
                    DataClassification::Personal,
                    ProcessingPurpose::Analytics,
                    'airmius:prune-ad-events --limit=1000',
                    'app/Console/Commands/PruneAdEvents.php',
                    'daily 03:45',
                    'Product Analytics / Operations',
                    'Minimum 30 days; configured default 180 days.',
                ),
                'agency_requests' => self::retention(
                    DataClassification::Personal,
                    ProcessingPurpose::Support,
                    'airmius:prune-website-requests --limit=1000',
                    'app/Console/Commands/PruneWebsiteRequests.php',
                    'daily 03:48',
                    'Agency / Operations',
                    '12 months while active; 6 months after completion or cancellation.',
                ),
                'recruiting_interests' => self::retention(
                    DataClassification::Sensitive,
                    ProcessingPurpose::Organization,
                    'airmius:prune-recruiting-interests --limit=1000',
                    'app/Console/Commands/PruneRecruitingInterests.php',
                    'daily 03:50',
                    'Club Recruiting / Operations',
                    'Six months after rejection or hiring unless an approved exception applies.',
                ),
                'expired_stories' => self::retention(
                    DataClassification::Personal,
                    ProcessingPurpose::ProductOperation,
                    'airmius:prune-expired-stories --limit=500',
                    'app/Console/Commands/PruneExpiredStories.php',
                    'hourly',
                    'Community / Operations',
                    'Delete after the story expiry timestamp, including stored media.',
                ),
                'delivery_envelopes' => self::retention(
                    DataClassification::Internal,
                    ProcessingPurpose::Security,
                    'airmius:prune-platform-delivery --outbox-days=30 --failed-outbox-days=90 --limit=1000',
                    'app/Console/Commands/PrunePlatformDeliveryData.php',
                    'daily 03:55',
                    'Platform Engineering / Operations',
                    'Expired idempotency responses; delivered envelopes 30 days; permanently failed envelopes 90 days.',
                ),
                'inactive_accounts' => [
                    'classification' => DataClassification::Sensitive->value,
                    'purpose' => ProcessingPurpose::ProductOperation->value,
                    'command' => 'airmius:process-inactive-accounts',
                    'command_source' => 'app/Console/Commands/ProcessInactiveAccounts.php',
                    'schedule' => 'daily 03:30',
                    'owner' => 'Data Protection / Operations',
                    'policy' => 'Warnings after approximately 12 and 18 months; restriction at 24 months; staged anonymization.',
                    'dry_run' => true,
                    'bounded' => true,
                    'bounded_strategy' => 'chunkById(50',
                ],
            ],
            'retention_governance' => [
                'configurable_by_data_kind' => true,
                'preview_required' => true,
                'legal_hold_blocks_deletion' => true,
                'four_eyes_release_required_for_irreversible_delete' => true,
                'irreversible_deletion_log_required' => true,
                'commands_are_dry_run_and_bounded' => true,
                'rollback_mode' => 'restore_from_backup_or_hold_before_commit',
            ],
            'processors' => [
                'contract' => 'airmius.processor-management.v1',
                'source' => 'docs/DATA_PROCESSING_PROVIDERS.md',
                'protected_management_required' => true,
                'required_fields' => [
                    'processor',
                    'purpose',
                    'region',
                    'dpa_or_avv_status',
                    'subprocessors',
                    'contract_start',
                    'contract_end',
                    'review_due_at',
                    'owner',
                ],
                'review_interval_months' => 6,
                'public_logs_never_include_contract_values' => true,
            ],
            'security_controls' => [
                'encryption_at_rest_required' => true,
                'secret_values_never_printed' => true,
                'backup_encryption_required' => true,
                'upload_validation_required' => true,
                'audit_integrity_hash_chain_required' => true,
                'security_headers_required' => true,
                'control_sources' => [
                    'app/Http/Middleware/ApplySecurityHeaders.php',
                    'app/Support/UploadStorage.php',
                    'app/Console/Commands/CreateDatabaseBackup.php',
                    'app/Models/SupportTicketConfidentialAudit.php',
                ],
            ],
            'incident_drill' => [
                'mode' => 'repository_only_no_external_actions',
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'classification_required' => true,
                'owner_required' => true,
                'deadline_required' => true,
                'mitigation_required' => true,
                'notification_decision_required' => true,
                'roles' => [
                    'incident_commander' => 'SRE / Operations',
                    'security_lead' => 'Security Engineering',
                    'privacy_lead' => 'Data Protection Officer',
                    'technical_owner' => 'Owning Engineering Team',
                    'communications_owner' => 'Management / Communications',
                    'evidence_owner' => 'Security / Legal',
                ],
                'stages' => [
                    'detect_and_triage',
                    'contain_and_preserve_minimized_evidence',
                    'assess_data_and_risk',
                    'decide_and_document_notifications',
                    'eradicate_and_recover',
                    'verify_and_review',
                ],
                'notification_rule' => 'Notify the competent authority without undue delay and, where feasible, within 72 hours when the Article 33 risk threshold applies; document every decision and delay.',
                'authority_source' => 'https://eur-lex.europa.eu/eli/reg/2016/679/oj',
                'runbook' => 'docs/SECURITY_PRIVACY_INCIDENT_RUNBOOK.md',
                'command' => 'airmius:audit-security-privacy',
            ],
            'external_gates' => ['dpia_approval', 'external_penetration_test'],
        ];
    }

    /** @return array{owner:string,sources:array<int,string>,tests:array<int,string>} */
    private static function control(string $owner, array $sources, array $tests): array
    {
        return compact('owner', 'sources', 'tests');
    }

    /** @return array<string, mixed> */
    private static function retention(
        DataClassification $classification,
        ProcessingPurpose $purpose,
        string $command,
        string $commandSource,
        string $schedule,
        string $owner,
        string $policy,
    ): array {
        return [
            'classification' => $classification->value,
            'purpose' => $purpose->value,
            'command' => $command,
            'command_source' => $commandSource,
            'schedule' => $schedule,
            'owner' => $owner,
            'policy' => $policy,
            'dry_run' => true,
            'bounded' => true,
            'bounded_strategy' => '--limit',
        ];
    }
}
