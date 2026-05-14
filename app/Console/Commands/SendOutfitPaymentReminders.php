<?php

namespace App\Console\Commands;

use App\Models\OutfitSubscription;
use App\Services\OutfitPaymentReminderService;
use Illuminate\Console\Command;

class SendOutfitPaymentReminders extends Command
{
    protected $signature = 'airmius:send-outfit-payment-reminders';

    protected $description = 'Sendet faellige Outfit-Abo-Zahlungserinnerungen.';

    public function __construct(private OutfitPaymentReminderService $reminders)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;
        $expired = 0;
        $dunning = 0;

        OutfitSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name'])
            ->whereIn('payment_status', ['pending', 'failed'])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$sent, &$expired, &$dunning) {
                $subscriptions->each(function (OutfitSubscription $subscription) use (&$sent, &$expired, &$dunning) {
                    if ($this->reminders->isDueForAutomaticDunning($subscription)) {
                        $dunning += $this->reminders->sendDunning($subscription) ? 1 : 0;

                        return;
                    }

                    if ($this->reminders->isExpiredUnpaid($subscription)) {
                        $expired += $this->reminders->expire($subscription) ? 1 : 0;

                        return;
                    }

                    if ($this->reminders->isDueForAutomaticReminder($subscription)) {
                        $sent += $this->reminders->send($subscription) ? 1 : 0;
                    }
                });
            });

        $this->info("{$sent} Outfit-Zahlungserinnerungen versendet. {$dunning} Mahnungen versendet. {$expired} unbezahlte Outfit-Anfragen geloescht.");

        return self::SUCCESS;
    }
}
