<?php

namespace App\Console\Commands;

use App\Support\ProviderSmokeReadinessReport;
use Illuminate\Console\Command;

final class AuditProviderSmoke extends Command
{
    protected $signature = 'airmius:audit-providers
        {--live : Perform bounded outbound calls; allowed only in staging/testing and sandbox payment modes}
        {--smtp-mailer= : Configured SMTP mailer name; the value is never emitted}
        {--smtp-recipient-file= : Protected file containing one test mailbox; path and value are never emitted}
        {--fcm-token-file= : Protected file containing one consenting test-device token; path and value are never emitted}
        {--smtp-receipt-reference= : Non-sensitive mailbox receipt artifact identifier; value is never emitted}
        {--fcm-receipt-reference= : Non-sensitive real-device receipt artifact identifier; value is never emitted}
        {--payment-evidence-reference= : Non-sensitive reviewed checkout/refund/reconciliation artifact identifier; value is never emitted}
        {--json : Print machine-readable, data-minimized JSON}
        {--strict : Fail while any live provider or reviewed evidence check is pending}';

    protected $description = 'Audit SMTP, Firebase, Stripe, and PayPal staging connectivity without printing targets, credentials, payloads, responses, or errors';

    public function handle(ProviderSmokeReadinessReport $reporter): int
    {
        $report = $reporter->make(
            (bool) $this->option('live'),
            filled($this->option('smtp-mailer')) ? (string) $this->option('smtp-mailer') : null,
            $this->protectedValue('smtp-recipient-file'),
            $this->protectedValue('fcm-token-file'),
            filled($this->option('smtp-receipt-reference')) ? (string) $this->option('smtp-receipt-reference') : null,
            filled($this->option('fcm-receipt-reference')) ? (string) $this->option('fcm-receipt-reference') : null,
            filled($this->option('payment-evidence-reference')) ? (string) $this->option('payment-evidence-reference') : null,
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Check', 'Status', 'Detail'],
                collect($report['checks'])->map(static fn (array $check): array => [
                    $check['id'],
                    strtoupper($check['status']),
                    $check['detail'],
                ])->all(),
            );
            $this->line('Receipt: '.$report['receipt_code']);
        }

        if (! $report['automated_checks_passed']) {
            return self::FAILURE;
        }

        return $this->option('strict') && ! $report['evidence_complete']
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function protectedValue(string $option): ?string
    {
        $file = trim((string) $this->option($option));
        if ($file === '') {
            return null;
        }
        if (! is_file($file) || ! is_readable($file)) {
            return '__invalid_protected_provider_input__';
        }

        $value = file_get_contents($file);

        return is_string($value) ? trim($value) : '__invalid_protected_provider_input__';
    }
}
