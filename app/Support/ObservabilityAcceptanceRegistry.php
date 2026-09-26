<?php

namespace App\Support;

final class ObservabilityAcceptanceRegistry
{
    public const CONTRACT = 'observability-slo-readiness.v1';

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'minimum_evidence_window_hours' => max(24, (int) config('airmius_observability.minimum_evidence_window_hours', 24)),
            'signals' => [
                'availability' => self::signal(
                    owner: 'SRE / Platform',
                    severity: 'critical',
                    alertWithinMinutes: 5,
                    objectives: ['minimum_availability_percent' => 99.9],
                    sources: ['external_uptime_probe', StagingHttpDeliveryReport::CONTRACT],
                    coverage: ['guest.vereine', 'guest.marketplace', 'guest.e-learning', 'api.v1.meta'],
                ),
                'api_error_rate' => self::signal(
                    owner: 'Backend / SRE',
                    severity: 'critical',
                    alertWithinMinutes: 5,
                    objectives: ['maximum_http_5xx_percent' => 1.0],
                    sources: ['edge_or_apm_request_metrics', 'http.performance'],
                ),
                'request_latency' => self::signal(
                    owner: 'Backend / SRE',
                    severity: 'high',
                    alertWithinMinutes: 10,
                    objectives: ['maximum_server_p95_ms' => 1000, 'maximum_db_p95_ms' => 500],
                    sources: ['edge_or_apm_request_metrics', 'http.performance'],
                ),
                'guest_core_web_vitals' => self::signal(
                    owner: 'Frontend / SRE',
                    severity: 'high',
                    alertWithinMinutes: 30,
                    objectives: ['maximum_lcp_p75_ms' => 2500, 'maximum_inp_p75_ms' => 200, 'maximum_cls_p75' => 0.1],
                    sources: ['synthetic_browser', 'consented_aggregate_rum'],
                    coverage: ['guest.vereine', 'guest.marketplace', 'guest.e-learning'],
                ),
                'queue_health' => self::signal(
                    owner: 'Backend / SRE',
                    severity: 'critical',
                    alertWithinMinutes: 5,
                    objectives: ['maximum_stale_jobs' => 0, 'maximum_recent_failed_jobs' => 0, 'stale_after_minutes' => 15],
                    sources: ['airmius:monitor-operations'],
                ),
                'webhook_delivery' => self::signal(
                    owner: 'Commerce / SRE',
                    severity: 'critical',
                    alertWithinMinutes: 5,
                    objectives: ['maximum_http_5xx_percent' => 1.0, 'signature_failures_alerted' => 1],
                    sources: ['edge_or_apm_route_metrics', 'provider-smoke-readiness.v1'],
                ),
                'mail_delivery' => self::signal(
                    owner: 'Operations / SRE',
                    severity: 'high',
                    alertWithinMinutes: 15,
                    objectives: ['maximum_failed_percent' => 2.0, 'maximum_stale_minutes' => 15],
                    sources: ['mail_deliveries', 'airmius:monitor-operations'],
                ),
                'push_delivery' => self::signal(
                    owner: 'Mobile / SRE',
                    severity: 'high',
                    alertWithinMinutes: 15,
                    objectives: ['maximum_failed_percent' => 5.0, 'maximum_stale_deliveries' => 0, 'stale_after_minutes' => 30],
                    sources: ['mobile_push_deliveries', 'airmius:monitor-operations'],
                ),
                'backup_freshness' => self::signal(
                    owner: 'DevOps / SRE',
                    severity: 'critical',
                    alertWithinMinutes: 60,
                    objectives: [
                        'maximum_age_hours' => 30,
                        'restore_rehearsal_required' => 1,
                        'offsite_required' => 1,
                        'rpo_hours' => (int) config('airmius_backup.rpo_hours', 24),
                        'rto_hours' => (int) config('airmius_backup.rto_hours', 4),
                    ],
                    sources: ['backup_manifest', 'airmius:monitor-operations'],
                ),
                'scheduler_health' => self::signal(
                    owner: 'Backend / SRE',
                    severity: 'critical',
                    alertWithinMinutes: 15,
                    objectives: ['monitor_frequency_minutes' => 60, 'backup_frequency_hours' => 24],
                    sources: ['scheduler:list', 'airmius:monitor-operations', 'backup_manifest'],
                ),
                'integration_health' => self::signal(
                    owner: 'Backend / Integrations',
                    severity: 'high',
                    alertWithinMinutes: 60,
                    objectives: ['provider_token_checks_scheduled' => 1, 'sport_sync_scheduled' => 1],
                    sources: ['scheduler:list', 'provider-smoke-readiness.v1', 'sport_integrations'],
                ),
            ],
            'data_policy' => [
                'synthetic_guest_checks_preferred' => true,
                'rum_requires_consent' => true,
                'rum_minimum_group_size' => 5,
                'allow_raw_urls' => false,
                'allow_query_strings' => false,
                'allow_request_or_response_bodies' => false,
                'allow_user_or_device_identifiers' => false,
                'allow_ip_addresses' => false,
                'allow_credentials_or_tokens' => false,
            ],
            'artifacts' => [
                'app/Support/OperationsMonitor.php',
                'app/Http/Middleware/MeasureRequestPerformance.php',
                'app/Support/StagingHttpDeliveryReport.php',
                'app/Support/ProviderSmokeReadinessReport.php',
                'routes/console.php',
                'tests/Feature/OperationsMonitoringTest.php',
                'tests/Unit/RequestPerformanceTelemetryTest.php',
                'tests/Feature/StagingHttpDeliveryAuditTest.php',
                'tests/Feature/GuestExperienceOptimizationTest.php',
                'docs/OPERATIONS_MONITORING_RUNBOOK.md',
                'resources/release/observability_evidence.template.json',
            ],
            'evidence' => [
                'local_path' => (string) config('airmius_observability.evidence_path'),
                'external_gate' => 'external_observability',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function signal(
        string $owner,
        string $severity,
        int $alertWithinMinutes,
        array $objectives,
        array $sources,
        array $coverage = [],
    ): array {
        return [
            'owner' => $owner,
            'severity' => $severity,
            'alert_within_minutes' => $alertWithinMinutes,
            'objectives' => $objectives,
            'sources' => $sources,
            'coverage' => $coverage,
            'stores_personal_data' => false,
        ];
    }
}
