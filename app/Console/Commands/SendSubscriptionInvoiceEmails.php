<?php

namespace App\Console\Commands;

use App\Models\SubscriptionInvoice;
use App\Notifications\SubscriptionInvoiceReminder;
use App\Support\AppNotification;
use Illuminate\Console\Command;

class SendSubscriptionInvoiceEmails extends Command
{
    protected $signature = 'airmius:send-subscription-invoice-emails';

    protected $description = 'Sendet E-Mail-Erinnerungen für offene Airmius Abo-Rechnungen.';

    public function handle(): int
    {
        $sent = 0;
        $today = now()->startOfDay();

        SubscriptionInvoice::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])
            ->whereNull('reminder_email_sent_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $today)
            ->orderBy('due_at')
            ->chunkById(100, function ($invoices) use (&$sent) {
                foreach ($invoices as $invoice) {
                    if (! $invoice->user || blank($invoice->user->email)) {
                        continue;
                    }

                    $invoice->user->notify(new SubscriptionInvoiceReminder($invoice));

                    AppNotification::send($invoice->user_id, 'subscription.invoice.reminder', [
                        'title' => 'Airmius Rechnung ist offen',
                        'body' => $invoice->number.' ist fällig.',
                        'subscription_invoice_id' => $invoice->id,
                    ]);

                    $invoice->forceFill([
                        'status' => 'overdue',
                        'reminder_email_sent_at' => now(),
                    ])->save();

                    $sent++;
                }
            });

        $this->info("Airmius Rechnungserinnerungen gesendet: {$sent}");

        return self::SUCCESS;
    }
}
