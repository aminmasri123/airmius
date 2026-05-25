<?php

namespace App\Console\Commands;

use App\Models\ClubSubscription;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionInvoiceReminder;
use App\Support\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'airmius:process-subscription-lifecycle
        {--grace-days=9 : Tage bis zur Zugriffseinschraenkung nach Fälligkeit}
        {--reminder-days=3 : Abstand zwischen Mahnungen}
        {--dry-run : Nur zaehlen, nichts speichern oder senden}';

    protected $description = 'Erzeugt wiederkehrende Abo-Rechnungen, mahnt offene Zahlungen und finalisiert Kündigungen.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now()->startOfDay();
        $stats = [
            'invoices' => 0,
            'reminders' => 0,
            'past_due' => 0,
            'restricted' => 0,
            'cancelled' => 0,
        ];

        $stats['cancelled'] += $this->finalizeCancellations(ClubSubscription::class, 'club', $today, $dryRun);
        $stats['cancelled'] += $this->finalizeCancellations(UserSubscription::class, 'user', $today, $dryRun);

        $stats['invoices'] += $this->generateInvoices(ClubSubscription::class, 'club', $today, $dryRun);
        $stats['invoices'] += $this->generateInvoices(UserSubscription::class, 'user', $today, $dryRun);

        $overdue = $this->processOverdueInvoices($today, $dryRun);
        $stats['reminders'] += $overdue['reminders'];
        $stats['past_due'] += $overdue['past_due'];

        $stats['restricted'] += $this->restrictPastDueSubscriptions(ClubSubscription::class, 'club', $today, $dryRun);
        $stats['restricted'] += $this->restrictPastDueSubscriptions(UserSubscription::class, 'user', $today, $dryRun);

        $this->info(sprintf(
            'Abo-Lifecycle%s: %d Rechnungen, %d Mahnungen, %d past_due, %d eingeschraenkt, %d beendet.',
            $dryRun ? ' (dry-run)' : '',
            $stats['invoices'],
            $stats['reminders'],
            $stats['past_due'],
            $stats['restricted'],
            $stats['cancelled'],
        ));

        return self::SUCCESS;
    }

    private function finalizeCancellations(string $modelClass, string $subscriptionType, Carbon $today, bool $dryRun): int
    {
        $count = 0;

        $modelClass::query()
            ->where('status', 'cancels_at_period_end')
            ->whereNotNull('cancels_at')
            ->where('cancels_at', '<=', $today)
            ->chunkById(100, function ($subscriptions) use (&$count, $subscriptionType, $dryRun) {
                foreach ($subscriptions as $subscription) {
                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    $subscription->forceFill([
                        'status' => 'cancelled',
                        'cancel_at_period_end' => false,
                        'cancelled_at' => now(),
                        'next_invoice_at' => null,
                        'grace_period_ends_at' => null,
                        'access_restricted_at' => null,
                    ])->save();

                    $this->notifySubscriptionOwner($subscription, $subscriptionType, 'subscription.ended', [
                        'title' => 'Abo beendet',
                        'body' => 'Dein Abo wurde zum Kündigungsdatum beendet.',
                        'subscription_type' => $subscriptionType,
                        'subscription_id' => $subscription->id,
                    ]);
                }
            });

        return $count;
    }

    private function generateInvoices(string $modelClass, string $subscriptionType, Carbon $today, bool $dryRun): int
    {
        $count = 0;

        $modelClass::query()
            ->with(['plan', $subscriptionType === 'club' ? 'club.owner' : 'user'])
            ->where('status', 'active')
            ->where(function ($query) use ($today) {
                $query->whereNotNull('next_invoice_at')->where('next_invoice_at', '<=', $today)
                    ->orWhere(function ($fallback) use ($today) {
                        $fallback->whereNull('next_invoice_at')
                            ->whereNotNull('current_period_ends_at')
                            ->where('current_period_ends_at', '<=', $today);
                    });
            })
            ->chunkById(100, function ($subscriptions) use (&$count, $subscriptionType, $dryRun) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->plan) {
                        continue;
                    }

                    $periodStart = $this->periodStart($subscription);
                    $periodEnd = $this->periodEnd($periodStart, $subscription->billing_interval);
                    $amountCents = $this->amountCents($subscription);

                    if ($amountCents <= 0) {
                        if (! $dryRun) {
                            $subscription->forceFill([
                                'current_period_ends_at' => $periodEnd,
                                'next_invoice_at' => $periodEnd,
                                'last_renewed_at' => now(),
                            ])->save();
                        }

                        continue;
                    }

                    $existing = SubscriptionInvoice::query()
                        ->where('subscription_type', $subscriptionType)
                        ->where('subscription_id', $subscription->id)
                        ->whereDate('billing_period_start', $periodStart->toDateString())
                        ->exists();

                    if (! $existing) {
                        $billingUserId = $this->billingUserId($subscription, $subscriptionType);

                        if (! $billingUserId) {
                            continue;
                        }

                        $count++;

                        if (! $dryRun) {
                            SubscriptionInvoice::query()->create([
                                'user_id' => $billingUserId,
                                'club_id' => $subscriptionType === 'club' ? $subscription->club_id : null,
                                'subscription_plan_id' => $subscription->subscription_plan_id,
                                'subscription_type' => $subscriptionType,
                                'subscription_id' => $subscription->id,
                                'number' => $this->nextInvoiceNumber(),
                                'title' => 'Airmius '.$subscription->plan->name,
                                'description' => 'Wiederkehrendes Abo '.$subscription->plan->name.' ('.$this->intervalLabel($subscription->billing_interval).')',
                                'amount_cents' => $amountCents,
                                'currency' => $subscription->plan->currency ?: 'EUR',
                                'status' => $this->paymentMethod($subscription) === 'bank_transfer' ? 'awaiting_transfer' : 'open',
                                'payment_method' => $this->paymentMethod($subscription),
                                'payment_reference' => $this->paymentReference($subscriptionType, $subscription->id),
                                'billing_period_start' => $periodStart->toDateString(),
                                'billing_period_end' => $periodEnd->toDateString(),
                                'issued_at' => now(),
                                'due_at' => now()->addDays((int) Setting::valueFor('billing_payment_terms_days', 14)),
                                'meta' => [
                                    'recurring' => true,
                                    'billing_interval' => $this->billingInterval($subscription),
                                ],
                            ]);
                        }
                    }

                    if (! $dryRun) {
                        $subscription->forceFill([
                            'current_period_ends_at' => $periodEnd,
                            'next_invoice_at' => $periodEnd,
                            'last_renewed_at' => now(),
                        ])->save();
                    }
                }
            });

        return $count;
    }

    private function processOverdueInvoices(Carbon $today, bool $dryRun): array
    {
        $stats = ['reminders' => 0, 'past_due' => 0];
        $reminderDays = max(1, (int) $this->option('reminder-days'));
        $graceDays = max(1, (int) $this->option('grace-days'));

        SubscriptionInvoice::query()
            ->with(['user:id,name,email'])
            ->whereNotNull('subscription_type')
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['open', 'awaiting_transfer', 'overdue'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $today)
            ->orderBy('due_at')
            ->chunkById(100, function ($invoices) use (&$stats, $today, $dryRun, $reminderDays, $graceDays) {
                foreach ($invoices as $invoice) {
                    $subscription = $this->subscriptionForInvoice($invoice);

                    if ($subscription && $subscription->status === 'active') {
                        $stats['past_due']++;

                        if (! $dryRun) {
                            $subscription->forceFill([
                                'status' => 'past_due',
                                'grace_period_ends_at' => $invoice->due_at->copy()->addDays($graceDays),
                                'payment_issue_email_sent_at' => $subscription->payment_issue_email_sent_at ?: now(),
                            ])->save();
                        }
                    }

                    $lastReminderAt = $invoice->last_reminder_sent_at ?: $invoice->reminder_email_sent_at;
                    $canRemind = (int) $invoice->reminder_count < 3
                        && (! $lastReminderAt || $lastReminderAt->lte(now()->subDays($reminderDays)));

                    if (! $canRemind) {
                        if (! $dryRun && $invoice->status !== 'overdue') {
                            $invoice->forceFill(['status' => 'overdue'])->save();
                        }

                        continue;
                    }

                    $stats['reminders']++;

                    if ($dryRun) {
                        continue;
                    }

                    if ($invoice->user && filled($invoice->user->email)) {
                        $invoice->user->notify(new SubscriptionInvoiceReminder($invoice));
                    }

                    AppNotification::send($invoice->user_id, 'subscription.invoice.reminder', [
                        'title' => 'Airmius Abo-Rechnung offen',
                        'body' => $invoice->number.' ist fällig. Bitte begleiche die Rechnung, damit dein Abo aktiv bleibt.',
                        'subscription_invoice_id' => $invoice->id,
                    ]);

                    $invoice->forceFill([
                        'status' => 'overdue',
                        'reminder_email_sent_at' => $invoice->reminder_email_sent_at ?: now(),
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => ((int) $invoice->reminder_count) + 1,
                    ])->save();
                }
            });

        return $stats;
    }

    private function restrictPastDueSubscriptions(string $modelClass, string $subscriptionType, Carbon $today, bool $dryRun): int
    {
        $count = 0;

        $modelClass::query()
            ->where('status', 'past_due')
            ->whereNull('access_restricted_at')
            ->where(function ($query) use ($today) {
                $query->whereNotNull('grace_period_ends_at')->where('grace_period_ends_at', '<', $today)
                    ->orWhereNull('grace_period_ends_at');
            })
            ->chunkById(100, function ($subscriptions) use (&$count, $subscriptionType, $dryRun) {
                foreach ($subscriptions as $subscription) {
                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    $subscription->forceFill([
                        'access_restricted_at' => now(),
                    ])->save();

                    $this->notifySubscriptionOwner($subscription, $subscriptionType, 'subscription.restricted', [
                        'title' => 'Abo eingeschraenkt',
                        'body' => 'Dein Abo ist wegen offener Zahlung eingeschraenkt. Bitte begleiche die offene Rechnung.',
                        'subscription_type' => $subscriptionType,
                        'subscription_id' => $subscription->id,
                    ]);
                }
            });

        return $count;
    }

    private function subscriptionForInvoice(SubscriptionInvoice $invoice): ?Model
    {
        return match ($invoice->subscription_type) {
            'club' => ClubSubscription::query()->find($invoice->subscription_id),
            'user' => UserSubscription::query()->find($invoice->subscription_id),
            default => null,
        };
    }

    private function periodStart(Model $subscription): Carbon
    {
        return ($subscription->current_period_ends_at ?: now())->copy()->startOfDay();
    }

    private function periodEnd(Carbon $periodStart, ?string $billingInterval): Carbon
    {
        return $this->billingInterval((object) ['billing_interval' => $billingInterval]) === 'yearly'
            ? $periodStart->copy()->addYear()->subDay()->endOfDay()
            : $periodStart->copy()->addMonth()->subDay()->endOfDay();
    }

    private function amountCents(Model $subscription): int
    {
        $interval = $this->billingInterval($subscription);

        return (int) ($interval === 'yearly'
            ? ($subscription->plan->yearly_price_cents ?: $subscription->plan->monthly_price_cents * 12)
            : $subscription->plan->monthly_price_cents);
    }

    private function billingInterval(object $subscription): string
    {
        return $subscription->billing_interval === 'yearly' ? 'yearly' : 'monthly';
    }

    private function intervalLabel(?string $billingInterval): string
    {
        return $billingInterval === 'yearly' ? 'Jahreszahlung' : 'Monatszahlung';
    }

    private function paymentMethod(Model $subscription): string
    {
        return $subscription->payment_provider ?: 'bank_transfer';
    }

    private function billingUserId(Model $subscription, string $subscriptionType): int
    {
        if ($subscriptionType === 'user') {
            return (int) $subscription->user_id;
        }

        return (int) ($subscription->club?->owner_id ?: $subscription->club?->owner?->id);
    }

    private function notifySubscriptionOwner(Model $subscription, string $subscriptionType, string $type, array $data): void
    {
        $userId = $subscriptionType === 'user'
            ? $subscription->user_id
            : ($subscription->club?->owner_id ?: $subscription->club?->owner?->id);

        if ($userId) {
            AppNotification::send((int) $userId, $type, $data);
        }
    }

    private function paymentReference(string $subscriptionType, int $subscriptionId): string
    {
        return 'AIR-SUB-'.now()->format('Y').'-'.strtoupper($subscriptionType).'-'.str_pad((string) $subscriptionId, 6, '0', STR_PAD_LEFT);
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = 'AR-'.now()->format('Y').'-';
        $next = SubscriptionInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
