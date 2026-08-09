<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileReleaseEvidenceIntegrityContractTest extends TestCase
{
    public function test_manifest_sync_requires_explicit_version_bound_success_markers(): void
    {
        $sync = $this->source('mobile/airmius_mobile/scripts/sync_release_evidence_manifest.ps1');

        $this->assertStringContainsString('function Test-LogMatchesEvidence', $sync);
        $this->assertSame(1, substr_count($sync, 'Test-LogContainsNoFailure -Path'));
        $this->assertStringContainsString('$VersionMarkers = @(', $sync);
        $this->assertStringContainsString('Version: $($Manifest.version)', $sync);
        $this->assertStringContainsString('$HasVersionConsistencyEvidence -and', $sync);

        foreach ([
            'No issues found!',
            'Local release prerequisites check passed.',
            'Linux Android release prerequisites passed.',
            'Flutter dependency lock check passed.',
            'Logo/theme asset check passed.',
            'Manual release evidence pack check passed.',
            'Release configuration check passed.',
            'Release secrets hygiene check passed.',
        ] as $marker) {
            $this->assertStringContainsString($marker, $sync);
        }

        $this->assertStringNotContainsString('contains no obvious failure markers', $sync);
    }

    public function test_build_and_bundle_gates_require_valid_logs_and_non_empty_artifacts(): void
    {
        $sync = $this->source('mobile/airmius_mobile/scripts/sync_release_evidence_manifest.ps1');

        $this->assertStringContainsString('app-release\\.aab', $sync);
        $this->assertStringContainsString('app-release\\.apk', $sync);
        $this->assertStringContainsString('Runner\\.app', $sync);
        $this->assertGreaterThanOrEqual(4, substr_count($sync, '-PathType Leaf'));
        $this->assertGreaterThanOrEqual(6, substr_count($sync, '.Length -gt 0'));
        $this->assertStringContainsString('release-evidence-bundle-contents.log', $sync);
        $this->assertStringContainsString('Release evidence bundle content check passed.', $sync);
        $this->assertStringContainsString('$HasIosNoCodesignLog -and $HasIosRunnerArtifact', $sync);
    }

    public function test_all_release_pipelines_persist_version_and_bundle_validation_evidence(): void
    {
        $pipeline = $this->source('mobile/airmius_mobile/scripts/run_full_release_evidence_pipeline.ps1');
        $androidWorkflow = $this->source('.github/workflows/airmius-mobile.yml');
        $iosWorkflow = $this->source('.github/workflows/airmius-mobile-ios.yml');
        $manifest = json_decode(
            ltrim(
                $this->source('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json'),
                "\xEF\xBB\xBF",
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString('release-version-consistency.log', $pipeline);
        $this->assertStringContainsString('release-evidence-bundle-contents.log', $pipeline);
        $this->assertGreaterThanOrEqual(3, substr_count($pipeline, 'Tee-Object -FilePath $BundleContentsLog'));

        foreach ([$androidWorkflow, $iosWorkflow] as $workflow) {
            $this->assertStringContainsString('release-version-consistency', $workflow);
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($workflow, 'release-evidence-bundle-contents.log'),
            );
        }

        $prerequisites = collect($manifest['gates'])
            ->firstWhere('id', 'local_release_prerequisites');

        $this->assertSame(
            'scripts/assert_linux_android_release_prerequisites.sh',
            $prerequisites['linux_local_command'],
        );
    }

    public function test_android_release_floor_is_explicit_and_checker_accepts_safe_higher_versions(): void
    {
        $gradle = $this->source('mobile/airmius_mobile/android/app/build.gradle.kts');
        $configuration = $this->source('mobile/airmius_mobile/scripts/assert_release_configuration.ps1');

        $this->assertMatchesRegularExpression('/minSdk\s*=\s*24/', $gradle);
        $this->assertStringNotContainsString('minSdk = flutter.minSdkVersion', $gradle);
        $this->assertStringContainsString('(?<minSdk>\\d+)', $configuration);
        $this->assertStringContainsString('if ($MinSdk -lt 23)', $configuration);
        $this->assertStringContainsString('Android minSdk: $MinSdk', $configuration);
    }

    public function test_linux_version_checker_matches_the_release_manifest_contract(): void
    {
        $script = $this->source('mobile/airmius_mobile/scripts/assert_release_version_consistency.sh');
        $manifest = json_decode(
            ltrim(
                $this->source('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json'),
                "\xEF\xBB\xBF",
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString('set -euo pipefail', $script);
        $this->assertStringContainsString('Release version consistency check passed.', $script);
        $this->assertStringContainsString('Manifest and pubspec versions do not match.', $script);
        $this->assertStringContainsString('com\\.airmius\\.app', $script);
        $this->assertSame(
            'scripts/assert_release_version_consistency.sh',
            $manifest['linux_version_consistency_command'],
        );
    }

    public function test_linux_evidence_sync_is_local_strict_and_non_destructive(): void
    {
        $script = $this->source('mobile/airmius_mobile/scripts/sync_linux_release_evidence_manifest.sh');
        $manifest = json_decode(
            ltrim(
                $this->source('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json'),
                "\xEF\xBB\xBF",
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString('set -euo pipefail', $script);
        $this->assertStringContainsString('Refusing to overwrite the authoritative release evidence manifest.', $script);
        $this->assertStringContainsString('Version: $manifest_version', $script);
        $this->assertStringContainsString('No issues found!', $script);
        $this->assertStringContainsString('APK signature: verified', $script);
        $this->assertStringContainsString('AAB JAR signature: verified', $script);
        $this->assertStringContainsString('Android minSdk: 24', $script);
        $this->assertStringContainsString('The authoritative manifest was not modified.', $script);
        $this->assertSame(
            'scripts/sync_linux_release_evidence_manifest.sh',
            $manifest['linux_local_evidence_sync_command'],
        );

        $preflight = $this->source('app/Support/ReleaseReadinessReport.php');
        $this->assertStringContainsString('release_evidence_manifest.local.json', $preflight);
        $this->assertStringContainsString('this remains non-authoritative until packaging and review', $preflight);
        $this->assertStringContainsString('$localIds === $authoritativeIds', $preflight);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));

        $this->assertIsString($source, $path.' must be readable.');

        return $source;
    }
}
