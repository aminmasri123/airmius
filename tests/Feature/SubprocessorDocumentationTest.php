<?php

namespace Tests\Feature;

use Tests\TestCase;

class SubprocessorDocumentationTest extends TestCase
{
    public function test_subprocessor_documentation_covers_mvp_provider_categories(): void
    {
        $document = file_get_contents(base_path('docs/DATA_PROCESSING_PROVIDERS.md'));

        $requiredFragments = [
            '## MVP-Status nach Kategorie',
            '| Hosting | Hostinger |',
            '| Storage/CDN/Backups | Cloudflare R2/CDN |',
            '| Mail | SMTP/Log',
            '| Payments | Stripe, PayPal',
            '| Analytics/Tracking | Keine externen Analytics',
            '| Push | FCM und APNS vorbereitet',
            '## Mail',
            '## Payments',
            '## Analytics und Tracking',
            '## Mobile Push',
            '## Realtime',
            '## Karten, Routing und Sportdaten',
            '## KI-Anbieter',
            '## Social Login, Fitness-Integrationen und externe Datenquellen',
            '## Monitoring und Betrieb',
        ];

        foreach ($requiredFragments as $fragment) {
            $this->assertStringContainsString($fragment, $document);
        }
    }

    public function test_subprocessor_documentation_matches_critical_env_switches(): void
    {
        $document = file_get_contents(base_path('docs/DATA_PROCESSING_PROVIDERS.md'));
        $env = file_get_contents(base_path('.env.example'));

        $envPairs = [
            'FILESYSTEM_DISK=r2' => 'Cloudflare R2/CDN',
            'UPLOAD_DISK=r2' => 'Cloudflare R2/CDN',
            'BACKUP_DISK=r2' => 'Cloudflare R2/CDN',
            'MAIL_MAILER=log' => '## Mail',
            'STRIPE_SECRET=' => '## Payments',
            'PAYPAL_CLIENT_ID=' => '## Payments',
            'MOBILE_PUSH_PROVIDER=fcm' => '## Mobile Push',
            'FCM_PROJECT_ID=' => '## Mobile Push',
            'APNS_KEY_ID=' => '## Mobile Push',
            'BROADCAST_CONNECTION=reverb' => '## Realtime',
            'AIRMIUS_AI_PRIMARY_PROVIDER=ionos' => '## KI-Anbieter',
            'ERROR_MONITORING_DSN=' => '## Monitoring und Betrieb',
        ];

        foreach ($envPairs as $envFragment => $documentFragment) {
            $this->assertStringContainsString($envFragment, $env);
            $this->assertStringContainsString($documentFragment, $document);
        }
    }
}
