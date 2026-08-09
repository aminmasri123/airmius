<?php

namespace Tests\Feature;

use App\Services\MobilePushDeliveryService;
use App\Support\ProviderSmokeReadinessReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ProviderSmokeReadinessTest extends TestCase
{
    private ?string $credentialsPath = null;

    protected function tearDown(): void
    {
        if ($this->credentialsPath && is_file($this->credentialsPath)) {
            @unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_dry_run_is_non_mutating_and_strict_mode_keeps_missing_providers_pending(): void
    {
        $this->clearProviderConfiguration();
        Http::fake();
        Mail::fake();

        $report = app(ProviderSmokeReadinessReport::class)->make();

        $this->assertSame('provider-smoke-readiness.v1', $report['contract']);
        $this->assertSame('dry_run', $report['mode']);
        $this->assertSame('no_go', $report['decision']);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertFalse($report['evidence_complete']);
        $this->assertGreaterThan(0, $report['summary']['pending']);
        $this->assertSame(Command::SUCCESS, Artisan::call('airmius:audit-providers', ['--json' => true]));
        $this->assertSame(Command::FAILURE, Artisan::call('airmius:audit-providers', ['--json' => true, '--strict' => true]));
        Http::assertNothingSent();
    }

    public function test_full_live_staging_contract_passes_without_exposing_targets_secrets_or_provider_material(): void
    {
        $this->configureHealthyProviders();
        $this->fakeHealthyProviders();
        Mail::fake();

        $recipient = 'private-smoke-recipient@example.test';
        $token = 'private-fcm-device-token-123456789';
        $smtpReference = 'MAIL-private-reference';
        $fcmReference = 'FCM-private-reference';
        $paymentReference = 'PAY-private-reference';
        $report = app(ProviderSmokeReadinessReport::class)->make(
            true,
            'provider_smoke',
            $recipient,
            $token,
            $smtpReference,
            $fcmReference,
            $paymentReference,
        );
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('go', $report['decision'], $encoded);
        $this->assertTrue($report['automated_checks_passed']);
        $this->assertTrue($report['evidence_complete']);
        $this->assertSame(['pass' => 14, 'pending' => 0, 'fail' => 0], $report['summary']);
        $this->assertMatchesRegularExpression('/\A[A-F0-9]{16}\z/', $report['receipt_code']);
        foreach ([$recipient, $token, $smtpReference, $fcmReference, $paymentReference, 'sk_test_private', 'paypal-private-secret', 'acct_private', 'projects/airmius-smoke/messages/private'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encoded);
        }
        foreach ($report['privacy'] as $value) {
            $this->assertFalse($value);
        }

        Http::assertSentCount(7);
    }

    public function test_protected_file_command_is_strict_green_and_does_not_echo_file_or_content(): void
    {
        $this->configureHealthyProviders();
        $this->fakeHealthyProviders();
        Mail::fake();
        $recipientFile = tempnam(sys_get_temp_dir(), 'airmius-provider-mail-');
        $tokenFile = tempnam(sys_get_temp_dir(), 'airmius-provider-fcm-');
        $this->assertIsString($recipientFile);
        $this->assertIsString($tokenFile);
        $recipient = 'protected-recipient@example.test';
        $token = 'protected-fcm-device-token-123456';
        file_put_contents($recipientFile, $recipient);
        file_put_contents($tokenFile, $token);

        try {
            $exitCode = Artisan::call('airmius:audit-providers', [
                '--live' => true,
                '--smtp-mailer' => 'provider_smoke',
                '--smtp-recipient-file' => $recipientFile,
                '--fcm-token-file' => $tokenFile,
                '--smtp-receipt-reference' => 'MAIL-1234',
                '--fcm-receipt-reference' => 'FCM-1234',
                '--payment-evidence-reference' => 'PAY-1234',
                '--json' => true,
                '--strict' => true,
            ]);
            $output = Artisan::output();

            $this->assertSame(Command::SUCCESS, $exitCode, $output);
            foreach ([$recipientFile, $tokenFile, $recipient, $token, 'MAIL-1234', 'FCM-1234', 'PAY-1234'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $output);
            }
        } finally {
            @unlink($recipientFile);
            @unlink($tokenFile);
        }
    }

    public function test_live_credentials_or_targets_fail_closed_before_external_calls(): void
    {
        config([
            'services.stripe.secret' => 'sk_live_private',
            'services.stripe.webhook_secret' => 'whsec_private',
            'services.paypal.mode' => 'live',
            'services.paypal.client_id' => 'paypal-private-id',
            'services.paypal.client_secret' => 'paypal-private-secret',
            'services.paypal.webhook_id' => 'paypal-private-webhook',
            'services.paypal.commerce_webhook_id' => 'paypal-private-commerce-webhook',
            'services.paypal.outfit_webhook_id' => 'paypal-private-outfit-webhook',
        ]);
        Http::fake();

        $report = app(ProviderSmokeReadinessReport::class)->make(
            true,
            null,
            'not-an-email-private-value',
            'short-private-token',
            'https://private.example.test/?token=secret',
        );
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['automated_checks_passed']);
        $this->assertSame('fail', $this->checkStatus($report, 'input.targets'));
        $this->assertSame('fail', $this->checkStatus($report, 'provider.stripe_configuration'));
        $this->assertSame('fail', $this->checkStatus($report, 'provider.paypal_configuration'));
        $this->assertStringNotContainsString('private', strtolower($encoded));
        Http::assertNothingSent();
    }

    private function configureHealthyProviders(): void
    {
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'airmius-provider-creds-');
        $this->assertIsString($this->credentialsPath);
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'airmius-smoke',
            'client_email' => 'smoke@airmius-smoke.iam.gserviceaccount.com',
            'private_key' => 'private-key-material',
            'token_uri' => 'https://oauth.example/token',
        ], JSON_THROW_ON_ERROR));

        config([
            'mail.from.address' => 'smoke@airmius.example',
            'mail.from.name' => 'Airmius',
            'mail.mailers.provider_smoke' => [
                'transport' => 'smtp',
                'scheme' => 'tls',
                'host' => 'smtp.sandbox.example',
                'port' => 587,
                'username' => 'private-smtp-user',
                'password' => 'private-smtp-password',
            ],
            'services.mobile_push.fcm.credentials' => $this->credentialsPath,
            'services.mobile_push.fcm.project_id' => 'airmius-smoke',
            'services.stripe.secret' => 'sk_test_private',
            'services.stripe.webhook_secret' => 'whsec_private',
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'paypal-private-id',
            'services.paypal.client_secret' => 'paypal-private-secret',
            'services.paypal.webhook_id' => 'paypal-subscription-webhook',
            'services.paypal.commerce_webhook_id' => 'paypal-commerce-webhook',
            'services.paypal.outfit_webhook_id' => 'paypal-outfit-webhook',
        ]);

        app()->bind(MobilePushDeliveryService::class, fn () => new class extends MobilePushDeliveryService
        {
            protected function firebaseCredentials(): array
            {
                return [
                    'client_email' => 'smoke@airmius-smoke.iam.gserviceaccount.com',
                    'private_key' => 'unused',
                    'project_id' => 'airmius-smoke',
                    'token_uri' => 'https://oauth.example/token',
                ];
            }

            protected function firebaseAssertion(array $credentials): string
            {
                return 'signed-smoke-assertion';
            }
        });
    }

    private function clearProviderConfiguration(): void
    {
        config([
            'mail.default' => 'array',
            'airmius_mail.senders.system.mailer' => 'array',
            'services.mobile_push.fcm.credentials' => null,
            'services.mobile_push.fcm.project_id' => null,
            'services.stripe.secret' => null,
            'services.stripe.webhook_secret' => null,
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
            'services.paypal.webhook_id' => null,
            'services.paypal.commerce_webhook_id' => null,
            'services.paypal.outfit_webhook_id' => null,
        ]);
    }

    private function fakeHealthyProviders(): void
    {
        Http::fake(function ($request) {
            $url = (string) $request->url();
            if ($url === 'https://api.stripe.com/v1/account') {
                return Http::response(['id' => 'acct_private']);
            }
            if ($url === 'https://oauth.example/token') {
                return Http::response(['access_token' => 'firebase-private-access-token']);
            }
            if (str_starts_with($url, 'https://fcm.googleapis.com/')) {
                return Http::response(['name' => 'projects/airmius-smoke/messages/private']);
            }
            if ($url === 'https://api-m.sandbox.paypal.com/v1/oauth2/token') {
                return Http::response(['access_token' => 'paypal-private-access-token']);
            }
            if (str_starts_with($url, 'https://api-m.sandbox.paypal.com/v1/notifications/webhooks/')) {
                return Http::response(['id' => rawurldecode(basename($url))]);
            }

            return Http::response([], 500);
        });
    }

    /** @param array<string, mixed> $report */
    private function checkStatus(array $report, string $id): string
    {
        $check = collect($report['checks'])->firstWhere('id', $id);
        $this->assertIsArray($check, "Missing provider-smoke check: {$id}");

        return $check['status'];
    }
}
