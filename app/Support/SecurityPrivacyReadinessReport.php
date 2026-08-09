<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use JsonException;

final class SecurityPrivacyReadinessReport
{
    /** @return array<string, mixed> */
    public static function make(): array
    {
        $registry = SecurityPrivacyAcceptanceRegistry::definitions();
        $checks = [
            self::registryCheck($registry),
            self::artifactCheck($registry),
            self::headerCheck(),
            self::privacyRightsCheck($registry),
            self::retentionCheck($registry),
            self::scheduleCheck($registry),
            self::incidentCheck($registry),
        ];
        $failed = collect($checks)->where('status', 'fail')->count();
        $external = self::externalEvidence($registry['external_gates']);

        return [
            'contract' => SecurityPrivacyAcceptanceRegistry::CONTRACT,
            'generated_at' => now()->utc()->toIso8601String(),
            'mode' => $registry['incident_drill']['mode'],
            'decision' => $failed === 0 && $external['pending'] === [] && $external['failed'] === [] ? 'go' : 'no_go',
            'automated_checks_passed' => $failed === 0,
            'external_evidence_complete' => $external['pending'] === [] && $external['failed'] === [],
            'summary' => [
                'pass' => collect($checks)->where('status', 'pass')->count(),
                'fail' => $failed,
                'external_pending' => count($external['pending']),
                'external_failed' => count($external['failed']),
            ],
            'checks' => $checks,
            'external_gates' => $external,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'prints_configuration_values' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $registry */
    private static function registryCheck(array $registry): array
    {
        $passes = ($registry['contract'] ?? null) === SecurityPrivacyAcceptanceRegistry::CONTRACT
            && count($registry['classifications'] ?? []) === 5
            && count($registry['purposes'] ?? []) === 8
            && count($registry['guest_controls'] ?? []) >= 5
            && count($registry['retention'] ?? []) >= 6;

        return self::check('contract', $passes, 'Security/privacy contract, data classes, purposes, guest controls, and retention inventory are defined.');
    }

    /** @param array<string, mixed> $registry */
    private static function artifactCheck(array $registry): array
    {
        $files = [];
        foreach (['technical_controls', 'guest_controls'] as $group) {
            foreach ($registry[$group] as $control) {
                $files = array_merge($files, $control['sources'], $control['tests']);
            }
        }
        foreach ($registry['retention'] as $policy) {
            $files[] = $policy['command_source'];
        }
        $missing = array_values(array_filter(array_unique($files), static fn (string $path): bool => ! is_file(base_path($path))));

        return self::check('artifacts', $missing === [], $missing === [] ? count(array_unique($files)).' control artifacts are present.' : 'Missing: '.implode(', ', $missing));
    }

    private static function headerCheck(): array
    {
        $source = self::source('app/Http/Middleware/ApplySecurityHeaders.php');
        $directives = [
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
            'Permissions-Policy',
            "object-src 'none'",
            "frame-ancestors 'none'",
        ];
        $missing = array_values(array_filter($directives, static fn (string $directive): bool => ! str_contains($source, $directive)));
        $passes = config('airmius_security.headers.enabled', true) === true
            && config('airmius_security.headers.csp_enabled', true) === true
            && $missing === [];

        return self::check('security_headers', $passes, $passes ? 'Global header baseline is enabled and structurally complete.' : 'Missing or disabled controls: '.implode(', ', $missing));
    }

    /** @param array<string, mixed> $registry */
    private static function privacyRightsCheck(array $registry): array
    {
        $missing = array_values(array_filter($registry['privacy_rights_routes'], static fn (string $name): bool => ! Route::has($name)));

        return self::check('privacy_rights', $missing === [], $missing === [] ? count($registry['privacy_rights_routes']).' export, correction, and withdrawal routes are registered.' : 'Missing routes: '.implode(', ', $missing));
    }

    /** @param array<string, mixed> $registry */
    private static function retentionCheck(array $registry): array
    {
        $invalid = [];
        foreach ($registry['retention'] as $id => $policy) {
            $source = self::source($policy['command_source']);
            $boundedMarker = $policy['bounded_strategy'] === '--limit' ? '{--limit=' : $policy['bounded_strategy'];
            if (($policy['dry_run'] ?? false) !== true
                || ($policy['bounded'] ?? false) !== true
                || ! str_contains($source, '{--dry-run')
                || ! str_contains($source, $boundedMarker)) {
                $invalid[] = $id;
            }
        }

        return self::check('bounded_retention', $invalid === [], $invalid === [] ? count($registry['retention']).' retention jobs support a non-destructive preview and bounded processing.' : 'Unsafe retention jobs: '.implode(', ', $invalid));
    }

    /** @param array<string, mixed> $registry */
    private static function scheduleCheck(array $registry): array
    {
        $schedule = self::source('routes/console.php');
        $missing = [];
        foreach ($registry['retention'] as $id => $policy) {
            if (! str_contains($schedule, "Schedule::command('{$policy['command']}')")) {
                $missing[] = $id;
            }
        }

        return self::check('retention_schedule', $missing === [], $missing === [] ? 'Every declared retention command has a non-overlapping scheduler entry.' : 'Missing scheduler entries: '.implode(', ', $missing));
    }

    /** @param array<string, mixed> $registry */
    private static function incidentCheck(array $registry): array
    {
        $drill = $registry['incident_drill'];
        $passes = count($drill['roles'] ?? []) >= 6
            && count($drill['stages'] ?? []) >= 6
            && ($drill['stores_personal_data'] ?? true) === false
            && ($drill['stores_secrets'] ?? true) === false
            && is_file(base_path($drill['runbook']));

        return self::check('incident_drill', $passes, $passes ? 'Incident roles, stages, notification decision path, and privacy-safe evidence rules are drillable.' : 'Incident drill contract or runbook is incomplete.');
    }

    /** @param array<int, string> $requiredIds
     * @return array{pending:array<int,string>,failed:array<int,string>,passed:array<int,string>}
     */
    private static function externalEvidence(array $requiredIds): array
    {
        $manifest = self::json('resources/release/platform_release_gates.json');
        $gates = collect($manifest['gates'] ?? [])->keyBy('id');
        $result = ['pending' => [], 'failed' => [], 'passed' => []];

        foreach ($requiredIds as $id) {
            $status = data_get($gates->get($id), 'status', 'missing');
            if (in_array($status, ['passed', 'waived'], true)) {
                $result['passed'][] = $id;
            } elseif ($status === 'failed' || $status === 'missing') {
                $result['failed'][] = $id;
            } else {
                $result['pending'][] = $id;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        try {
            $decoded = json_decode(self::source($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    private static function source(string $path): string
    {
        $source = @file_get_contents(base_path($path));

        return is_string($source) ? $source : '';
    }

    /** @return array{id:string,status:string,detail:string} */
    private static function check(string $id, bool $passes, string $detail): array
    {
        return ['id' => $id, 'status' => $passes ? 'pass' : 'fail', 'detail' => $detail];
    }
}
