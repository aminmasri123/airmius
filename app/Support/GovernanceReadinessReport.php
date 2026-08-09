<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use JsonException;

final class GovernanceReadinessReport
{
    private const EVIDENCE_STATUSES = ['pending', 'passed', 'failed'];

    private const MANIFEST_STATUSES = ['pending', 'passed', 'failed', 'waived'];

    private const REFERENCE_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{2,119}\z/';

    private const FORBIDDEN_KEYS = [
        'url', 'uri', 'host', 'hostname', 'path', 'query', 'headers', 'body',
        'payload', 'response', 'request', 'trace', 'exception', 'message',
        'notes', 'comment', 'free_text', 'token', 'secret', 'password',
        'credential', 'authorization', 'cookie', 'email', 'phone', 'ip',
        'user_id', 'device_id', 'club_id', 'order_id', 'payment_id',
        'tester', 'reviewer_name', 'reviewed_by', 'finding', 'findings',
        'vulnerability', 'vulnerabilities', 'risk_description', 'personal_data',
    ];

    /** @return array<string, mixed> */
    public function make(): array
    {
        $registry = GovernanceAssuranceRegistry::definitions();
        $platformManifest = $this->json((string) config('airmius_governance.platform_gate_path'));
        $checks = [
            $this->contractCheck($registry),
            $this->artifactCheck($registry),
            $this->legalSurfaceCheck($registry),
            $this->legalValidatorCheck(),
            $this->dpiaCoverageCheck($registry),
            $this->penetrationCoverageCheck($registry),
            $this->evidenceTemplateCheck($registry),
            $this->platformManifestCheck($platformManifest),
            $this->localEvidenceCheck(),
            $this->externalGateCheck($platformManifest, 'legal_release_approval', 'external.legal_release_approval'),
            $this->externalGateCheck($platformManifest, 'dpia_approval', 'external.dpia_approval'),
            $this->externalGateCheck($platformManifest, 'external_penetration_test', 'external.external_penetration_test'),
        ];
        $summary = array_fill_keys(['pass', 'pending', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }
        $external = collect($checks)->filter(
            static fn (array $check): bool => str_starts_with($check['id'], 'external.'),
        );
        $repositoryFailed = collect($checks)->contains(
            static fn (array $check): bool => ! str_starts_with($check['id'], 'external.') && $check['status'] === 'fail',
        );

        return [
            'contract' => GovernanceAssuranceRegistry::CONTRACT,
            'release_version' => ReleaseReadinessReport::VERSION,
            'generated_at' => now()->utc()->toIso8601String(),
            'decision' => $summary['pending'] === 0 && $summary['fail'] === 0 ? 'go' : 'no_go',
            'automated_checks_passed' => ! $repositoryFailed,
            'external_evidence_complete' => $external->count() === count(GovernanceAssuranceRegistry::GATE_IDS)
                && $external->every(static fn (array $check): bool => $check['status'] === 'pass'),
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_personal_data' => false,
                'stores_security_findings' => false,
                'stores_raw_urls_or_paths' => false,
                'stores_secrets_or_credentials' => false,
                'stores_reviewer_identity' => false,
                'stores_free_text' => false,
                'outputs_evidence_references' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function contractCheck(array $registry): array
    {
        $separation = $registry['role_separation'] ?? [];
        $privacy = $registry['evidence_policy'] ?? [];
        $legalLocalization = $registry['legal_localization'] ?? [];
        $passes = ($registry['contract'] ?? null) === GovernanceAssuranceRegistry::CONTRACT
            && ($registry['release_version'] ?? null) === ReleaseReadinessReport::VERSION
            && ($registry['gate_ids'] ?? null) === GovernanceAssuranceRegistry::GATE_IDS
            && ($registry['legal_requirements'] ?? null) === GovernanceAssuranceRegistry::LEGAL_KEYS
            && ($legalLocalization['contract'] ?? null) === 'localized-legal-content.v1'
            && ($legalLocalization['source_locale'] ?? null) === SupportedLocale::DEFAULT
            && ($legalLocalization['locales'] ?? null) === SupportedLocale::ALL
            && ($legalLocalization['server_only_catalogs'] ?? false) === true
            && ($legalLocalization['safe_source_fallback'] ?? false) === true
            && ($legalLocalization['external_legal_and_native_review_required'] ?? false) === true
            && ($registry['dpia_processing_families'] ?? null) === GovernanceAssuranceRegistry::DPIA_PROCESSING_KEYS
            && ($registry['dpia_required_sections'] ?? null) === GovernanceAssuranceRegistry::DPIA_SECTION_KEYS
            && ($registry['penetration_test_scope'] ?? null) === GovernanceAssuranceRegistry::PENTEST_SCOPE_KEYS
            && ($registry['penetration_test_acceptance'] ?? null) === GovernanceAssuranceRegistry::PENTEST_ACCEPTANCE_KEYS
            && ($separation['self_approval_allowed'] ?? true) === false
            && ($separation['waiver_allowed'] ?? true) === false
            && collect($privacy)->every(static fn (mixed $value): bool => is_bool($value))
            && collect($privacy)->except('short_reference_only')->every(static fn (bool $value): bool => $value === false)
            && ($privacy['short_reference_only'] ?? false) === true;

        return $this->check(
            'repository.contract',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Three existing release gates, separated approver roles, no waivers, exact release binding, and data-minimized evidence boundaries are versioned.'
                : 'The governance assurance contract, role separation, release binding, or evidence policy is incomplete.',
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
                ? count($files).' legal, privacy, guest, security, store, provider, and test artifacts are present.'
                : 'Required governance artifacts are missing: '.implode(', ', $missing),
        );
    }

    /** @param array<string, mixed> $registry */
    private function legalSurfaceCheck(array $registry): array
    {
        $missing = array_values(array_filter(
            $registry['legal_routes'] ?? [],
            static fn (string $name): bool => ! Route::has($name),
        ));
        $source = $this->source('app/Http/Controllers/LegalPageController.php');
        $localizer = $this->source('app/Services/LegalContentLocalizer.php');
        $passes = $missing === []
            && str_contains($source, 'Art. 9 Abs. 2 lit. a DSGVO')
            && str_contains($source, 'Minderjährige und Elternzustimmung')
            && str_contains($source, 'KI-gestützte Funktionen')
            && str_contains($source, 'Rechte betroffener Personen')
            && str_contains($localizer, 'localized-legal-content.v1')
            && str_contains($localizer, 'resource_path("legal/{$locale}.json")');

        return $this->check(
            'repository.legal_surfaces',
            $passes ? 'pass' : 'fail',
            $passes
                ? count($registry['legal_routes']).' public guest legal/right surfaces and sensitive-processing disclosures are registered.'
                : 'A required public legal/right route or sensitive-processing disclosure is missing.',
        );
    }

    private function legalValidatorCheck(): array
    {
        $source = $this->source('app/Console/Commands/AuditLegalReadiness.php');
        $markers = [
            "'expected_version'",
            'ReleaseReadinessReport::VERSION',
            'DateTimeImmutable::createFromFormat',
            'hash_equals',
            'config("legal.{$key}")',
        ];
        $missing = array_values(array_filter($markers, static fn (string $marker): bool => ! str_contains($source, $marker)));

        return $this->check(
            'repository.legal_validator',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? 'The legal validator checks identity placeholders, an exact current release, approval date, and version equality without printing configured values.'
                : 'The legal validator is missing release/date/identity safeguards.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function dpiaCoverageCheck(array $registry): array
    {
        $sources = $registry['authoritative_sources'] ?? [];
        $passes = count($registry['dpia_processing_families'] ?? []) === 10
            && count($registry['dpia_required_sections'] ?? []) === 9
            && str_contains((string) ($sources['gdpr'] ?? ''), 'eur-lex.europa.eu')
            && str_contains((string) ($sources['edpb_dpia'] ?? ''), 'edpb.europa.eu')
            && in_array('article_36_prior_consultation_when_residual_risk_is_high', $registry['dpia_required_sections'] ?? [], true)
            && in_array('minors_guardians_and_vulnerable_people', $registry['dpia_processing_families'] ?? [], true);

        return $this->check(
            'repository.dpia_coverage',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Ten Airmius high-risk processing families and nine Article 35/36 accountability sections are explicitly covered.'
                : 'DPIA processing-family, accountability-section, vulnerable-person, or official-source coverage is incomplete.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function penetrationCoverageCheck(array $registry): array
    {
        $sources = $registry['authoritative_sources'] ?? [];
        $passes = count($registry['penetration_test_scope'] ?? []) === 16
            && count($registry['penetration_test_acceptance'] ?? []) === 8
            && str_contains((string) ($sources['owasp_wstg'] ?? ''), 'owasp.org')
            && str_contains((string) ($sources['bsi_pentest'] ?? ''), 'bsi.bund.de')
            && in_array('public_guest_discovery_checkout_and_token_pages', $registry['penetration_test_scope'] ?? [], true)
            && in_array('critical_and_high_findings_closed_and_retested', $registry['penetration_test_acceptance'] ?? [], true)
            && in_array('independent_authorized_assessor', $registry['penetration_test_acceptance'] ?? [], true);

        return $this->check(
            'repository.penetration_test_coverage',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Sixteen web/API/mobile/business/privacy scopes and eight independent-assessment acceptance rules are OWASP/BSI-aligned.'
                : 'Pentest scope, independent assessment, retest, guest-surface, or official-methodology coverage is incomplete.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function evidenceTemplateCheck(array $registry): array
    {
        $template = $this->json(base_path('resources/release/governance_evidence.template.json'));
        $data = $template['data'];
        $passes = $template['errors'] === []
            && ($data['contract'] ?? null) === GovernanceAssuranceRegistry::CONTRACT
            && ($data['release_version'] ?? null) === ReleaseReadinessReport::VERSION
            && array_keys($data['tracks'] ?? []) === GovernanceAssuranceRegistry::GATE_IDS
            && array_keys($data['legal_requirements'] ?? []) === GovernanceAssuranceRegistry::LEGAL_KEYS
            && array_keys($data['dpia_processing_families'] ?? []) === GovernanceAssuranceRegistry::DPIA_PROCESSING_KEYS
            && array_keys($data['dpia_required_sections'] ?? []) === GovernanceAssuranceRegistry::DPIA_SECTION_KEYS
            && array_keys($data['penetration_test_scope'] ?? []) === GovernanceAssuranceRegistry::PENTEST_SCOPE_KEYS
            && array_keys($data['penetration_test_acceptance'] ?? []) === GovernanceAssuranceRegistry::PENTEST_ACCEPTANCE_KEYS
            && ! $this->containsForbiddenData($data);

        return $this->check(
            'repository.evidence_template',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'The exact legal, DPIA, pentest, remediation, release, and privacy matrix is available without findings or personal data.'
                : 'The governance evidence template is invalid, incomplete, or contains forbidden data.',
        );
    }

    private function localEvidenceCheck(): array
    {
        $path = (string) config('airmius_governance.evidence_path');
        if ($path === '' || ! is_file($path)) {
            return $this->check('local.evidence', 'pending', 'No local governance preparation evidence is present; only the three authoritative external gates can approve release.');
        }

        $evidence = $this->json($path);
        $data = $evidence['data'];
        $errors = $evidence['errors'];
        if (($data['contract'] ?? null) !== GovernanceAssuranceRegistry::CONTRACT) {
            $errors[] = 'contract';
        }
        if (($data['release_version'] ?? null) !== ReleaseReadinessReport::VERSION) {
            $errors[] = 'release_version';
        }
        if (! in_array($data['status'] ?? null, self::EVIDENCE_STATUSES, true)) {
            $errors[] = 'status';
        }
        if ($this->containsForbiddenData($data)) {
            $errors[] = 'forbidden_data';
        }
        if (($data['status'] ?? null) === 'passed' && ! $this->passedEvidenceComplete($data)) {
            $errors[] = 'coverage_or_reference';
        }
        if ($errors !== []) {
            return $this->check('local.evidence', 'fail', 'Local governance evidence is invalid: '.implode(', ', array_unique($errors)).'. Values and paths are not printed.');
        }

        $status = match ($data['status']) {
            'passed' => 'pass',
            'failed' => 'fail',
            default => 'pending',
        };

        return $this->check(
            'local.evidence',
            $status,
            'Local version-bound preparation evidence is structurally valid and non-authoritative; references are never emitted.',
        );
    }

    /** @param array{data:array<string,mixed>,errors:array<int,string>} $manifest */
    private function platformManifestCheck(array $manifest): array
    {
        $gates = is_array($manifest['data']['gates'] ?? null) ? $manifest['data']['gates'] : [];
        $ids = collect($gates)->pluck('id')->filter();
        $required = collect($gates)->whereIn('id', GovernanceAssuranceRegistry::GATE_IDS);
        $validRequired = $required->count() === count(GovernanceAssuranceRegistry::GATE_IDS)
            && $required->every(function (mixed $gate): bool {
                if (! is_array($gate)
                    || ! is_string($gate['owner'] ?? null)
                    || trim($gate['owner']) === ''
                    || ! is_string($gate['required_evidence'] ?? null)
                    || trim($gate['required_evidence']) === ''
                    || ! in_array($gate['status'] ?? null, self::MANIFEST_STATUSES, true)
                    || ! is_array($gate['evidence'] ?? null)) {
                    return false;
                }

                if (($gate['status'] ?? null) !== 'passed') {
                    return true;
                }

                return $gate['evidence'] !== []
                    && collect($gate['evidence'])->every(fn (mixed $reference): bool => $this->validReference($reference))
                    && is_string($gate['reviewed_by'] ?? null)
                    && trim($gate['reviewed_by']) !== ''
                    && ($gate['reviewed_role'] ?? null) === $this->expectedReviewRole((string) $gate['id'])
                    && $this->validReviewDate($gate['reviewed_at'] ?? null);
            });
        $passes = $manifest['errors'] === []
            && ($manifest['data']['version'] ?? null) === ReleaseReadinessReport::VERSION
            && ($manifest['data']['governance_audit_command'] ?? null) === 'php artisan airmius:audit-governance --json --strict'
            && $ids->count() === $ids->unique()->count()
            && $validRequired;

        return $this->check(
            'repository.platform_manifest',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'The three existing authoritative gates are unique, owner-bound, exact-release, evidence-bound, and coordinated by the strict governance audit.'
                : 'The authoritative governance gates are missing, duplicated, unowned, release-mismatched, or structurally invalid.',
        );
    }

    /** @param array{data:array<string,mixed>,errors:array<int,string>} $manifest
     * @return array{id:string,status:string,detail:string}
     */
    private function externalGateCheck(array $manifest, string $gateId, string $checkId): array
    {
        if ($manifest['errors'] !== [] || ($manifest['data']['version'] ?? null) !== ReleaseReadinessReport::VERSION) {
            return $this->check($checkId, 'fail', 'The authoritative platform manifest is invalid or release-mismatched.');
        }

        $gate = collect($manifest['data']['gates'] ?? [])->firstWhere('id', $gateId);
        if (! is_array($gate)) {
            return $this->check($checkId, 'fail', 'The required authoritative gate is missing.');
        }

        $status = $gate['status'] ?? 'missing';
        $evidence = is_array($gate['evidence'] ?? null) ? $gate['evidence'] : [];
        $approvalComplete = $status === 'passed'
            && $evidence !== []
            && collect($evidence)->every(fn (mixed $reference): bool => $this->validReference($reference))
            && is_string($gate['reviewed_by'] ?? null)
            && trim($gate['reviewed_by']) !== ''
            && ($gate['reviewed_role'] ?? null) === $this->expectedReviewRole($gateId)
            && $this->validReviewDate($gate['reviewed_at'] ?? null);
        $mapped = $approvalComplete
            ? 'pass'
            : (in_array($status, ['failed', 'waived', 'missing'], true) ? 'fail' : 'pending');

        return $this->check(
            $checkId,
            $mapped,
            $mapped === 'pass'
                ? 'The exact-release authoritative gate has separated human approval and non-sensitive references; identities and references are not emitted.'
                : ($status === 'waived'
                    ? 'This governance gate cannot be waived and remains a release failure.'
                    : 'The owner-bound external approval remains open; repository or local evidence cannot approve it.'),
        );
    }

    /** @param array<string, mixed> $data */
    private function passedEvidenceComplete(array $data): bool
    {
        if (! $this->validReference($data['review_reference'] ?? null)) {
            return false;
        }

        $dimensions = [
            'tracks' => GovernanceAssuranceRegistry::GATE_IDS,
            'legal_requirements' => GovernanceAssuranceRegistry::LEGAL_KEYS,
            'dpia_processing_families' => GovernanceAssuranceRegistry::DPIA_PROCESSING_KEYS,
            'dpia_required_sections' => GovernanceAssuranceRegistry::DPIA_SECTION_KEYS,
            'penetration_test_scope' => GovernanceAssuranceRegistry::PENTEST_SCOPE_KEYS,
            'penetration_test_acceptance' => GovernanceAssuranceRegistry::PENTEST_ACCEPTANCE_KEYS,
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

    private function validReviewDate(mixed $value): bool
    {
        if (! is_string($value)
            || preg_match('/\A\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2}))?\z/', $value) !== 1) {
            return false;
        }

        try {
            $date = new \DateTimeImmutable($value);

            return $date <= new \DateTimeImmutable('+5 minutes');
        } catch (\Exception) {
            return false;
        }
    }

    private function expectedReviewRole(string $gateId): ?string
    {
        $roles = GovernanceAssuranceRegistry::definitions()['role_separation'];

        return is_string($roles[$gateId] ?? null) ? $roles[$gateId] : null;
    }

    /** @param array<string, mixed> $data */
    private function containsForbiddenData(array $data): bool
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
            if (is_array($value) && $this->containsForbiddenData($value)) {
                return true;
            }
            if (is_string($value)
                && (strlen($value) > 120
                    || preg_match('#(?:https?://|airmius://|[/\\\\]|@|[?&][A-Za-z0-9_]+=)#i', $value) === 1)) {
                return true;
            }
        }

        return false;
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

    private function source(string $path): string
    {
        $source = @file_get_contents(base_path($path));

        return is_string($source) ? $source : '';
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
