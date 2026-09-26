<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Throwable;

final class ClubSepaNoticeDeliveryReadinessReport
{
    public const CONTRACT = 'club-sepa-notice-delivery-rollout.v2';

    public const SURFACES = ['web_desktop', 'web_mobile', 'android', 'ios'];

    public const JOURNEYS = [
        'provider_transport_acceptance',
        'provider_message_id_match',
        'provider_webhook_registration',
        'delivery_callback',
        'duplicate_callback',
        'controlled_bounce',
        'webhook_auth_rejection',
        'trusted_proxy_ip_handling',
        'queue_worker_restart',
        'payer_fallback_process',
    ];

    public function make(bool $withRuntime = false, ?string $evidencePath = null): array
    {
        $checks = [$this->repositoryCheck(), $this->evidenceTemplateCheck()];
        $inventory = null;

        if ($withRuntime) {
            [$runtimeChecks, $inventory] = $this->runtimeChecks();
            array_push($checks, ...$runtimeChecks);
        } else {
            $checks[] = $this->check('runtime.inspection', 'pending', 'Runtime configuration and aggregate delivery state were not inspected. Run with --with-runtime in the reviewed environment.');
        }

        $checks[] = $this->evidenceCheck($evidencePath);
        $automatedChecksPassed = collect($checks)
            ->reject(fn (array $check) => in_array($check['id'], ['local.evidence', 'runtime.inspection'], true) && $check['status'] === 'pending')
            ->every(fn (array $check) => $check['status'] !== 'fail');
        $evidencePassed = collect($checks)->firstWhere('id', 'local.evidence')['status'] === 'pass';

        return [
            'contract' => self::CONTRACT,
            'generated_at' => now()->toIso8601String(),
            'mode' => $withRuntime ? 'runtime-read-only' : 'repository-only',
            'automated_checks_passed' => $automatedChecksPassed,
            'decision' => $automatedChecksPassed && $withRuntime && $evidencePassed ? 'go' : 'no-go',
            'inventory' => $inventory,
            'limitations' => [
                'Configured credentials and a durable queue driver do not prove that a worker is running.',
                'Postmark webhook registration, real delivery, controlled bounce and proxy-aware IP handling require reviewed staging evidence.',
                'A provider delivery callback is not proof that the recipient read or personally received the message.',
            ],
            'checks' => $checks,
        ];
    }

    private function repositoryCheck(): array
    {
        $paths = [
            'database/migrations/2026_09_25_000001_add_provider_feedback_to_club_sepa_notices.php',
            'app/Http/Controllers/Webhooks/PostmarkMailWebhookController.php',
            'app/Services/PostmarkSepaNoticeWebhookService.php',
            'app/Models/ClubSepaNoticeProviderEvent.php',
            'docs/CLUB_SEPA_NOTICE_DELIVERY_FEEDBACK.md',
            'resources/release/club_sepa_notice_delivery_evidence.template.json',
        ];
        $missing = array_values(array_filter($paths, fn (string $path) => ! is_file(base_path($path))));
        $dependencies = class_exists(PostmarkApiTransport::class)
            && class_exists(HttpClient::class);
        $routeReady = Route::has('webhooks.mail.postmark')
            && Route::getRoutes()->getByName('webhooks.mail.postmark')?->methods() === ['POST'];
        $ready = $missing === [] && $dependencies && $routeReady;

        return $this->check(
            'repository.contract',
            $ready ? 'pass' : 'fail',
            $ready
                ? 'Postmark transport, callback route, durable event storage and rollout artifacts are present.'
                : count($missing).' required artifacts are missing, or the Postmark dependency/route contract is unavailable.',
        );
    }

    private function evidenceTemplateCheck(): array
    {
        $data = $this->json(base_path('resources/release/club_sepa_notice_delivery_evidence.template.json'));
        $valid = is_array($data)
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS
            && ($data['contract'] ?? null) === self::CONTRACT;

        return $this->check(
            'repository.evidence_template',
            $valid ? 'pass' : 'fail',
            $valid ? 'The versioned delivery, bounce, queue and device evidence template is complete.' : 'The evidence template is missing or invalid.',
        );
    }

