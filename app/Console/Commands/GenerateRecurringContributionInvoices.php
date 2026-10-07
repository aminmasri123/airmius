<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Services\ClubContributionCalculator;
use App\Services\ClubNumberRangeService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\TransactionalMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateRecurringContributionInvoices extends Command
{
    protected $signature = 'airmius:generate-recurring-contribution-invoices
        {--date= : Stichtag im Format YYYY-MM-DD, Standard ist heute}';

    protected $description = 'Erstellt wiederkehrende Mitgliedsbeitrags-Rechnungen für Pro/Elite-Vereine.';

    public function __construct(
        private PlanFeatureService $planFeatures,
        private ClubNumberRangeService $numberRanges,
        private ClubContributionCalculator $contributionCalculator,
    ) {
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
                'club_user.contribution_payer_user_id',
                'club_user.contribution_amount',
                'club_user.contribution_interval',
                'club_user.contribution_next_invoice_on',
                'club_user.club_membership_type_id',
                'club_user.family_group_key',
                'club_user.joined_on',
                'clubs.name as club_name',
                'users.name as user_name',
            ])
            ->where('club_user.membership_status', 'active')
            ->whereNotNull('club_user.contribution_amount')
            ->where('club_user.contribution_amount', '>', 0)
            ->whereIn('club_user.contribution_interval', ['monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'])
            ->whereNotNull('club_user.contribution_next_invoice_on')
            ->whereDate('club_user.contribution_next_invoice_on', '<=', $date->toDateString())
            ->orderBy('club_user.club_id')
            ->orderBy('club_user.user_id')
            ->cursor()
            ->each(function ($membership) use (&$created, &$skippedByPlan) {
                $club = Club::query()->find((int) $membership->club_id);

                if (! $club || ! $this->planFeatures->allows($club, 'recurring_invoices')) {
                    $skippedByPlan++;

                    return;
                }

                $dueDate = Carbon::parse($membership->contribution_next_invoice_on)->startOfDay();
                $periodEnd = $this->periodEnd($dueDate, $membership->contribution_interval);
                $payerUserId = (int) ($membership->contribution_payer_user_id ?: $membership->user_id);

                $invoice = DB::transaction(function () use ($club, $membership, $payerUserId, $dueDate, $periodEnd) {
                    Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
                    $alreadyExists = Invoice::query()
                        ->where('club_id', $membership->club_id)
                        ->where('source', 'recurring_contribution')
                        ->whereDate('billing_period_start', $dueDate->toDateString())
                        ->where(function ($query) use ($membership) {
                            $query->where('membership_user_id', $membership->user_id)
                                ->orWhere(function ($legacy) use ($membership) {
                                    $legacy->whereNull('membership_user_id')
                                        ->where('user_id', $membership->user_id);
                                });
                        })
                        ->exists();
                    if ($alreadyExists) {
                        $this->advanceMembership($membership, $dueDate);

                        return null;
                    }

                    $allocation = $this->numberRanges->allocateDefault(
                        $club,
                        'invoice',
                        null,
                        (string) Str::uuid(),
                        fn (string $number) => ! Invoice::query()->where('number', $number)->exists()
                    );
                    $snapshot = $this->contributionSnapshot($club, $membership, $dueDate, $periodEnd);
                    if ($snapshot['skip_invoice'] ?? false) {
                        $this->advanceMembership($membership, $dueDate);

                        return null;
                    }
                    $invoice = Invoice::create([
                        'club_id' => $membership->club_id,
                        'user_id' => $payerUserId,
                        'membership_user_id' => $membership->user_id,
                        'number' => $allocation?->formatted_number ?? $this->nextInvoiceNumber($club),
                        'title' => $this->titleFor($dueDate, $membership->contribution_interval),
                        'description' => "Automatisch erzeugter Mitgliedsbeitrag für {$membership->user_name} bei {$membership->club_name}.",
                        'amount' => $snapshot['amount'],
                        'status' => 'open',
                        'source' => 'recurring_contribution',
                        'contribution_snapshot' => $snapshot,
                        'billing_period_start' => $dueDate->toDateString(),
                        'billing_period_end' => $periodEnd?->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'issued_at' => now(),
                    ]);
                    if ($allocation) {
                        $this->numberRanges->assignTo($allocation, 'invoice', $invoice->id);
                    }
                    $this->advanceMembership($membership, $dueDate);

                    return $invoice;
                });
                if (! $invoice) {
                    return;
                }

                AppNotification::send($payerUserId, 'invoice.created', [
                    'title' => 'Neue Beitragsrechnung von '.$membership->club_name,
                    'body' => $invoice->title.' - '.number_format((float) $invoice->amount, 2, ',', '.').' EUR',
                    'url' => route('auth.club-memberships.index', [
                        'tab' => 'payments',
                        'club_id' => $membership->club_id,
                        'invoice_id' => $invoice->id,
                    ]),
                    'club_id' => $membership->club_id,
                    'invoice_id' => $invoice->id,
                ]);

                $recipient = User::query()->find($payerUserId);

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

                $created++;
            });

        $this->info("Wiederkehrende Rechnungen erstellt: {$created}. Übersprungen wegen Plan: {$skippedByPlan}.");

        return self::SUCCESS;
    }

    private function advanceMembership(object $membership, Carbon $currentDueDate): void
    {
        $nextDate = match ($membership->contribution_interval) {
            'monthly' => $currentDueDate->copy()->addMonthNoOverflow(),
            'quarterly' => $currentDueDate->copy()->addMonthsNoOverflow(3),
            'four_monthly' => $currentDueDate->copy()->addMonthsNoOverflow(4),
            'semi_yearly' => $currentDueDate->copy()->addMonthsNoOverflow(6),
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
            'four_monthly' => $start->copy()->addMonthsNoOverflow(4)->subDay(),
            'semi_yearly' => $start->copy()->addMonthsNoOverflow(6)->subDay(),
            'yearly' => $start->copy()->addYearNoOverflow()->subDay(),
            'once' => $start->copy(),
            default => null,
        };
    }

    private function contributionSnapshot(Club $club, object $membership, Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $fullAmount = number_format(round((float) $membership->contribution_amount, 2), 2, '.', '');
        $activeFrom = filled($membership->joined_on ?? null)
            ? Carbon::parse($membership->joined_on)->startOfDay()
            : $periodStart->copy();
        $rule = $this->matchingContributionRule($club, $membership, $periodStart);
        $prorationPolicy = $rule?->proration_policy ?: 'prorate_days';

        if ($periodEnd && $prorationPolicy === 'next_period' && $activeFrom->greaterThan($periodStart) && $activeFrom->lessThanOrEqualTo($periodEnd)) {
            return [
                'version' => 1,
                'membership_user_id' => (int) $membership->user_id,
                'payer_user_id' => (int) ($membership->contribution_payer_user_id ?: $membership->user_id),
                'membership_type_id' => $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null,
                'rule_id' => $rule?->id,
                'interval' => $membership->contribution_interval,
                'full_amount' => $fullAmount,
                'amount' => '0.00',
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'period_days' => $periodStart->diffInDays($periodEnd) + 1,
                'billable_days' => 0,
                'active_from' => $activeFrom->toDateString(),
                'prorated' => false,
                'proration_policy' => $prorationPolicy,
                'skip_invoice' => true,
                'rounded_cents' => 0,
                'captured_at' => now()->toIso8601String(),
            ];
        }

        $proration = $periodEnd && $prorationPolicy === 'prorate_days'
            ? $this->contributionCalculator->prorateForPeriod($fullAmount, $periodStart, $periodEnd, $activeFrom)
            : [
                'amount' => $fullAmount,
                'full_amount' => $fullAmount,
                'period_days' => $periodEnd ? $periodStart->diffInDays($periodEnd) + 1 : 1,
                'billable_days' => $periodEnd ? $periodStart->diffInDays($periodEnd) + 1 : 1,
                'active_from' => $activeFrom->greaterThan($periodStart) ? $activeFrom->toDateString() : $periodStart->toDateString(),
                'prorated' => false,
            ];

        return array_merge([
            'version' => 1,
            'membership_user_id' => (int) $membership->user_id,
            'payer_user_id' => (int) ($membership->contribution_payer_user_id ?: $membership->user_id),
            'membership_type_id' => $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null,
            'rule_id' => $rule?->id,
            'interval' => $membership->contribution_interval,
            'full_amount' => $proration['full_amount'],
            'amount' => $proration['amount'],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd?->toDateString(),
            'period_days' => $proration['period_days'],
            'billable_days' => $proration['billable_days'],
            'active_from' => $proration['active_from'],
            'prorated' => $proration['prorated'],
            'proration_policy' => $prorationPolicy,
            'skip_invoice' => false,
            'rounded_cents' => (int) round((float) $proration['amount'] * 100),
            'captured_at' => now()->toIso8601String(),
        ], $this->contributionBreakdown($club, $membership, $periodStart));
    }

    private function contributionBreakdown(Club $club, object $membership, Carbon $date): array
    {
        $member = User::query()->find((int) $membership->user_id);
        $membershipTypeId = $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null;
        $resolved = $this->contributionCalculator->resolve(
            $club,
            $member,
            $membershipTypeId,
            $date,
            $membership->family_group_key ?? null,
        );

        if (! $resolved) {
            return [];
        }

        return [
            'rule_id' => $resolved['rule_id'] ?? null,
            'base_amount' => $resolved['base_amount'] ?? null,
            'component_amount' => $resolved['component_amount'] ?? null,
            'discount_amount' => $resolved['discount_amount'] ?? null,
            'components' => $resolved['components'] ?? [],
            'discounts' => $resolved['discounts'] ?? [],
            'preview_lines' => $resolved['preview_lines'] ?? [],
        ];
    }

    private function matchingContributionRule(Club $club, object $membership, Carbon $date): ?\App\Models\ClubContributionRule
    {
        $membershipTypeId = $membership->club_membership_type_id ? (int) $membership->club_membership_type_id : null;

        return $club->contributionRules()
            ->effectiveOn($date->toDateString())
            ->where('billing_interval', $membership->contribution_interval)
            ->when($membershipTypeId, fn ($query) => $query->where(function ($query) use ($membershipTypeId) {
                $query->where('club_membership_type_id', $membershipTypeId)
                    ->orWhereNull('club_membership_type_id');
            }))
            ->when(! $membershipTypeId, fn ($query) => $query->whereNull('club_membership_type_id'))
            ->orderByRaw('CASE WHEN club_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
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
