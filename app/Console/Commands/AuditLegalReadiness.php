<?php

namespace App\Console\Commands;

use App\Support\ReleaseReadinessReport;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class AuditLegalReadiness extends Command
{
    protected $signature = 'airmius:audit-legal-readiness {--json}';

    protected $description = 'Fail the release gate unless legal identity and version-bound legal approval are complete.';

    public function handle(): int
    {
        $required = [
            'provider_name', 'street', 'city', 'country', 'email', 'support_email',
            'privacy_email', 'legal_email', 'phone', 'representative', 'register',
            'vat_id', 'supervisory_authority', 'content_responsible',
        ];
        $placeholders = ['offen', 'airmius', 'telefon auf anfrage', 'kein registereintrag angegeben.', 'keine umsatzsteuer-id angegeben.', 'keine besondere aufsichtsbehörde angegeben.'];
        $failures = [];

        foreach ($required as $key) {
            $value = trim((string) config("legal.{$key}"));
            if ($value === '' || in_array(mb_strtolower($value), $placeholders, true)) {
                $failures[] = 'LEGAL_'.strtoupper($key).' is missing or still a placeholder.';
            }
        }

        foreach (['approved_by', 'approved_at', 'approved_version', 'expected_version'] as $key) {
            if (blank(config("legal.release.{$key}"))) {
                $failures[] = 'LEGAL_'.strtoupper($key).' is missing.';
            }
        }

        $expectedVersion = trim((string) config('legal.release.expected_version'));
        $approvedVersion = trim((string) config('legal.release.approved_version'));
        if ($expectedVersion !== '' && ! hash_equals(ReleaseReadinessReport::VERSION, $expectedVersion)) {
            $failures[] = 'LEGAL_EXPECTED_VERSION does not match the current release contract.';
        }
        if ($expectedVersion !== '' && $approvedVersion !== '' && ! hash_equals($expectedVersion, $approvedVersion)) {
            $failures[] = 'The legal approval does not match LEGAL_EXPECTED_VERSION.';
        }

        $approvedAtValue = trim((string) config('legal.release.approved_at'));
        if ($approvedAtValue !== '') {
            $approvedAt = DateTimeImmutable::createFromFormat('!Y-m-d', $approvedAtValue);
            $validDate = $approvedAt instanceof DateTimeImmutable
                && $approvedAt->format('Y-m-d') === $approvedAtValue
                && $approvedAt <= new DateTimeImmutable('today');
            if (! $validDate) {
                $failures[] = 'LEGAL_APPROVED_AT must be a valid non-future YYYY-MM-DD date.';
            }
        }

        $result = ['status' => $failures === [] ? 'pass' : 'fail', 'failures' => $failures];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($failures === []) {
            $this->components->info('Legal release gate passed for the configured version.');
        } else {
            $this->components->error('Legal release gate failed.');
            foreach ($failures as $failure) {
                $this->line("- {$failure}");
            }
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
