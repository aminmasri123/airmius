<?php

namespace App\Support;

use JsonException;

final class CrossDeviceReadinessReport
{
    private const EVIDENCE_STATUSES = ['pending', 'passed', 'failed'];

    private const REFERENCE_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{2,119}\z/';

    private const FORBIDDEN_KEYS = [
        'url', 'uri', 'host', 'hostname', 'path', 'query', 'headers', 'body',
        'payload', 'response', 'request', 'trace', 'exception', 'message',
        'notes', 'comment', 'free_text', 'token', 'secret', 'password',
        'credential', 'authorization', 'cookie', 'email', 'phone', 'ip',
        'user_id', 'device_id', 'device_serial', 'club_id', 'order_id',
        'tester', 'reviewer_name',
    ];

    /** @return array<string, mixed> */
    public function make(): array
    {
        $registry = CrossDeviceAcceptanceRegistry::definitions();
        $checks = [
            $this->contractCheck($registry),
            $this->artifactCheck($registry),
            $this->realDeviceValidatorCheck($registry),
            $this->evidenceTemplateCheck($registry),
            $this->localEvidenceCheck($registry),
            $this->mobileManifestStructureCheck($registry),
            $this->mobileEvidenceCheck($registry),
            $this->externalGateCheck('mobile_real_devices', 'external.mobile_real_devices'),
            $this->externalGateCheck('native_localization_qa', 'external.native_localization'),
            $this->externalGateCheck('wcag_human_acceptance', 'external.wcag_human_acceptance'),
        ];
        $summary = array_fill_keys(['pass', 'pending', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }
        $externalIds = [
            'mobile.authoritative_evidence',
            'external.mobile_real_devices',
            'external.native_localization',
            'external.wcag_human_acceptance',
        ];
        $externalComplete = collect($externalIds)->every(
            static fn (string $id): bool => collect($checks)->firstWhere('id', $id)['status'] === 'pass',
        );

        return [
            'contract' => CrossDeviceAcceptanceRegistry::CONTRACT,
            'release_version' => ReleaseReadinessReport::VERSION,
            'mobile_version' => CrossDeviceAcceptanceRegistry::MOBILE_VERSION,
            'generated_at' => now()->utc()->toIso8601String(),
            'decision' => $summary['fail'] === 0 && $summary['pending'] === 0 ? 'go' : 'no_go',
            'automated_checks_passed' => $summary['fail'] === 0,
            'external_evidence_complete' => $externalComplete,
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_device_identifiers' => false,
                'stores_credentials' => false,
                'stores_raw_urls_or_paths' => false,
                'stores_free_text' => false,
                'outputs_evidence_references' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function contractCheck(array $registry): array
    {
        $platforms = array_keys($registry['platforms'] ?? []);
        $locales = array_keys($registry['locales'] ?? []);
        $passes = ($registry['contract'] ?? null) === CrossDeviceAcceptanceRegistry::CONTRACT
            && ($registry['release_version'] ?? null) === ReleaseReadinessReport::VERSION
            && ($registry['mobile_version'] ?? null) === CrossDeviceAcceptanceRegistry::MOBILE_VERSION
            && $platforms === CrossDeviceAcceptanceRegistry::PLATFORM_KEYS
            && $locales === CrossDeviceAcceptanceRegistry::LOCALE_KEYS
            && ($registry['journeys'] ?? null) === CrossDeviceAcceptanceRegistry::JOURNEY_KEYS
            && ($registry['assistive_technology'] ?? null) === CrossDeviceAcceptanceRegistry::ASSISTIVE_KEYS
            && ($registry['mobile_gate_ids'] ?? null) === CrossDeviceAcceptanceRegistry::MOBILE_GATE_IDS
            && data_get($registry, 'locales.ar_rtl.direction') === 'rtl'
            && data_get($registry, 'platforms.android.real_device_required') === true
            && data_get($registry, 'platforms.ios.real_device_required') === true
            && collect($registry['privacy'] ?? [])->every(static fn (mixed $value): bool => is_bool($value));

        return $this->check(
            'repository.contract',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Four platforms, four locales, eight critical journeys, seven assistive checks, seven authoritative mobile gates, and privacy boundaries are versioned.'
                : 'The cross-device acceptance contract is incomplete or inconsistent.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function artifactCheck(array $registry): array
    {
        $files = array_values(array_filter($registry['artifacts'] ?? [], 'is_string'));
        $missing = array_values(array_filter($files, static fn (string $path): bool => ! is_file(base_path($path))));

        return $this->check(
            'repository.artifacts',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? count($files).' mobile, guest, localization, accessibility, device-helper, and evidence artifacts are present.'
                : 'Required cross-device artifacts are missing: '.implode(', ', $missing),
        );
    }

    /** @param array<string, mixed> $registry */
    private function realDeviceValidatorCheck(array $registry): array
    {
        $validator = file_get_contents(base_path('mobile/airmius_mobile/scripts/assert_real_device_smoke_evidence.sh'));
        $android = file_get_contents(base_path('mobile/airmius_mobile/scripts/run_android_real_device_smoke.sh'));
        $ios = file_get_contents(base_path('mobile/airmius_mobile/scripts/run_ios_real_device_smoke.sh'));
        $sources = [$validator, $android, $ios];
        $privacySafeHelpers = is_string($android)
            && is_string($ios)
            && str_contains($android, 'redact_output()')
            && str_contains($android, 'run_redacted_logged()')
            && str_contains($ios, 'redact_output()')
            && str_contains($ios, 'run_redacted_logged()')
            && str_contains($android, 'android-device-summary.log')
            && str_contains($android, 'Invitation smoke accepts only an explicit non-production test-* token.')
            && ! str_contains($android, 'run_logged "$output_dir/flutter-devices.log"')
            && ! str_contains($android, 'run_logged "$output_dir/adb-devices.log"')
            && ! str_contains($ios, 'run_logged "$output_dir/flutter-devices.log"');
        $passes = collect($sources)->every(static fn (mixed $source): bool => is_string($source))
            && collect($sources)->every(static fn (string $source): bool => str_contains($source, CrossDeviceAcceptanceRegistry::CONTRACT)
                && str_contains($source, ReleaseReadinessReport::VERSION)
                && str_contains($source, CrossDeviceAcceptanceRegistry::MOBILE_VERSION))
            && collect($registry['real_device_checklist_keys'])->every(
                static fn (string $prefix): bool => collect($sources)->every(static fn (string $source): bool => str_contains($source, $prefix)),
            )
            && is_string($validator)
            && str_contains($validator, 'raw URL, credential/token marker, or email address')
            && $privacySafeHelpers;

        return $this->check(
            'repository.real_device_validator',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Android/iOS templates and the validator require the same version-bound 19-item checklist; device listings, raw URLs, deep-link values, and production invitation tokens are excluded from evidence logs.'
                : 'Real-device templates, validator, or privacy-safe logging helpers are not in exact checklist/version/privacy parity.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function evidenceTemplateCheck(array $registry): array
    {
        $template = $this->json(base_path('resources/release/cross_device_evidence.template.json'));
        $data = $template['data'];
        $passes = $template['errors'] === []
            && ($data['contract'] ?? null) === CrossDeviceAcceptanceRegistry::CONTRACT
            && ($data['release_version'] ?? null) === ReleaseReadinessReport::VERSION
            && ($data['mobile_version'] ?? null) === CrossDeviceAcceptanceRegistry::MOBILE_VERSION
            && array_keys($data['platforms'] ?? []) === CrossDeviceAcceptanceRegistry::PLATFORM_KEYS
            && array_keys($data['locales'] ?? []) === CrossDeviceAcceptanceRegistry::LOCALE_KEYS
            && array_keys($data['journeys'] ?? []) === CrossDeviceAcceptanceRegistry::JOURNEY_KEYS
            && array_keys($data['assistive_technology'] ?? []) === CrossDeviceAcceptanceRegistry::ASSISTIVE_KEYS
            && ! $this->containsForbiddenKey($data);

        return $this->check(
            'repository.evidence_template',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'The version-bound template has exact platform, locale, journey, and assistive-technology parity without forbidden fields.'
                : 'The cross-device evidence template is invalid, incomplete, or contains forbidden fields.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function localEvidenceCheck(array $registry): array
    {
        $path = (string) config('airmius_cross_device.evidence_path');
        if ($path === '' || ! is_file($path)) {
            return $this->check('local.evidence', 'pending', 'No local cross-device evidence is present. Copy the template and use only short artifact references.');
        }

        $evidence = $this->json($path);
        $data = $evidence['data'];
        $errors = $evidence['errors'];
        if (($data['contract'] ?? null) !== CrossDeviceAcceptanceRegistry::CONTRACT) {
            $errors[] = 'contract';
        }
        if (($data['release_version'] ?? null) !== ReleaseReadinessReport::VERSION
            || ($data['mobile_version'] ?? null) !== CrossDeviceAcceptanceRegistry::MOBILE_VERSION) {
            $errors[] = 'version';
        }
        if (! in_array($data['status'] ?? null, self::EVIDENCE_STATUSES, true)) {
            $errors[] = 'status';
        }
        if ($this->containsForbiddenKey($data)) {
            $errors[] = 'forbidden_data_key';
        }
        if (($data['status'] ?? null) === 'passed' && ! $this->passedEvidenceComplete($data, $registry)) {
            $errors[] = 'coverage_or_approval';
        }
        if ($errors !== []) {
            return $this->check('local.evidence', 'fail', 'Local cross-device evidence is invalid: '.implode(', ', array_unique($errors)).'. Paths and values are not printed.');
        }

        $status = match ($data['status']) {
            'passed' => 'pass',
            'failed' => 'fail',
            default => 'pending',
        };

        return $this->check(
            'local.evidence',
            $status,
            'Local version-bound cross-device evidence is structurally valid; references are not emitted and remain non-authoritative.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function mobileManifestStructureCheck(array $registry): array
    {
        $manifest = $this->mobileManifest();
        $gates = $manifest['data']['gates'] ?? [];
        $ids = is_array($gates) ? collect($gates)->pluck('id') : collect();
        $passes = $manifest['errors'] === []
            && ($manifest['data']['product'] ?? null) === 'Airmius Mobile'
            && ($manifest['data']['version'] ?? null) === CrossDeviceAcceptanceRegistry::MOBILE_VERSION
            && ($manifest['data']['cross_device_audit_command'] ?? null) === 'php artisan airmius:audit-cross-device --json --strict'
            && $ids->count() === $ids->unique()->count()
            && collect($registry['mobile_gate_ids'])->every(static fn (string $id): bool => $ids->contains($id))
            && collect($gates)->every(static fn (mixed $gate): bool => is_array($gate) && filled($gate['owner'] ?? null) && filled($gate['required_evidence'] ?? null));

        return $this->check(
            'repository.mobile_manifest',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'The authoritative mobile manifest is version-aligned, uniquely keyed, owned, and contains all seven required cross-device gates.'
                : 'The authoritative mobile manifest is missing, invalid, version-mismatched, duplicated, or incomplete.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function mobileEvidenceCheck(array $registry): array
    {
        $manifest = $this->mobileManifest();
        if ($manifest['errors'] !== []) {
            return $this->check('mobile.authoritative_evidence', 'fail', 'Authoritative mobile evidence cannot be evaluated because its manifest is invalid.');
        }

        $required = collect($manifest['data']['gates'] ?? [])->whereIn('id', $registry['mobile_gate_ids']);
        $statuses = $required->pluck('status');
        $status = $statuses->contains(fn (mixed $value): bool => in_array($value, ['failed', 'blocked'], true))
            ? 'fail'
            : ($required->count() === count($registry['mobile_gate_ids']) && $statuses->every(static fn (mixed $value): bool => $value === 'passed') ? 'pass' : 'pending');

        return $this->check(
            'mobile.authoritative_evidence',
            $status,
            $status === 'pass'
                ? 'All seven required mobile build, screenshot, API, device, localization, and secure-session gates have authoritative evidence.'
                : 'One or more authoritative mobile build, screenshot, API, device, localization, or secure-session gates remain open; local evidence cannot approve them.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function externalGateCheck(string $gateId, string $checkId): array
    {
        $manifest = $this->json((string) config('airmius_cross_device.platform_gate_path'));
        $gate = collect($manifest['data']['gates'] ?? [])->firstWhere('id', $gateId);
        $status = data_get($gate, 'status', 'missing');
        $mapped = match ($status) {
            'passed', 'waived' => 'pass',
            'failed', 'missing' => 'fail',
            default => 'pending',
        };

        return $this->check(
            $checkId,
            $mapped,
            $mapped === 'pass'
                ? 'The authoritative owner-bound platform gate has reviewed evidence.'
                : 'The authoritative owner-bound platform gate remains open; repository and local evidence cannot approve it.',
        );
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $registry */
    private function passedEvidenceComplete(array $data, array $registry): bool
    {
        if (! $this->validReference($data['review_reference'] ?? null)) {
            return false;
        }

        $dimensions = [
            'platforms' => CrossDeviceAcceptanceRegistry::PLATFORM_KEYS,
            'locales' => CrossDeviceAcceptanceRegistry::LOCALE_KEYS,
            'journeys' => CrossDeviceAcceptanceRegistry::JOURNEY_KEYS,
            'assistive_technology' => CrossDeviceAcceptanceRegistry::ASSISTIVE_KEYS,
        ];
        foreach ($dimensions as $dimension => $expectedKeys) {
            $items = $data[$dimension] ?? null;
            if (! is_array($items) || array_keys($items) !== $expectedKeys) {
                return false;
            }
            foreach ($items as $item) {
                if (! is_array($item)
                    || ($item['status'] ?? null) !== 'passed'
                    || ! $this->validReference($item['evidence_reference'] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function validReference(mixed $value): bool
    {
        return is_string($value) && preg_match(self::REFERENCE_PATTERN, $value) === 1;
    }

    /** @param array<string, mixed> $data */
    private function containsForbiddenKey(array $data): bool
    {
        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            if (str_starts_with($normalized, 'stores_') && $value === false) {
                continue;
            }
            if (in_array($normalized, self::FORBIDDEN_KEYS, true)
                || collect(self::FORBIDDEN_KEYS)->contains(static fn (string $forbidden): bool => str_ends_with($normalized, '_'.$forbidden))) {
                return true;
            }
            if (is_array($value) && $this->containsForbiddenKey($value)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{data:array<string,mixed>,errors:array<int,string>} */
    private function mobileManifest(): array
    {
        return $this->json((string) config('airmius_cross_device.mobile_manifest_path'));
    }

    /** @return array{data:array<string,mixed>,errors:array<int,string>} */
    private function json(string $path): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return ['data' => [], 'errors' => ['file_missing']];
        }

        try {
            $contents = ltrim((string) file_get_contents($path), "\xEF\xBB\xBF");
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            return is_array($data)
                ? ['data' => $data, 'errors' => []]
                : ['data' => [], 'errors' => ['root_not_object']];
        } catch (JsonException) {
            return ['data' => [], 'errors' => ['invalid_json']];
        }
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