    private function runtimeChecks(): array
    {
        $checks = [];
        $mailer = $this->effectiveBillingMailer();
        $mailerReady = $mailer !== null && config("mail.mailers.{$mailer}.transport") === 'postmark';
        $checks[] = $this->check('runtime.billing_mailer', $mailerReady ? 'pass' : 'fail', $mailerReady
            ? 'The effective billing sender uses the Postmark API transport.'
            : 'The effective billing sender does not use the Postmark API transport.');

        $key = trim((string) config('services.postmark.key'));
        $username = trim((string) config('services.postmark.webhook_username'));
        $password = trim((string) config('services.postmark.webhook_password'));
        $credentialsReady = strlen($key) >= 10 && strlen($username) >= 12 && strlen($password) >= 20 && ! hash_equals($username, $password);
        $checks[] = $this->check('runtime.postmark_credentials', $credentialsReady ? 'pass' : 'fail', $credentialsReady
            ? 'Postmark and independent webhook credentials are configured; values were not read into the report.'
            : 'Postmark or independent webhook credentials are missing or do not meet the rollout baseline.');

        $connection = (string) config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        $workerBacked = is_string($driver) && ! in_array($driver, ['sync', 'null', 'deferred', 'background'], true);
        $checks[] = $this->check('runtime.queue_connection', $workerBacked ? 'pass' : 'fail', $workerBacked
            ? 'A worker-backed queue connection is configured.'
            : 'A durable worker-backed queue connection is required.');

        $required = [
            'club_sepa_notices' => ['provider_status', 'delivered_at', 'bounced_at', 'bounce_type', 'message_id'],
            'club_sepa_notice_provider_events' => ['club_sepa_notice_id', 'provider', 'event_key', 'event_type', 'occurred_at'],
        ];
        if ($driver === 'database') {
            $required['jobs'] = ['id', 'queue', 'payload', 'attempts', 'available_at'];
            $required['failed_jobs'] = ['id', 'uuid', 'queue', 'failed_at'];
        }

        try {
            $missing = [];
            foreach ($required as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    $missing[] = $table;

                    continue;
                }
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missing[] = $table.'.'.$column;
                    }
                }
            }
            if ($missing !== []) {
                $checks[] = $this->check('runtime.schema', 'fail', count($missing).' required queue or provider-feedback schema elements are missing.');

                return [$checks, null];
            }

            $inventory = [
                'webhook_ip_allowlist_enabled' => config('services.postmark.webhook_ips', []) !== [],
                'notices_awaiting_provider_feedback' => DB::table('club_sepa_notices')->where('provider_status', 'pending')->count(),
                'notices_reported_delivered' => DB::table('club_sepa_notices')->where('provider_status', 'delivered')->count(),
                'notices_reported_bounced' => DB::table('club_sepa_notices')->where('provider_status', 'bounced')->count(),
                'provider_events' => DB::table('club_sepa_notice_provider_events')->count(),
            ];
            $checks[] = $this->check('runtime.schema', 'pass', 'Provider-feedback storage and required queue tables are present.');
            $checks[] = $this->check('runtime.aggregate_inventory', 'pass', 'Aggregate delivery state was inspected without emitting identifiers, recipients, messages or provider payloads.');

            return [$checks, $inventory];
        } catch (Throwable) {
            $checks[] = $this->check('runtime.inspection', 'fail', 'The configured database is unavailable or the read-only aggregate inspection failed.');

            return [$checks, null];
        }
    }

    private function effectiveBillingMailer(): ?string
    {
        $category = 'billing';
        try {
            if (Schema::hasTable('mail_sender_settings')) {
                $stored = DB::table('mail_sender_settings')->where('category', 'billing')->first(['mailer', 'active']);
                if ($stored) {
                    if ((bool) $stored->active) {
                        return is_string($stored->mailer) ? $stored->mailer : null;
                    }
                    $category = 'system';
                }
            }
        } catch (Throwable) {
            return null;
        }

        $mailer = config("airmius_mail.senders.{$category}.mailer");

        return is_string($mailer) && $mailer !== '' ? $mailer : null;
    }

    private function evidenceCheck(?string $path): array
    {
        if (! $path) {
            return $this->check('local.evidence', 'pending', 'No reviewed staging delivery, bounce, queue and device evidence file was supplied.');
        }
        $data = $this->json($path);
        $shape = is_array($data)
            && array_keys($data) === ['contract', 'status', 'environment', 'migration', 'runtime', 'surfaces', 'journeys', 'approvals', 'evidence_references']
            && ($data['contract'] ?? null) === self::CONTRACT
            && ($data['environment'] ?? null) === 'staging'
            && array_keys($data['migration'] ?? []) === ['backup_verified', 'dry_run_passed', 'rollback_rehearsed']
            && array_keys($data['runtime'] ?? []) === ['postmark_configured', 'queue_worker_verified']
            && array_keys($data['surfaces'] ?? []) === self::SURFACES
            && array_keys($data['journeys'] ?? []) === self::JOURNEYS
            && array_keys($data['approvals'] ?? []) === ['finance_owner', 'engineering'];
        $statuses = [...array_values($data['surfaces'] ?? []), ...array_values($data['journeys'] ?? [])];
        $passed = $shape
            && ($data['status'] ?? null) === 'passed'
            && collect($data['migration'] ?? [])->every(fn ($value) => $value === true)
            && collect($data['runtime'] ?? [])->every(fn ($value) => $value === true)
            && collect($data['approvals'] ?? [])->every(fn ($value) => $value === true)
            && $statuses !== [] && collect($statuses)->every(fn ($value) => $value === 'passed')
            && $this->validReferences($data['evidence_references'] ?? []);

        return $this->check('local.evidence', $passed ? 'pass' : 'pending', $passed
            ? 'Reviewed staging delivery, bounce, queue, browser, device and owner evidence is complete.'
            : 'Reviewed staging delivery, bounce, queue, browser, device or owner evidence remains open.');
    }

    private function validReferences(mixed $references): bool
    {
        return is_array($references) && $references !== []
            && collect($references)->every(fn ($reference) => $this->validReference($reference));
    }

    private function validReference(mixed $reference): bool
    {
        if (! is_string($reference) || preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,80}$/i', $reference) !== 1) {
            return false;
        }

        $normalized = strtolower($reference);

        return preg_match('/^[0-9a-f]{32,80}$/', $normalized) !== 1
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $normalized) !== 1;
    }

    private function json(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
