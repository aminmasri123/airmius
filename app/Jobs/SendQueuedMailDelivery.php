<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\MailDelivery;
use App\Models\User;
use App\Notifications\AdminInvoiceCreated;
use App\Notifications\AdminInvoiceStatusUpdated;
use App\Notifications\ClubInvoiceCreated;
use App\Notifications\InactiveAccountNotice;
use App\Notifications\ScheduledCommunicationMail;
use App\Support\TransactionalMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SendQueuedMailDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $mailDeliveryId) {}

    public function handle(TransactionalMail $mail): void
    {
        $delivery = MailDelivery::query()->find($this->mailDeliveryId);
        if (! $delivery || $delivery->status !== 'queued') {
            return;
        }

        if ($delivery->next_attempt_at && $delivery->next_attempt_at->isFuture()) {
            self::dispatch($delivery->id)->delay($delivery->next_attempt_at);

            return;
        }

        $recipient = $delivery->recipient_id
            ? User::query()->find($delivery->recipient_id)
            : null;

        if (! $recipient?->email || ! hash_equals(strtolower((string) $recipient->email), strtolower((string) $delivery->recipient_email))) {
            $delivery->update([
                'status' => 'skipped',
                'error_message' => 'Recipient missing or no longer matches delivery.',
                'failed_at' => now(),
            ]);

            return;
        }

        $transport = $mail->transportFor($delivery->primary_category ?: 'system');

        try {
            $delivery->increment('attempts');
            $delivery->forceFill([
                'last_attempt_at' => now(),
                'next_attempt_at' => null,
                'used_category' => $transport['category'] ?? null,
                'mailer' => $transport['mailer'] ?? null,
                'from_address' => $transport['address'] ?? null,
                'adapter' => $transport['mailer'] ?? null,
            ])->save();

            $recipient->notify($this->notificationFor($delivery, $transport));

            $delivery->update([
                'status' => 'sent',
                'provider_status' => 'accepted',
                'error_message' => null,
                'sent_at' => now(),
                'failed_at' => null,
            ]);
        } catch (Throwable $exception) {
            $maxAttempts = max(1, (int) config('airmius_mail.max_delivery_attempts', 5));
            $willRetry = $delivery->attempts < $maxAttempts;

            $delivery->update([
                'status' => $willRetry ? 'queued' : 'failed',
                'provider_status' => $willRetry ? 'retrying' : 'failed',
                'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                'next_attempt_at' => $willRetry ? now()->addSeconds($this->retryDelay($delivery->attempts)) : null,
                'failed_at' => $willRetry ? null : now(),
            ]);

            if ($willRetry) {
                self::dispatch($delivery->id)->delay($delivery->next_attempt_at);
            }
        }
    }

    private function notificationFor(MailDelivery $delivery, array $transport): object
    {
        return match (true) {
            str_starts_with((string) $delivery->mail_type, 'inactive_account.') => new InactiveAccountNotice(
                str($delivery->mail_type)->after('inactive_account.')->toString(),
                $delivery->context['scheduled_at'] ?? null,
                $transport['mailer'] ?? null,
                $transport['address'] ?? null,
                $transport['name'] ?? null,
            ),
            $delivery->mail_type === 'communication.scheduled' => new ScheduledCommunicationMail(
                (string) $delivery->subject,
                (string) $delivery->body,
                $transport['mailer'] ?? null,
                $transport['address'] ?? null,
                $transport['name'] ?? null,
            ),
            $delivery->mail_type === 'invoice.created' => new AdminInvoiceCreated(
                $this->invoiceFor($delivery),
                $transport['mailer'] ?? null,
                $transport['address'] ?? null,
                $transport['name'] ?? null,
            ),
            $delivery->mail_type === 'invoice.status_updated' => new AdminInvoiceStatusUpdated(
                $this->invoiceFor($delivery),
                $delivery->context['old_status'] ?? null,
                $transport['mailer'] ?? null,
                $transport['address'] ?? null,
                $transport['name'] ?? null,
            ),
            in_array($delivery->mail_type, ['club.invoice.created', 'recurring_contribution.invoice.created'], true) => new ClubInvoiceCreated(
                $this->invoiceFor($delivery)->loadMissing('club'),
                $transport['mailer'] ?? null,
                $transport['address'] ?? null,
                $transport['name'] ?? null,
            ),
            default => throw new \RuntimeException('Unsupported queued mail type: '.$delivery->mail_type),
        };
    }

    private function invoiceFor(MailDelivery $delivery): Invoice
    {
        $invoice = Invoice::query()->find($delivery->context['invoice_id'] ?? null);
        if (! $invoice) {
            throw new \RuntimeException('Queued mail invoice is missing.');
        }

        return $invoice;
    }

    private function retryDelay(int $attempts): int
    {
        return min(3600, 60 * (2 ** max(0, $attempts - 1)));
    }
}
