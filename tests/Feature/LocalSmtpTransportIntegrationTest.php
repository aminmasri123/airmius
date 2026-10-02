<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('local-integration')]
class LocalSmtpTransportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ?Process $receiver = null;

    private ?string $messagePath = null;

    protected function tearDown(): void
    {
        $this->receiver?->stop();
        if ($this->messagePath !== null && is_file($this->messagePath)) {
            unlink($this->messagePath);
        }
        parent::tearDown();
    }

    private function startReceiver(bool $reject = false): void
    {
        if (getenv('RUN_LOCAL_SMTP_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_LOCAL_SMTP_TESTS=1 to exercise real loopback TCP; no external mail is sent.');
        }
        $this->messagePath = tempnam(sys_get_temp_dir(), 'airmius-smtp-');
        $this->receiver = new Process([PHP_BINARY, base_path('tests/Fixtures/local_smtp_sink.php'), $this->messagePath, $reject ? 'reject' : 'accept']);
        $this->receiver->setTimeout(20);
        $this->receiver->start();
        $this->assertTrue($this->receiver->waitUntil(fn ($type, $output) => str_contains($output, '127.0.0.1:')), 'Local SMTP receiver did not start.');
        $address = trim($this->receiver->getOutput());
        $this->assertMatchesRegularExpression('/^127\.0\.0\.1:\d+$/', $address);
        config(['mail.mailers.local_integration' => [
            'transport' => 'smtp',
            'host' => '127.0.0.1',
            'port' => (int) substr(strrchr($address, ':'), 1),
            'timeout' => 5,
            'auto_tls' => false,
        ]]);
    }

    public function test_laravel_mailer_sends_a_real_smtp_message_to_a_local_receiver(): void
    {
        $this->startReceiver();
        $result = Mail::mailer('local_integration')->raw("Controlled SMTP integration.\n.dot line", function ($mail) {
            $mail->from('sender@example.test', 'Airmius Test')
                ->to('recipient@example.test')->subject('Local integration only');
        });
        $this->assertNotNull($result);
        $message = file_get_contents($this->messagePath);
        $this->assertStringContainsString('Subject: Local integration only', $message);
        $this->assertStringContainsString('To: recipient@example.test', $message);
        $this->assertStringContainsString('Controlled SMTP integration.', $message);
        $this->assertStringContainsString("\r\n.dot line", $message);
    }

    public function test_real_smtp_recipient_rejection_is_not_reported_as_success(): void
    {
        $this->startReceiver(reject: true);
        try {
            Mail::mailer('local_integration')->raw('Must not be delivered.', function ($mail) {
                $mail->from('sender@example.test')->to('recipient@example.test')->subject('Reject');
            });
            $this->fail('SMTP rejection must throw.');
        } catch (TransportExceptionInterface $exception) {
            $this->assertStringContainsString('550', $exception->getMessage());
            $this->assertSame('', file_get_contents($this->messagePath));
        }
    }

    public function test_mobile_verification_notification_reaches_the_local_smtp_receiver(): void
    {
        $this->startReceiver();
        config([
            'mail.default' => 'local_integration',
            'airmius_mail.senders.security.address' => 'security@example.test',
            'airmius_mail.senders.security.name' => 'Airmius Security Test',
            'airmius.verification_url' => 'http://localhost',
        ]);
        $user = User::factory()->unverified()->create(['email' => 'recipient@example.test']);
        $user->notifyNow(new MobileVerifyEmail);

        $message = quoted_printable_decode(file_get_contents($this->messagePath));
        $this->assertStringContainsString('security@example.test', $message);
        $this->assertStringContainsString('recipient@example.test', $message);
        $this->assertStringContainsString(route('email.verification.bridge', [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ], absolute: false), $message);
        $this->assertStringContainsString('signature=', $message);
    }
}
