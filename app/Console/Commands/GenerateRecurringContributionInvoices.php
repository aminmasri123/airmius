<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\TransactionalMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateRecurringContributionInvoices extends Command
{
    protected $signature = 'airmius:generate-recurring-contribution-invoices
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Erstellt wiederkehrende Mitgliedsbeitrags-Rechnungen für Pro/Elite-Vereine.';

    public function __construct(private PlanFeatureService $planFeatures)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();
        $created = 0;
        $skippedByPlan = 0;

        DB::table('club_user')
            ->join('clubs', 'clubs.id', '=', 'club_user.club_id')
            ->join('users', 'users.id', '=', 'club_user.user_id')
            ->select([
                'club_user.club_id',
                'club_user.user_id',
                'club_user.contribution_amount',
                'club_user.contribution_interval',
                'club_user.contribution_next_invoice_on',
                'clubs.name as club_name',
                'users.name as user_name',
            ])
            ->where('club_user.membership_status', 'active')
            ->whereNotNull('club_user.contribution_amount')
            ->where('club_user.contribution_amount', '>', 0)
            ->whereIn('club_user.contribution_interval', ['monthly', 'quarterly', 'yearly', 'once'])
            ->whereNotNull('club_user.contribution_next_invoice_on')
            ->whereDate('club_user.contribution_next_invoice_on', '<=', $date->toDateString())
            ->orderBy('club_user.club_id')
            ->orderBy('club_user.user_id')
            ->cursor()
            ->each(function ($membership) use (&$created, &$skippedByPlan, $date) {
                $club = Club::query()->find((int) $membership->club_id);

                if (! $club || ! $this->planFeatures->allows($club, 'recurring_invoices')) {
                    $skippedByPlan++;
                    return;
                }

                $dueDate = Carbon::parse($membership->contribution_next_invoice_on)->startOfDay();
                $periodEnd = $this->periodEnd($dueDate, $membership->contribution_interval);

                $alreadyExists = Invoice::query()
                    ->where('club_id', $membership->club_id)
                    ->where('user_id', $membership->user_id)
                    ->where('source', 'recurring_contribution')
                    ->whereDate('billing_period_start', $dueDate->toDateString())
                    ->exists();

                if ($alreadyExists) {
                    $this->advanceMembership($membership, $dueDate);
                    return;
                }

                $invoice = Invoice::create([
                    'club_id' => $membership->club_id,
                    'user_id' => $membership->user_id,
                    'number' => $this->nextInvoiceNumber($club),
                    'title' => $this->titleFor($dueDate, $membership->contribution_interval),
                    'description' => "Automatisch erzeugter Mitgliedsbeitrag für {$membership->club_name}.",
                    'amount' => $membership->contribution_amount,
                    'status' => 'open',
                    'source' => 'recurring_contribution',
                    'billing_period_start' => $dueDate->toDateString(),
                    'billing_period_end' => $periodEnd?->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'issued_at' => now(),
                ]);

                AppNotification::send((int) $membership->user_id, 'invoice.created', [
                    'title' => 'Neue Beitragsrechnung von '.$membership->club_name,
                    'body' => $invoice->title.' - '.number_format((float) $invoice->amount, 2, ',', '.').' EUR',
                    'url' => route('auth.settings'),
                    'club_id' => $membership->club_id,
                    'invoice_id' => $invoice->id,
                ]);

                $recipient = User::query()->find((int) $membership->user_id);

                if ($recipient?->email) {
                    $mailer = app(TransactionalMail::class);

                    $mailer->notifyWithFallback(
                        $recipient,
                        fn (array $transport) => new ClubInvoiceCreated(
                            $invoice->loadMissing('club'),
                            $transport['mailer'],
                            $transport['address'],
                            $transport['name'],
                        ),
                        $mailer->invoicePrimaryCategory(),
                        $mailer->invoiceFallbackCategory(),
                        'recurring-contribution.invoice.created:'.$invoice->id.':'.$recipient->id,
                        (int) config('airmius_mail.throttle_seconds.invoice_created', 21600),
                        [
                            'mail_type' => 'recurring_contribution.invoice.created',
                            'invoice_id' => $invoice->id,
                            'recipient_id' => $recipient->id,
                        ],
                    );
                }

                $this->advanceMembership($membership, $dueDate);
                $created++;
            });

        $this->info("Wiederkehrende Rechnungen erstellt: {$created}. Uebersprungen wegen Plan: {$skippedByPlan}.");

        return self::SUCCESS;
    }

    private function advanceMembership(object $membership, Carbon $currentDueDate): void
    {
        $nextDate = match ($membership->contribution_interval) {
            'monthly' => $currentDueDate->copy()->addMonthNoOverflow(),
            'quarterly' => $currentDueDate->copy()->addMonthsNoOverflow(3),
            'yearly' => $currentDueDate->copy()->addYearNoOverflow(),
            'once' => null,
            default => null,
        };

        DB::table('club_user')
            ->where('club_id', $membership->club_id)
            ->where('user_id', $membership->user_id)
            ->update([
                'contribution_next_invoice_on' => $nextDate?->toDateString(),
                'contribution_last_invoice_at' => now(),
            ]);
    }

    private function periodEnd(Carbon $start, string $interval): ?Carbon
    {
        return match ($interval) {
            'monthly' => $start->copy()->addMonthNoOverflow()->subDay(),
            'quarterly' => $start->copy()->addMonthsNoOverflow(3)->subDay(),
            'yearly' => $start->copy()->addYearNoOverflow()->subDay(),
            'once' => $start->copy(),
            default => null,
        };
    }

    private function titleFor(Carbon $date, string $interval): string
    {
        return match ($interval) {
            'monthly' => 'Mitgliedsbeitrag '.$date->translatedFormat('F Y'),
            'quarterly' => 'Mitgliedsbeitrag Quartal '.$date->quarter.'/'.$date->year,
            'yearly' => 'Mitgliedsbeitrag '.$date->year,
            default => 'Mitgliedsbeitrag',
        };
    }

    private function nextInvoiceNumber(Club $club): string
    {
        $next = Invoice::query()
            ->where('club_id', $club->id)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return 'AIR-'.$club->id.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
