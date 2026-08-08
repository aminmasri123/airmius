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

                    AppNotification::sendLocalized(
                        $invoice->user_id,
                        'subscription.invoice.reminder',
                        'notification_settings.notifications.invoice_reminder_title',
                        'notification_settings.notifications.invoice_reminder_body',
                        ['invoice' => $invoice->number],
                        ['subscription_invoice_id' => $invoice->id],
                        [
                            'dedupe_key' => 'subscription-invoice:'.$invoice->id.':overdue-reminder',
                            'priority' => 'high',
                        ],
                    );

                    $invoice->forceFill([
                        'status' => 'overdue',
                        'reminder_email_sent_at' => now(),
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => max(1, (int) $invoice->reminder_count),
                    ])->save();

                    $sent++;
                }
            });

        $this->info("Airmius Rechnungserinnerungen gesendet: {$sent}");

        return self::SUCCESS;
    }
}
