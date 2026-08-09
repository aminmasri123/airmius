<?php

namespace App\Support;

use App\Services\ProviderSmokeGateway;
use Illuminate\Support\Facades\Route;
use Throwable;

final class ProviderSmokeReadinessReport
{
    public const CONTRACT = 'provider-smoke-readiness.v1';

    /** @return array<string, mixed> */
    public function make(
        bool $live = false,
        ?string $smtpMailer = null,
        ?string $smtpRecipient = null,
        ?string $fcmToken = null,
        ?string $smtpReceiptReference = null,
        ?string $fcmReceiptReference = null,
        ?string $paymentEvidenceReference = null,
    ): array {
        $receiptCode = strtoupper(bin2hex(random_bytes(8)));
        $smtpMailer = trim((string) ($smtpMailer ?: data_get(config('airmius_mail.senders.system'), 'mailer')));
        $smtpRecipient = $this->normalizeSecretInput($smtpRecipient);
        $fcmToken = $this->normalizeSecretInput($fcmToken);

        $checks = [
            $this->environmentCheck($live),
            $this->targetInputCheck($live, $smtpRecipient, $fcmToken),
            $this->localContractCheck(),
            $this->smtpConfigurationCheck($smtpMailer),
            $this->firebaseConfigurationCheck(),
            $this->stripeConfigurationCheck(),
            $this->paypalConfigurationCheck(),
        ];

        $unsafe = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'fail');
        $gateway = app(ProviderSmokeGateway::class);
        $checks[] = $this->liveCheck(
            'live.smtp_delivery',
            $live,
            ! $unsafe && $this->status($checks, 'provider.smtp_configuration') === 'pass' && $smtpRecipient !== null,
            fn () => $gateway->sendSmtpReceipt($smtpMailer, (string) $smtpRecipient, $receiptCode),
            'The SMTP provider accepted one neutral receipt message.',
            'SMTP delivery is pending a safe target, complete configuration, and live staging execution.',
        );
        $checks[] = $this->liveCheck(
            'live.firebase_delivery',
            $live,
            ! $unsafe && $this->status($checks, 'provider.firebase_configuration') === 'pass' && $fcmToken !== null,
            fn () => $gateway->sendFirebaseReceipt((string) $fcmToken, $receiptCode),
            'Firebase accepted one neutral cross-platform receipt notification.',
            'Firebase delivery is pending a safe device token, complete configuration, and live staging execution.',
        );
        $checks[] = $this->liveCheck(
            'live.stripe_connectivity',
            $live,
            ! $unsafe && $this->status($checks, 'provider.stripe_configuration') === 'pass',
            fn () => $gateway->verifyStripeSandbox(),
            'Stripe test-account authentication and the local signed-webhook contract passed without creating a payment.',
            'Stripe connectivity is pending complete test credentials and live staging execution.',
        );
        $checks[] = $this->liveCheck(
            'live.paypal_connectivity',
            $live,
            ! $unsafe && $this->status($checks, 'provider.paypal_configuration') === 'pass',
            fn () => $gateway->verifyPayPalSandbox(),
            'PayPal sandbox authentication and configured webhook registrations passed without creating a payment.',
            'PayPal connectivity is pending complete sandbox credentials and live staging execution.',
        );
        $checks[] = $this->referenceCheck('evidence.smtp_receipt', $smtpReceiptReference, 'SMTP mailbox receipt');
        $checks[] = $this->referenceCheck('evidence.firebase_receipt', $fcmReceiptReference, 'Firebase real-device receipt');
        $checks[] = $this->referenceCheck('evidence.payment_workflows', $paymentEvidenceReference, 'Stripe/PayPal checkout, webhook, retry, refund, and reconciliation review');

