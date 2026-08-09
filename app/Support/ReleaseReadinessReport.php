<?php

namespace App\Support;

use JsonException;
use Throwable;

final class ReleaseReadinessReport
{
    public const VERSION = '2026-08-09';

    private const MANUAL_GATE_STATUSES = ['pending', 'passed', 'failed', 'waived'];

    public function __construct(private readonly OperationsMonitor $operationsMonitor) {}

    /**
     * @return array<string, mixed>
     */
    public function make(bool $includeOperations = false): array
    {
        $checks = array_merge(
            $this->repositoryChecks(),
            [$this->deploymentConfigurationCheck()],
            $includeOperations ? [$this->operationsCheck()] : [],
            $this->manualEvidenceChecks(),
        );

        $summary = array_fill_keys(['pass', 'warn', 'pending', 'skipped', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        $hasFailure = $summary['fail'] > 0;
        $hasOpenEvidence = $summary['pending'] > 0 || $summary['skipped'] > 0;
        $automatedFailure = collect($checks)->contains(
            fn (array $check): bool => $check['area'] !== 'external' && $check['status'] === 'fail',
        );

        return [
            'version' => self::VERSION,
            'generated_at' => now()->utc()->toIso8601String(),
            'environment' => app()->environment(),
            'mode' => $includeOperations ? 'repository_and_runtime' : 'repository',
            'decision' => $hasFailure || $hasOpenEvidence ? 'no_go' : 'go',
            'automated_checks_passed' => ! $automatedFailure,
            'release_evidence_complete' => ! $hasFailure && ! $hasOpenEvidence,
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_secrets' => false,
                'stores_personal_data' => false,
                'evidence_policy' => 'Only non-sensitive ticket, artifact, or approval references belong in release manifests.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function repositoryChecks(): array
    {
        return [
            $this->phpRuntimeCheck(),
            $this->localizationCheck(),
            $this->webBuildCheck(),
            $this->deliveryContractCheck(),
            $this->mobileManifestCheck(),
            $this->platformManifestCheck(),
        ];
    }

    /** @return array<string, mixed> */
    private function phpRuntimeCheck(): array
    {
        $minimum = '8.2.0';
        $passes = version_compare(PHP_VERSION, $minimum, '>=');

        return $this->check(
            'repository.php_runtime',
            'repository',
            'PHP runtime',
            $passes ? 'pass' : 'fail',
            'Platform Engineering',
            'PHP '.PHP_VERSION."; required >= {$minimum}.",
            'composer.json',
        );
    }

    /** @return array<string, mixed> */
    private function localizationCheck(): array
    {
        try {
            $report = LocalizationReadinessReport::make();
            $requiredGates = [
                'all_supported_locale_files_present',
                'rtl_locale_has_messages',
                'arabic_json_valid',
                'arabic_has_arabic_glyphs',
                'source_key_parity',
                'automatic_ui_key_parity',
                'placeholder_parity',
                'mobile_contract_exports_rtl',
            ];
            $failedGates = array_values(array_filter(
                $requiredGates,
                fn (string $gate): bool => data_get($report, "quality_gates.{$gate}") !== true,
            ));
            if ((int) data_get($report, 'quality_gates.corrupt_target_values', 1) !== 0) {
                $failedGates[] = 'corrupt_target_values';
            }

            $regressions = $this->localizationRegressions($report);
            $passes = $report['supported_locales'] === SupportedLocale::ALL
                && $failedGates === []
                && $regressions === [];
            $open = (int) data_get($report, 'quality_gates.hardcoded_text_total_candidates', 0);

            return $this->check(
                'repository.localization',
                'repository',
                'DE/EN/FR/AR and RTL contract',
                $passes ? 'pass' : 'fail',
                'Product / Localization',
                $passes
                    ? sprintf('%d locales are structurally valid; %d tracked legal candidates; no regression.', count(SupportedLocale::ALL), $open)
                    : 'Failed gates: '.implode(', ', array_merge($failedGates, $regressions)),
                'resources/localization-baseline.json',
            );
        } catch (Throwable $exception) {
            return $this->check(
                'repository.localization',
                'repository',
                'DE/EN/FR/AR and RTL contract',
                'fail',
                'Product / Localization',
                'The localization report could not be generated ('.$exception::class.').',
                'resources/localization-baseline.json',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function localizationRegressions(array $report): array
    {
        $baseline = $this->readJson(base_path('resources/localization-baseline.json'));
        if ($baseline['errors'] !== []) {
            return ['baseline_invalid'];
        }

        $keys = [
            'quality_gates.hardcoded_text_total_candidates',
            'vue.visible_text_candidates',
            'vue.attribute_text_candidates',
            'php.response_string_candidates',
        ];

        return array_values(array_filter(array_map(
            function (string $key) use ($report, $baseline): ?string {
                $current = (int) data_get($report, $key, 0);
                $allowed = (int) data_get($baseline['data'], $key, PHP_INT_MAX);

                return $current > $allowed ? "{$key}:{$current}>{$allowed}" : null;
            },
            $keys,
        )));
    }

    /** @return array<string, mixed> */
    private function webBuildCheck(): array
    {
        $manifestPath = public_path('build/manifest.json');
        $manifest = $this->readJson($manifestPath);
        $requiredEntries = ['resources/js/app.js'];
        foreach (SupportedLocale::ALL as $locale) {
            $requiredEntries[] = "resources/js/lang/{$locale}.json";
            $requiredEntries[] = "resources/js/lang/auto/{$locale}.json";
        }

        $missing = [];
        foreach ($requiredEntries as $entry) {
            $file = is_array($manifest['data'][$entry] ?? null)
                ? ($manifest['data'][$entry]['file'] ?? null)
                : null;
            if (! is_string($file) || ! is_file(public_path('build/'.$file))) {
                $missing[] = $entry;
            }
        }

        $passes = $manifest['errors'] === [] && $missing === [];

        return $this->check(
            'repository.web_build',
            'repository',
            'Versioned web assets',
            $passes ? 'pass' : 'fail',
            'Frontend',
            $passes
                ? count($manifest['data']).' manifest entries; app and eight locale chunks are present.'
                : 'Invalid or missing entries: '.implode(', ', array_merge($manifest['errors'], $missing)),
            'public/build/manifest.json',
        );
    }

    /** @return array<string, mixed> */
    private function deliveryContractCheck(): array
    {
        $requiredFiles = [
            'app/Http/Middleware/EnforceApiCachePolicy.php',
            'app/Http/Middleware/MeasureRequestPerformance.php',
            'app/Services/ChatLatestMessageLoader.php',
            'app/Services/AthleteDailyFlowService.php',
            'app/Services/AdminOperationsService.php',
            'app/Services/ClubOnboardingService.php',
            'app/Services/ClubMembershipLifecycleService.php',
            'app/Services/ProductAnalyticsConsentService.php',
            'app/Services/ProductAnalyticsService.php',
            'app/Services/PrivacyCenterService.php',
            'app/Services/PublicDiscoveryService.php',
            'app/Services/CommerceRefundService.php',
            'app/Services/GlobalSearchService.php',
            'app/Services/SearchModuleCatalog.php',
            'app/Services/MarketplacePayoutService.php',
            'app/Services/RecruitingMatchExplanationService.php',
            'app/Services/RevenueTrustService.php',
            'app/Services/SponsorWorkspaceService.php',
            'app/Services/SupportSlaService.php',
            'app/Services/SupportAccessService.php',
            'app/Services/TeamDailyLifeService.php',
            'app/Services/Training/TrainingFeedbackService.php',
            'app/Services/Training/TrainingLogAccessService.php',
            'app/Services/Training/TrainingLogService.php',
            'app/Services/Training/TrainingRouteLinkService.php',
            'app/Listeners/AwardTrainingCompletionXp.php',
            'app/Observers/TrainingLogObserver.php',
            'app/Support/CriticalJourneyRegistry.php',
            'app/Support/EventFileContext.php',
            'app/Support/PlatformModuleRegistry.php',
            'app/Http/Controllers/Api/V1/TrainingFeedbackController.php',
            'app/Http/Controllers/AdminOperationsController.php',
            'app/Http/Controllers/PublicDiscoveryController.php',
            'app/Http/Controllers/SupportCenterController.php',
            'config/product_analytics.php',
            'database/migrations/2026_08_08_000007_add_product_analytics_consent_to_users.php',
            'database/migrations/2026_08_08_000008_add_public_discovery_indexes.php',
            'database/migrations/2026_08_08_000009_add_revenue_trust_fields.php',
            'database/migrations/2026_08_09_000001_link_sport_routes_to_training.php',
            'database/migrations/2026_08_09_000002_link_sport_routes_to_events.php',
            'database/migrations/2026_08_09_000003_integrate_recruiting_opportunity_context.php',
            'database/migrations/2026_08_09_000004_harden_commerce_refunds_and_payout_reconciliation.php',
            'database/migrations/2026_08_09_000005_connect_training_completion_to_gamification.php',
            'database/migrations/2026_08_09_000006_add_club_context_and_response_sla_to_support_tickets.php',
            'database/migrations/2026_08_09_000007_add_admin_operations_query_indexes.php',
            'mobile/airmius_mobile/lib/core/airmius_api_client.dart',
            'mobile/airmius_mobile/lib/navigation/airmius_module_destination.dart',
            'mobile/airmius_mobile/scripts/assert_release_version_consistency.sh',
            'mobile/airmius_mobile/scripts/sync_linux_release_evidence_manifest.sh',
            'mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart',
            'mobile/airmius_mobile/lib/screens/club_cockpit_screen.dart',
            'mobile/airmius_mobile/lib/screens/global_search_screen.dart',
            'mobile/airmius_mobile/lib/screens/support_helpdesk_screen.dart',
            'mobile/airmius_mobile/lib/screens/admin_support_ticket_screen.dart',
            'mobile/airmius_mobile/test/widget_test.dart',
            'public/.htaccess',
            'resources/js/Pages/Auth/Dashboard/Admin/ProductAnalytics/Index.vue',
            'resources/js/Pages/Auth/Dashboard/Admin/Operations/Index.vue',
            'resources/js/Pages/Auth/Dashboard/ClubCockpit/Index.vue',
            'resources/js/Pages/Auth/Dashboard/Support/Index.vue',
            'resources/js/Components/Dashboard/DashboardDailyFlowWidget.vue',
            'resources/js/Components/Guest/SkipLink.vue',
            'resources/js/Components/Auth/Layouts/AppMobileSearchOverlay.vue',
            'resources/js/Components/Auth/Layouts/AppSearchResults.vue',
            'resources/js/Components/Modal.vue',
            'resources/js/services/dialogService.js',
            'resources/css/app.css',
            'resources/js/Components/Teams/TeamDailyHomeWidget.vue',
            'resources/js/Pages/Guest/Discovery/Cities.vue',
            'resources/js/Pages/Guest/Discovery/Show.vue',
            'resources/lang/ar/gamification.php',
            'resources/lang/ar/club_onboarding.php',
            'resources/lang/ar/search.php',
            'resources/lang/de/gamification.php',
            'resources/lang/de/club_onboarding.php',
            'resources/lang/de/search.php',
            'resources/lang/en/gamification.php',
            'resources/lang/en/club_onboarding.php',
            'resources/lang/en/search.php',
            'resources/lang/fr/gamification.php',
            'resources/lang/fr/club_onboarding.php',
            'resources/lang/fr/search.php',
            'lang/ar/search.php',
            'lang/de/search.php',
            'lang/en/search.php',
            'lang/fr/search.php',
            'lang/ar/privacy_center.php',
            'lang/de/privacy_center.php',
            'lang/en/privacy_center.php',
            'lang/fr/privacy_center.php',
            'tests/Feature/HttpDeliveryContractTest.php',
            'tests/Feature/AdminOperationsCenterTest.php',
            'tests/Feature/ClubCockpitGovernanceTest.php',
            'tests/Feature/ClubMembershipLifecycleIntegrationTest.php',
            'tests/Feature/CriticalJourneyContractTest.php',
            'tests/Feature/DashboardDailyFlowTest.php',
            'tests/Feature/HotPathQueryContractTest.php',
            'tests/Feature/ProductAnalyticsPrivacyTest.php',
            'tests/Feature/PrivacyCenterTest.php',
            'tests/Feature/SettingsLazyLoadingTest.php',
            'tests/Feature/PublicDiscoverySeoTest.php',
            'tests/Feature/RecruitingOpportunityContextTest.php',
            'tests/Feature/CommerceRefundReconciliationTest.php',
            'tests/Feature/MarketplacePayoutServiceTest.php',
            'tests/Feature/GuestExperienceOptimizationTest.php',
            'tests/Feature/Wcag22AccessibilityContractTest.php',
            'tests/Feature/GlobalSearchTest.php',
            'tests/Feature/MobileProductionModuleNavigationContractTest.php',
            'tests/Feature/MobileReleaseEvidenceIntegrityContractTest.php',
            'tests/Feature/RevenueTrustWorkflowTest.php',
            'tests/Feature/TeamDailyLifeApiTest.php',
            'tests/Feature/SupportTicketApiTest.php',
            'tests/Feature/SupportCenterWebTest.php',
            'tests/Feature/TrainingWorkflowIntegrationTest.php',
            'tests/Feature/TrainingGamificationIntegrationTest.php',
            'tests/Feature/TrainingRouteWorkflowTest.php',
            'tests/Feature/EventRouteWorkflowTest.php',
            'tests/Feature/EventFileContextWorkflowTest.php',
            'tests/Unit/RequestPerformanceTelemetryTest.php',
            '.github/workflows/airmius-mvp-ci.yml',
            '.github/workflows/airmius-mobile.yml',
            '.github/workflows/airmius-mobile-ios.yml',
        ];
        $missing = array_values(array_filter(
            $requiredFiles,
            fn (string $path): bool => ! is_file(base_path($path)),
        ));

        return $this->check(
            'repository.delivery_contracts',
            'repository',
            'Delivery, performance, and CI contracts',
            $missing === [] ? 'pass' : 'fail',
            'Platform Engineering',
            $missing === []
                ? count($requiredFiles).' required implementation, test, and CI artifacts are present.'
                : 'Missing artifacts: '.implode(', ', $missing),
            'docs/AIRMIUS_PLATFORM_DELIVERY_RUNBOOK.md',
        );
    }

    /** @return array<string, mixed> */
    private function mobileManifestCheck(): array
    {
        $path = base_path('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json');
        $manifest = $this->readJson($path);
        $errors = $manifest['errors'];
        $data = $manifest['data'];

        if (($data['product'] ?? null) !== 'Airmius Mobile') {
            $errors[] = 'product';
        }
        if (! is_array($data['gates'] ?? null) || $data['gates'] === []) {
            $errors[] = 'gates';
        }
        if (($data['android_application_id'] ?? null) !== 'com.airmius.app') {
            $errors[] = 'android_application_id';
        }
        if (($data['ios_bundle_id'] ?? null) !== 'com.airmius.app') {
            $errors[] = 'ios_bundle_id';
        }

        $gateIds = [];
        foreach (is_array($data['gates'] ?? null) ? $data['gates'] : [] as $index => $gate) {
            if (! is_array($gate)) {
                $errors[] = "gate_{$index}";

                continue;
            }

            foreach (['id', 'title', 'owner', 'status'] as $field) {
                if (! is_string($gate[$field] ?? null) || trim($gate[$field]) === '') {
                    $errors[] = "gate_{$index}_{$field}";
                }
            }

            $id = is_string($gate['id'] ?? null) ? $gate['id'] : null;
            if ($id !== null && in_array($id, $gateIds, true)) {
                $errors[] = "gate_{$index}_duplicate_id";
            }
            if ($id !== null) {
                $gateIds[] = $id;
            }
        }

        preg_match('/^version:\s*(\S+)$/m', (string) @file_get_contents(base_path('mobile/airmius_mobile/pubspec.yaml')), $version);
        if (($data['version'] ?? null) !== ($version[1] ?? null)) {
            $errors[] = 'version';
        }

        return $this->check(
            'repository.mobile_manifest',
            'repository',
            'Mobile release manifest structure',
            $errors === [] ? 'pass' : 'fail',
            'Mobile / Release',
            $errors === []
                ? count($data['gates']).' evidence gates are version-bound and structurally readable.'
                : 'Invalid fields: '.implode(', ', array_unique($errors)),
            'mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json',
        );
    }

    /** @return array<string, mixed> */
    private function platformManifestCheck(): array
    {
        $manifest = $this->platformManifest();

        return $this->check(
            'repository.platform_manifest',
            'repository',
            'Platform evidence manifest structure',
            $manifest['errors'] === [] ? 'pass' : 'fail',
            'Release Management',
            $manifest['errors'] === []
                ? count($manifest['gates']).' external platform gates are explicit, owned, and evidence-bound.'
                : 'Manifest errors: '.implode(', ', $manifest['errors']),
            'resources/release/platform_release_gates.json',
        );
    }

    /** @return array<string, mixed> */
    private function deploymentConfigurationCheck(): array
    {
        if (! app()->environment('production')) {
            return $this->check(
                'deployment.production_configuration',
                'deployment',
                'Production configuration',
                'skipped',
                'DevOps / Security',
                'Run this gate in the production-equivalent environment.',
                'docs/AIRMIUS_PLATFORM_DELIVERY_RUNBOOK.md',
            );
        }

        $failures = [];
        $name = trim((string) config('app.name'));
        $url = trim((string) config('app.url'));
        $locale = (string) config('app.locale');
        $fallback = (string) config('app.fallback_locale');

        if ($name === '' || mb_strtolower($name) === 'laravel') {
            $failures[] = 'APP_NAME';
        }
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || blank(parse_url($url, PHP_URL_HOST))) {
            $failures[] = 'APP_URL_HTTPS';
        }
        if (blank(config('app.key'))) {
            $failures[] = 'APP_KEY';
        }
        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG';
        }
        if (! in_array($locale, SupportedLocale::ALL, true) || ! in_array($fallback, SupportedLocale::ALL, true)) {
            $failures[] = 'APP_LOCALE';
        }
        if (in_array(config('queue.default'), ['sync', 'null'], true)) {
            $failures[] = 'QUEUE_CONNECTION';
        }
        if (in_array(config('cache.default'), ['array', 'null'], true)) {
            $failures[] = 'CACHE_STORE';
        }
        if (in_array(config('mail.default'), ['array', 'log'], true)) {
            $failures[] = 'MAIL_MAILER';
        }
        if (config('session.secure') !== true || config('session.http_only') !== true) {
            $failures[] = 'SESSION_COOKIE_SECURITY';
        }
        if (config('airmius_monitoring.performance.enabled') !== true) {
            $failures[] = 'OPERATIONS_PERFORMANCE_ENABLED';
        }

        return $this->check(
            'deployment.production_configuration',
            'deployment',
            'Production configuration',
            $failures === [] ? 'pass' : 'fail',
            'DevOps / Security',
            $failures === []
                ? 'Production URL, secrets, queues, cache, mail, cookies, locales, and telemetry meet the release baseline.'
                : 'Unsafe or incomplete settings: '.implode(', ', $failures).'. Values and secrets are never printed.',
            'docs/AIRMIUS_PLATFORM_DELIVERY_RUNBOOK.md',
        );
    }

    /** @return array<string, mixed> */
    private function operationsCheck(): array
    {
        try {
            $result = $this->operationsMonitor->run();
            $failures = (int) data_get($result, 'summary.fail', 0);
            $warnings = (int) data_get($result, 'summary.warn', 0);

            return $this->check(
                'runtime.operations',
                'runtime',
                'Live operations health',
                $failures > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'pass'),
                'SRE / Operations',
                "{$failures} failing checks and {$warnings} warnings in the configured monitoring window.",
                'docs/OPERATIONS_MONITORING_RUNBOOK.md',
            );
        } catch (Throwable $exception) {
            return $this->check(
                'runtime.operations',
                'runtime',
                'Live operations health',
                'fail',
                'SRE / Operations',
                'The live monitor could not complete ('.$exception::class.'). No query or secret was printed.',
                'docs/OPERATIONS_MONITORING_RUNBOOK.md',
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manualEvidenceChecks(): array
    {
        $checks = [$this->mobileEvidenceCheck()];
        $manifest = $this->platformManifest();

        if ($manifest['errors'] !== []) {
            return $checks;
        }

        foreach ($manifest['gates'] as $gate) {
            $status = match ($gate['status']) {
                'passed', 'waived' => 'pass',
                'failed' => 'fail',
                default => 'pending',
            };
            $evidenceCount = count($gate['evidence']);
            $detail = $status === 'pass'
                ? ucfirst($gate['status'])." with {$evidenceCount} non-sensitive evidence reference(s)."
                : (string) $gate['required_evidence'];

            $checks[] = $this->check(
                'external.'.$gate['id'],
                'external',
                (string) $gate['title'],
                $status,
                (string) $gate['owner'],
                $detail,
                'resources/release/platform_release_gates.json',
            );
        }

        return $checks;
    }

    /** @return array<string, mixed> */
    private function mobileEvidenceCheck(): array
    {
        $path = base_path('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json');
        $manifest = $this->readJson($path);
        $gates = is_array($manifest['data']['gates'] ?? null) ? $manifest['data']['gates'] : [];
        $statuses = array_values(array_filter(array_map(
            fn (mixed $gate): ?string => is_array($gate) && is_string($gate['status'] ?? null) ? $gate['status'] : null,
            $gates,
        )));
        $failed = array_filter($statuses, fn (string $status): bool => str_contains($status, 'failed') || str_contains($status, 'blocked'));
        $passed = array_filter($statuses, fn (string $status): bool => $status === 'passed');
        $status = $manifest['errors'] !== [] || $failed !== []
            ? 'fail'
            : (count($passed) === count($gates) && $gates !== [] ? 'pass' : 'pending');

        $localDetail = '';
        $localPath = base_path('mobile/airmius_mobile/release_evidence/release_evidence_manifest.local.json');
        if (is_file($localPath)) {
            $localManifest = $this->readJson($localPath);
            $localGates = is_array($localManifest['data']['gates'] ?? null)
                ? $localManifest['data']['gates']
                : [];
            $authoritativeIds = collect($gates)->pluck('id')->filter()->sort()->values()->all();
            $localIds = collect($localGates)->pluck('id')->filter()->sort()->values()->all();
            $localCompatible = $localManifest['errors'] === []
                && ($localManifest['data']['product'] ?? null) === ($manifest['data']['product'] ?? null)
                && ($localManifest['data']['version'] ?? null) === ($manifest['data']['version'] ?? null)
                && ($localManifest['data']['android_application_id'] ?? null) === ($manifest['data']['android_application_id'] ?? null)
                && ($localManifest['data']['ios_bundle_id'] ?? null) === ($manifest['data']['ios_bundle_id'] ?? null)
                && $localIds === $authoritativeIds;

            if ($localCompatible) {
                $localPassed = collect($localGates)->where('status', 'passed')->count();
                $localDetail = sprintf(
                    ' %d of %d current-version gates have local technical evidence; this remains non-authoritative until packaging and review.',
                    $localPassed,
                    count($localGates),
                );
            } else {
                $localDetail = ' A local technical manifest exists but was rejected because its product, version, identifiers, or gate set is incompatible.';
            }
        }

        return $this->check(
            'external.mobile_release_evidence',
            'external',
            'Mobile and store release evidence',
            $status,
            'Mobile / Release / Legal',
            sprintf(
                '%d of %d authoritative mobile evidence gates passed; the baseline manifest remains authoritative.%s',
                count($passed),
                count($gates),
                $localDetail,
            ),
            'mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json',
        );
    }

    /**
     * @return array{gates:array<int, array<string, mixed>>,errors:array<int, string>}
     */
    private function platformManifest(): array
    {
        $path = base_path('resources/release/platform_release_gates.json');
        $manifest = $this->readJson($path);
        $errors = $manifest['errors'];
        $gates = is_array($manifest['data']['gates'] ?? null) ? $manifest['data']['gates'] : [];
        $ids = [];

        if (($manifest['data']['version'] ?? null) !== self::VERSION) {
            $errors[] = 'version';
        }
        if ($gates === []) {
            $errors[] = 'gates_missing';
        }

        foreach ($gates as $index => $gate) {
            if (! is_array($gate)) {
                $errors[] = "gate_{$index}_invalid";

                continue;
            }

            foreach (['id', 'title', 'owner', 'status', 'required_evidence'] as $key) {
                if (! is_string($gate[$key] ?? null) || trim($gate[$key]) === '') {
                    $errors[] = "gate_{$index}_{$key}";
                }
            }

            if (! is_array($gate['evidence'] ?? null)) {
                $errors[] = "gate_{$index}_evidence";
            } elseif (array_filter($gate['evidence'], fn (mixed $reference): bool => ! is_string($reference) || trim($reference) === '') !== []) {
                $errors[] = "gate_{$index}_evidence_reference";
            }
            if (is_string($gate['status'] ?? null) && ! in_array($gate['status'], self::MANUAL_GATE_STATUSES, true)) {
                $errors[] = "gate_{$index}_status";
            }

            $id = is_string($gate['id'] ?? null) ? $gate['id'] : null;
            if ($id !== null && in_array($id, $ids, true)) {
                $errors[] = "gate_{$index}_duplicate_id";
            }
            if ($id !== null) {
                $ids[] = $id;
            }

            if (in_array($gate['status'] ?? null, ['passed', 'waived'], true)) {
                if (($gate['evidence'] ?? []) === [] || blank($gate['reviewed_by'] ?? null) || blank($gate['reviewed_at'] ?? null)) {
                    $errors[] = "gate_{$index}_approval_evidence";
                }
            }
            if (($gate['status'] ?? null) === 'waived' && blank($gate['waiver_reason'] ?? null)) {
                $errors[] = "gate_{$index}_waiver_reason";
            }
        }

        return ['gates' => $gates, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * @return array{data:array<string, mixed>,errors:array<int, string>}
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return ['data' => [], 'errors' => ['file_missing']];
        }

        try {
            $contents = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
            $data = json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);

            return is_array($data)
                ? ['data' => $data, 'errors' => []]
                : ['data' => [], 'errors' => ['root_not_object']];
        } catch (JsonException) {
            return ['data' => [], 'errors' => ['invalid_json']];
        }
    }

    /** @return array<string, mixed> */
    private function check(
        string $id,
        string $area,
        string $title,
        string $status,
        string $owner,
        string $detail,
        string $source,
    ): array {
        return compact('id', 'area', 'title', 'status', 'owner', 'detail', 'source');
    }
}
