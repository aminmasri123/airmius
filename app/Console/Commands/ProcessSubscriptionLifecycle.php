<?php

namespace App\Console\Commands;

use App\Models\ClubSubscription;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionInvoiceReminder;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use App\Support\SupportedLocale;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'airmius:process-subscription-lifecycle
        {--grace-days=9 : Tage bis zur Zugriffseinschränkung nach Fälligkeit}
        {--reminder-days=3 : Abstand zwischen Mahnungen}
        {--dry-run : Nur zählen, nichts speichern oder senden}';

    protected $description = 'Erzeugt wiederkehrende Abo-Rechnungen, mahnt offene Zahlungen und finalisiert Kündigungen.';

    public function __construct(private readonly SubscriptionLifecycleService $subscriptionLifecycle)
    {
        parent::__construct();
    }

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
            ->chunkById(100, function ($subscriptions) use (&$count, $dryRun) {
                foreach ($subscriptions as $subscription) {
                    if ($dryRun) {
                        $count++;

                        continue;
                    }

                    if ($this->subscriptionLifecycle->finalizeCancellation($subscription)) {
                        $count++;
                    }
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
                    $locale = $this->subscriptionLocale($subscription, $subscriptionType);

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
                                'title' => __('subscription.invoice.title', [
                                    'plan' => $subscription->plan->name,
                                ], $locale),
                                'description' => __(sprintf(
                                    'subscription.invoice.description_%s',
                                    $this->billingInterval($subscription) === 'yearly' ? 'yearly' : 'monthly',
                                ), [
                                    'plan' => $subscription->plan->name,
                                ], $locale),
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
            ->chunkById(100, function ($invoices) use (&$stats, $dryRun, $reminderDays, $graceDays) {
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

                    AppNotification::sendLocalized(
                        $invoice->user_id,
                        'subscription.invoice.reminder',
                        'notification_settings.notifications.invoice_reminder_title',
                        'notification_settings.notifications.invoice_reminder_body_extended',
                        ['invoice' => $invoice->number],
                        ['subscription_invoice_id' => $invoice->id],
                        [
                            'dedupe_key' => 'subscription-invoice:'.$invoice->id.':lifecycle-reminder:'.(((int) $invoice->reminder_count) + 1),
                            'priority' => 'high',
                        ],
                    );

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

                    $this->notifySubscriptionOwnersLocalized(
                        $subscription,
                        $subscriptionType,
                        'subscription.restricted',
                        'subscription.notifications.restricted_title',
                        'subscription.notifications.restricted_body',
                        [
                            'subscription_type' => $subscriptionType,
                            'subscription_id' => $subscription->id,
                        ],
                    );
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

    private function notifySubscriptionOwnersLocalized(
        Model $subscription,
        string $subscriptionType,
        string $type,
        string $titleKey,
        string $bodyKey,
        array $data,
    ): void {
        foreach ($this->subscriptionRecipientIds($subscription, $subscriptionType) as $userId) {
            AppNotification::sendLocalized(
                $userId,
                $type,
                $titleKey,
                $bodyKey,
                [],
                $data,
                [
                    'dedupe_key' => 'subscription:'.$subscriptionType.':'.$subscription->id.':restricted',
                    'priority' => 'high',
                ],
            );
        }
    }

    /** @return array<int, int> */
    private function subscriptionRecipientIds(Model $subscription, string $subscriptionType): array
    {
        if ($subscriptionType === 'user') {
            return $subscription->user_id ? [(int) $subscription->user_id] : [];
        }

        $club = $subscription->club;

        if (! $club) {
            return [];
        }

        return $club->users()
            ->get(['users.id'])
            ->push($club->owner)
            ->filter()
            ->unique('id')
            ->filter(fn ($user) => ClubPermissions::allows(
                $club,
                $user,
                ClubPermissions::SUBSCRIPTIONS_VIEW,
            ))
            ->pluck('id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function subscriptionLocale(Model $subscription, string $subscriptionType): string
    {
        $language = $subscriptionType === 'user'
            ? $subscription->user?->language
            : $subscription->club?->owner?->language;

        return SupportedLocale::normalize($language) ?? SupportedLocale::DEFAULT;
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
