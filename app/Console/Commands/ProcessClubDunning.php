<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\ClubDunningEvent;
use App\Models\ClubDunningRule;
use App\Notifications\ClubInvoiceReminder;
use App\Services\ClubDunningService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class ProcessClubDunning extends Command
{
    protected $signature = 'airmius:process-club-dunning
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Erzeugt fällige Mahnstufen einmalig und benachrichtigt Rechnungsempfänger.';

    public function handle(ClubDunningService $dunning, PlanFeatureService $features): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : now()->startOfDay();
        $created = 0;

        Invoice::query()
            ->with(['club.owner', 'user', 'externalMember'])
            ->whereIn('status', ['open', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $date)
            ->orderBy('id')
            ->eachById(function (Invoice $invoice) use ($dunning, $features, $date, &$created) {
                $club = $invoice->club;
                if (! $club || ! $club->owner || ! $features->allows($club, 'payment_reminders')) {
                    return;
                }

                $rule = ClubDunningRule::query()
                    ->where('club_id', $club->id)
                    ->where('is_active', true)
                    ->latest('version')
                    ->first();
                if (! $rule) {
                    return;
                }

                $overdueDays = $invoice->due_date->startOfDay()->diffInDays($date, false);
                $completedStages = ClubDunningEvent::query()
                    ->where('invoice_id', $invoice->id)
                    ->pluck('stage')
                    ->map(fn ($stage) => (int) $stage);
                $stage = collect($rule->stages)
                    ->sortBy('stage')
                    ->first(fn (array $item) => (int) ($item['days_after_due'] ?? 0) <= $overdueDays
                        && ! $completedStages->contains((int) $item['stage']));
                if (! $stage) {
                    return;
                }

                $event = $dunning->record($invoice, [
                    'rule_id' => $rule->id,
                    'stage' => (int) $stage['stage'],
                    'channel' => $stage['channel'] ?? 'email',
                    'delivery_status' => 'sent',
                    'delivered_at' => now(),
                    'evaluated_on' => $date->toDateString(),
                    'idempotency_key' => "invoice:{$invoice->id}:dunning:{$rule->version}:{$stage['stage']}",
                ], $club->owner);

                if ($invoice->user) {
                    AppNotification::sendLocalized(
                        $invoice->user,
                        'invoice.reminder',
                        'organization.notifications.invoice_reminder_title',
                        'organization.notifications.invoice_reminder_body',
                        ['invoice' => $invoice->number],
                        ['club_id' => $club->id, 'invoice_id' => $invoice->id, 'dunning_event_id' => $event->id],
                    );
                }

                $notifiable = $invoice->invoiceNotifiable();
                if ($notifiable) {
                    if ($invoice->externalMember) {
                        Notification::route('mail', $invoice->externalMember->email)
                            ->notify(new ClubInvoiceReminder($invoice, (int) $stage['stage']));
                    } else {
                        $notifiable->notify(new ClubInvoiceReminder($invoice, (int) $stage['stage']));
                    }
                }
                $created++;
            });

        $this->info("Fällige Mahnungen erstellt: {$created}.");

        return self::SUCCESS;
    }
}