        return $this->report($checks, $live, $receiptCode);
    }

    /** @return array{id:string,status:string,detail:string} */
    private function environmentCheck(bool $live): array
    {
        $safe = ! $live || app()->environment(['staging', 'testing']);

        return $this->check(
            'input.environment',
            $safe ? 'pass' : 'fail',
            $safe
                ? 'Dry-run/live mode and the staging-only outbound-call guard are valid.'
                : 'Live provider calls are permitted only when APP_ENV is staging or testing.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function targetInputCheck(bool $live, ?string $smtpRecipient, ?string $fcmToken): array
    {
        $recipientValid = $smtpRecipient === null
            || filter_var($smtpRecipient, FILTER_VALIDATE_EMAIL) !== false;
        $tokenValid = $fcmToken === null
            || preg_match('/\A[A-Za-z0-9:_-]{16,4096}\z/', $fcmToken) === 1;
        $valid = $recipientValid && $tokenValid;

        return $this->check(
            'input.targets',
            $valid ? 'pass' : 'fail',
            $valid
                ? ($live ? 'Protected target inputs are syntactically safe; their values are not emitted.' : 'No outbound target is used in dry-run mode.')
                : 'Recipient or device-token input is invalid. File paths and values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function localContractCheck(): array
    {
        $routes = [
            'webhooks.stripe',
            'webhooks.paypal',
            'webhooks.commerce.stripe',
            'webhooks.commerce.paypal',
            'webhooks.outfit-subscriptions.paypal',
        ];
        $files = [
            'app/Services/MobilePushDeliveryService.php',
            'app/Services/CommerceRefundService.php',
            'app/Support/PaymentWebhookVerifier.php',
            'app/Support/TransactionalMail.php',
            'tests/Feature/PaymentWebhookSignatureTest.php',
            'tests/Feature/MobilePushDeliveryServiceTest.php',
            'tests/Feature/CommerceRefundReconciliationTest.php',
            'tests/Feature/ProviderResilienceContractTest.php',
        ];
        $passes = collect($routes)->every(fn (string $route): bool => Route::has($route))
            && collect($files)->every(fn (string $file): bool => is_file(base_path($file)));

        return $this->check(
            'contract.local_resilience',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Webhook signatures, mail fallback, push retry/device invalidation, refund idempotency, and local-state isolation have executable contracts.'
                : 'A required provider route, implementation, or resilience contract is missing.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function smtpConfigurationCheck(string $mailer): array
    {
        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $mailer) !== 1) {
            return $this->check('provider.smtp_configuration', 'fail', 'The selected mailer name is invalid; its value is not emitted.');
        }

        $config = config("mail.mailers.{$mailer}");
        if (! is_array($config)) {
            return $this->check('provider.smtp_configuration', 'pending', 'A configured SMTP smoke mailer is required.');
        }

        $host = strtolower(trim((string) ($config['host'] ?? '')));
        $port = (int) ($config['port'] ?? 0);
        $scheme = strtolower(trim((string) ($config['scheme'] ?? '')));
        $ready = ($config['transport'] ?? null) === 'smtp'
            && $host !== ''
            && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            && $port > 0
            && (in_array($scheme, ['tls', 'smtps'], true) || in_array($port, [465, 587], true))
            && filled($config['username'] ?? null)
            && filled($config['password'] ?? null)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;

        return $this->check(
            'provider.smtp_configuration',
            $ready ? 'pass' : 'pending',
            $ready
                ? 'An authenticated encrypted remote SMTP transport and valid sender are configured.'
                : 'Configure an authenticated encrypted remote SMTP transport and valid sender before the live smoke.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function firebaseConfigurationCheck(): array
    {
        $path = trim((string) config('services.mobile_push.fcm.credentials'));
        $projectId = trim((string) config('services.mobile_push.fcm.project_id'));
        if ($path === '' && $projectId === '') {
            return $this->check('provider.firebase_configuration', 'pending', 'Firebase service-account credentials and project ID are required.');
        }
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return $this->check('provider.firebase_configuration', 'fail', 'The Firebase credential file is missing or unreadable; its path is not emitted.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);
        $ready = is_array($credentials)
            && filled($credentials['client_email'] ?? null)
            && filled($credentials['private_key'] ?? null)
            && filled($credentials['project_id'] ?? null)
            && $projectId !== ''
            && hash_equals((string) $credentials['project_id'], $projectId);

        return $this->check(
            'provider.firebase_configuration',
            $ready ? 'pass' : 'fail',
            $ready
                ? 'Firebase service account and configured project are structurally consistent.'
                : 'Firebase service account and project configuration are incomplete or inconsistent; values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function stripeConfigurationCheck(): array
    {
        $secret = trim((string) config('services.stripe.secret'));
        $webhookSecret = trim((string) config('services.stripe.webhook_secret'));
        if ($secret === '' && $webhookSecret === '') {
            return $this->check('provider.stripe_configuration', 'pending', 'Stripe test and webhook credentials are required.');
        }

        $safe = (str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'rk_test_'))
            && str_starts_with($webhookSecret, 'whsec_');

        return $this->check(
            'provider.stripe_configuration',
            $safe ? 'pass' : 'fail',
            $safe
                ? 'Stripe test credentials and webhook secret are configured.'
                : 'Only Stripe test credentials are allowed in this smoke audit; values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function paypalConfigurationCheck(): array
    {
        $values = collect([
            config('services.paypal.client_id'),
            config('services.paypal.client_secret'),
            config('services.paypal.webhook_id'),
            config('services.paypal.commerce_webhook_id'),
            config('services.paypal.outfit_webhook_id'),
        ]);
        if ($values->filter()->isEmpty()) {
            return $this->check('provider.paypal_configuration', 'pending', 'PayPal sandbox credentials and all webhook contexts are required.');
        }

        $safe = config('services.paypal.mode') === 'sandbox'
            && $values->every(fn ($value): bool => filled($value));

        return $this->check(
            'provider.paypal_configuration',
            $safe ? 'pass' : 'fail',
            $safe
                ? 'PayPal sandbox credentials and all webhook contexts are configured.'
                : 'Only complete PayPal sandbox configuration is allowed; values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function liveCheck(
        string $id,
        bool $live,
        bool $ready,
        callable $operation,
        string $success,
        string $pending,
    ): array {
        if (! $live || ! $ready) {
            return $this->check($id, 'pending', $pending);
        }

        try {
            $operation();

            return $this->check($id, 'pass', $success);
        } catch (Throwable) {
            return $this->check($id, 'fail', 'The provider smoke operation failed; target, credentials, response, and error details are not emitted.');
        }
    }

    /** @return array{id:string,status:string,detail:string} */
    private function referenceCheck(string $id, ?string $reference, string $label): array
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return $this->check($id, 'pending', $label.' evidence is pending.');
        }

        $valid = preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{2,119}\z/', $reference) === 1;

        return $this->check(
            $id,
            $valid ? 'pass' : 'fail',
            $valid ? $label.' evidence reference is present; its value is not emitted.' : $label.' reference must be a short non-sensitive artifact identifier.',
        );
    }

    private function normalizeSecretInput(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<int, array{id:string,status:string,detail:string}> $checks */
    private function status(array $checks, string $id): ?string
    {
        return collect($checks)->firstWhere('id', $id)['status'] ?? null;
    }

    /** @param array<int, array{id:string,status:string,detail:string}> $checks */
    private function report(array $checks, bool $live, string $receiptCode): array
    {
        $summary = [
            'pass' => collect($checks)->where('status', 'pass')->count(),
            'pending' => collect($checks)->where('status', 'pending')->count(),
            'fail' => collect($checks)->where('status', 'fail')->count(),
        ];
        $automatedChecksPassed = $summary['fail'] === 0;
        $evidenceComplete = $live
            && $automatedChecksPassed
            && $summary['pending'] === 0;

        return [
            'contract' => self::CONTRACT,
            'generated_at' => now()->toIso8601String(),
            'receipt_code' => $receiptCode,
            'mode' => $live ? 'live_staging' : 'dry_run',
            'decision' => $evidenceComplete ? 'go' : 'no_go',
            'automated_checks_passed' => $automatedChecksPassed,
            'evidence_complete' => $evidenceComplete,
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_recipients' => false,
                'stores_device_tokens' => false,
                'stores_credentials' => false,
                'stores_provider_payloads' => false,
                'stores_provider_responses' => false,
                'stores_error_details' => false,
                'stores_personal_data' => false,
            ],
        ];
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
