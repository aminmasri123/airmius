<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubExternalMember;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubMembershipInput;
use App\Support\TransactionalMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ClubContributionInvoiceRunService
{
    public function __construct(
        private ClubNumberRangeService $numberRanges,
        private ClubContributionCalculator $contributionCalculator,
    ) {}

    public function preview(Club $club, Carbon|string|null $date = null, array $options = []): array
    {
        $runDate = $this->date($date);
        $rows = collect()
            ->merge($this->linkedRows($club, $runDate))
            ->merge($this->externalRows($club, $runDate))
            ->map(fn (array $row) => $this->previewRow($club, $row, $runDate, $options))
            ->values();

        return [
            'run_date' => $runDate->toDateString(),
            'title' => $options['title'] ?? $this->titleFor($runDate, null),
            'due_date' => $this->date($options['due_date'] ?? $runDate->toDateString())->toDateString(),
            'total_count' => $rows->count(),
            'billable_count' => $rows->where('can_create', true)->count(),
            'skipped_count' => $rows->where('can_create', false)->count(),
            'transfer_count' => $rows->where('payment_flow', 'bank_transfer')->count(),
            'direct_debit_count' => $rows->where('payment_flow', 'direct_debit')->count(),
            'total_amount' => number_format($rows->where('can_create', true)->sum(fn ($row) => (float) $row['amount']), 2, '.', ''),
            'rows' => $rows,
        ];
    }

    public function create(Club $club, User $actor, Carbon|string|null $date = null, array $options = []): array
    {
        $preview = $this->preview($club, $date, $options);
        $created = collect();
        $skipped = 0;

        foreach ($preview['rows'] as $row) {
            if (! $row['can_create']) {
                if (in_array($row['skip_reason'], ['duplicate', 'next_period'], true)) {
                    DB::transaction(function () use ($club, $row) {
                        Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
                        $this->advance($row);
                    });
                }
                $skipped++;
                continue;
            }

            $invoice = DB::transaction(function () use ($club, $actor, $row, $options) {
                Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();

                if ($this->duplicateExists($club, $row)) {
                    return null;
                }

                $allocation = $this->numberRanges->allocateDefault(
                    $club,
                    'invoice',
                    $actor,
                    (string) Str::uuid(),
                    fn (string $number) => ! Invoice::query()->where('number', $number)->exists()
                );

                $invoice = Invoice::create([
                    'club_id' => $club->id,
                    'user_id' => $row['payer_user_id'],
                    'membership_user_id' => $row['member_type'] === 'member' ? $row['member_id'] : null,
                    'club_external_member_id' => $row['member_type'] === 'external' ? $row['member_id'] : null,
                    'number' => $allocation?->formatted_number ?? $this->nextInvoiceNumber($club),
                    'title' => $options['title'] ?? $row['title'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'status' => 'open',
                    'claim_status' => $row['payment_flow'] === 'direct_debit' ? 'awaiting_direct_debit' : 'awaiting_transfer',
                    'source' => 'recurring_contribution',
                    'contribution_snapshot' => $row['snapshot'],
                    'billing_period_start' => $row['billing_period_start'],
                    'billing_period_end' => $row['billing_period_end'],
                    'due_date' => $row['due_date'],
                    'issued_at' => now(),
                ]);

                if ($allocation) {
                    $this->numberRanges->assignTo($allocation, 'invoice', $invoice->id);
                }

                $this->advance($row);

                ClubAuditLog::record($club, $actor, 'club.invoice.run.created', $invoice, [
                    'invoice_number' => $invoice->number,
                    'amount' => $invoice->amount,
                    'member_type' => $row['member_type'],
                    'member_id' => $row['member_id'],
                    'payment_flow' => $row['payment_flow'],
                ]);

                return $invoice;
            });

            if (! $invoice) {
                $skipped++;
                continue;
            }

            $this->notify($invoice->loadMissing('club', 'user', 'membershipUser', 'externalMember'));
            $created->push($invoice);
        }

        return [
            ...$preview,
            'created_count' => $created->count(),
            'skipped_count' => $skipped,
            'invoice_ids' => $created->pluck('id')->values(),
        ];
    }

    private function linkedRows(Club $club, Carbon $date)
    {
        return DB::table('club_user')
            ->join('users', 'users.id', '=', 'club_user.user_id')
            ->where('club_user.club_id', $club->id)
            ->where('club_user.membership_status', 'active')
            ->whereNotNull('club_user.contribution_amount')
            ->where('club_user.contribution_amount', '>', 0)
            ->whereIn('club_user.contribution_interval', ['monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'])
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
                'club_user.payment_method',
                'club_user.sepa_iban',
                'club_user.sepa_bic',
                'club_user.sepa_mandate_reference',
                'club_user.sepa_mandate_signed_on',
                'club_user.sepa_mandate_active',
                'users.name as member_name',
                'users.email as member_email',
            ])
            ->orderBy('users.name')
            ->get()
            ->map(function ($row) {
                $data = get_object_vars($row);
                $data['contribution_next_invoice_on'] = ClubMembershipInput::normalizedNextInvoiceDate($data);

                return [
                    ...$data,
                    'member_type' => 'member',
                    'member_id' => (int) $row->user_id,
                    'payer_user_id' => (int) ($row->contribution_payer_user_id ?: $row->user_id),
                ];
            })
            ->filter(fn (array $row) => filled($row['contribution_next_invoice_on']) && $row['contribution_next_invoice_on'] <= $date->toDateString());
    }

    private function externalRows(Club $club, Carbon $date)
    {
        return ClubExternalMember::query()
            ->where('club_id', $club->id)
            ->where('membership_status', 'active')
            ->whereNotNull('contribution_amount')
            ->where('contribution_amount', '>', 0)
            ->whereIn('contribution_interval', ['monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'])
            ->orderBy('name')
            ->get()
            ->map(function (ClubExternalMember $member) {
                $data = [
                    'club_id' => $member->club_id,
                    'user_id' => null,
                    'contribution_payer_user_id' => $member->contribution_payer_user_id,
                    'contribution_amount' => $member->contribution_amount,
                    'contribution_interval' => $member->contribution_interval,
                    'contribution_next_invoice_on' => $member->contribution_next_invoice_on?->toDateString(),
                    'club_membership_type_id' => $member->club_membership_type_id,
                    'family_group_key' => $member->family_group_key,
                    'joined_on' => $member->joined_on?->toDateString(),
                    'payment_method' => $member->payment_method,
                    'sepa_iban' => $member->sepa_iban,
                    'sepa_bic' => $member->sepa_bic,
                    'sepa_mandate_reference' => $member->sepa_mandate_reference,
                    'sepa_mandate_signed_on' => $member->sepa_mandate_signed_on?->toDateString(),
                    'sepa_mandate_active' => $member->sepa_mandate_active,
                    'member_name' => $member->name ?: $member->email,
                    'member_email' => $member->email,
                    'member_type' => 'external',
                    'member_id' => $member->id,
                    'payer_user_id' => $member->contribution_payer_user_id,
                ];
                $data['contribution_next_invoice_on'] = ClubMembershipInput::normalizedNextInvoiceDate($data);

                return $data;
            })
            ->filter(fn (array $row) => filled($row['contribution_next_invoice_on']) && $row['contribution_next_invoice_on'] <= $date->toDateString());
    }

    private function previewRow(Club $club, array $row, Carbon $runDate, array $options): array
    {
        $periodStart = Carbon::parse($row['contribution_next_invoice_on'])->startOfDay();
        $periodEnd = $this->periodEnd($periodStart, $row['contribution_interval']);
        $snapshot = $this->snapshot($club, $row, $periodStart, $periodEnd);
        $duplicate = $this->duplicateExists($club, [
            ...$row,
            'billing_period_start' => $periodStart->toDateString(),
        ]);
        $paymentFlow = $this->paymentFlow($row);
        $recipientOk = $paymentFlow === 'direct_debit'
            ? filled($row['sepa_iban'])
                && filled($row['sepa_mandate_reference'])
                && filled($row['sepa_mandate_signed_on'])
                && (bool) $row['sepa_mandate_active']
            : ($row['member_type'] === 'member' || filled($row['member_email']));
        $canCreate = $recipientOk
            && ! ($snapshot['skip_invoice'] ?? false)
            && ! $duplicate
            && (float) $snapshot['amount'] > 0;

        return [
            'member_type' => $row['member_type'],
            'club_id' => $club->id,
            'member_id' => $row['member_id'],
            'payer_user_id' => $row['payer_user_id'],
            'member_name' => $row['member_name'],
            'member_email' => $row['member_email'],
            'title' => $options['title'] ?? $this->titleFor($periodStart, $row['contribution_interval']),
            'description' => $paymentFlow === 'direct_debit'
                ? 'Beitragsrechnung mit vorgesehener SEPA-Lastschrift.'
                : 'Beitragsrechnung mit Zahlungsaufforderung per Überweisung.',
            'amount' => $snapshot['amount'],
            'full_amount' => $snapshot['full_amount'] ?? $snapshot['amount'],
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd?->toDateString(),
            'due_date' => $this->date($options['due_date'] ?? $runDate->toDateString())->toDateString(),
            'interval' => $row['contribution_interval'],
            'payment_flow' => $paymentFlow,
            'recipient_ok' => $recipientOk,
            'duplicate' => $duplicate,
            'can_create' => $canCreate,
            'skip_reason' => match (true) {
                ! $recipientOk => 'missing_recipient',
                $duplicate => 'duplicate',
                (bool) ($snapshot['skip_invoice'] ?? false) => 'next_period',
                default => null,
            },
            'snapshot' => $snapshot,
        ];
    }

    private function snapshot(Club $club, array $row, Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $fullAmount = number_format(round((float) $row['contribution_amount'], 2), 2, '.', '');
        $activeFrom = filled($row['joined_on'] ?? null) ? Carbon::parse($row['joined_on'])->startOfDay() : $periodStart->copy();
        $rule = $this->matchingRule($club, $row, $periodStart);
        $policy = $rule?->proration_policy ?: 'prorate_days';

        if ($periodEnd && $policy === 'next_period' && $activeFrom->greaterThan($periodStart) && $activeFrom->lessThanOrEqualTo($periodEnd)) {
            return [
                'version' => 1,
                'amount' => '0.00',
                'full_amount' => $fullAmount,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'proration_policy' => $policy,
                'skip_invoice' => true,
                'captured_at' => now()->toIso8601String(),
            ];
        }

        $proration = $periodEnd && $policy === 'prorate_days'
            ? $this->contributionCalculator->prorateForPeriod($fullAmount, $periodStart, $periodEnd, $activeFrom)
            : [
                'amount' => $fullAmount,
                'full_amount' => $fullAmount,
                'period_days' => $periodEnd ? $periodStart->diffInDays($periodEnd) + 1 : 1,
                'billable_days' => $periodEnd ? $periodStart->diffInDays($periodEnd) + 1 : 1,
                'active_from' => $activeFrom->toDateString(),
                'prorated' => false,
            ];

        $resolved = $this->contributionCalculator->resolve(
            $club,
            $row['member_type'] === 'member' ? User::query()->find($row['member_id']) : null,
            $row['club_membership_type_id'] ? (int) $row['club_membership_type_id'] : null,
            $periodStart,
            $row['family_group_key'] ?? null,
        );

        return [
            'version' => 1,
            'member_type' => $row['member_type'],
            'member_id' => $row['member_id'],
            'membership_user_id' => $row['member_type'] === 'member' ? $row['member_id'] : null,
            'club_external_member_id' => $row['member_type'] === 'external' ? $row['member_id'] : null,
            'payer_user_id' => $row['payer_user_id'],
            'membership_type_id' => $row['club_membership_type_id'] ? (int) $row['club_membership_type_id'] : null,
            'rule_id' => $rule?->id,
            'interval' => $row['contribution_interval'],
            'amount' => $proration['amount'],
            'full_amount' => $proration['full_amount'],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd?->toDateString(),
            'period_days' => $proration['period_days'],
            'billable_days' => $proration['billable_days'],
            'active_from' => $proration['active_from'],
            'prorated' => $proration['prorated'],
            'proration_policy' => $policy,
            'skip_invoice' => false,
            'rounded_cents' => (int) round((float) $proration['amount'] * 100),
            'captured_at' => now()->toIso8601String(),
            'base_amount' => $resolved['base_amount'] ?? null,
            'component_amount' => $resolved['component_amount'] ?? null,
            'discount_amount' => $resolved['discount_amount'] ?? null,
            'components' => $resolved['components'] ?? [],
            'discounts' => $resolved['discounts'] ?? [],
            'preview_lines' => $resolved['preview_lines'] ?? [],
        ];
    }

    private function duplicateExists(Club $club, array $row): bool
    {
        return Invoice::query()
            ->where('club_id', $club->id)
            ->where('source', 'recurring_contribution')
            ->whereDate('billing_period_start', $row['billing_period_start'] ?? $row['contribution_next_invoice_on'])
            ->where(function ($query) use ($row) {
                if (($row['member_type'] ?? null) === 'external') {
                    $query->where('club_external_member_id', $row['member_id']);
                } else {
                    $query->where('membership_user_id', $row['member_id'])
                        ->orWhere(function ($legacy) use ($row) {
                            $legacy->whereNull('membership_user_id')->where('user_id', $row['member_id']);
                        });
                }
            })
            ->exists();
    }

    private function paymentFlow(array $row): string
    {
        $paymentMethod = $row['payment_method'] ?? null;

        if (in_array($paymentMethod, ['sepa', 'sepa_debit', 'direct_debit'], true)) {
            return 'direct_debit';
        }

        if (filled($paymentMethod)) {
            return 'bank_transfer';
        }

        return filled($row['sepa_iban']) && (bool) $row['sepa_mandate_active'] ? 'direct_debit' : 'bank_transfer';
    }

    private function advance(array $row): void
    {
        $current = Carbon::parse($row['billing_period_start']);
        $next = match ($row['interval']) {
            'monthly' => $current->copy()->addMonthNoOverflow(),
            'quarterly' => $current->copy()->addMonthsNoOverflow(3),
            'four_monthly' => $current->copy()->addMonthsNoOverflow(4),
            'semi_yearly' => $current->copy()->addMonthsNoOverflow(6),
            'yearly' => $current->copy()->addYearNoOverflow(),
            'once' => null,
            default => null,
        };

        if ($row['member_type'] === 'external') {
            ClubExternalMember::query()->whereKey($row['member_id'])->update([
                'contribution_next_invoice_on' => $next?->toDateString(),
                'contribution_last_invoice_at' => now(),
            ]);

            return;
        }

        DB::table('club_user')
            ->where('club_id', $row['club_id'])
            ->where('user_id', $row['member_id'])
            ->update([
                'contribution_next_invoice_on' => $next?->toDateString(),
                'contribution_last_invoice_at' => now(),
            ]);
    }

    private function notify(Invoice $invoice): void
    {
        if ($invoice->user) {
            AppNotification::sendLocalized(
                $invoice->user,
                'invoice.created',
                'organization.notifications.invoice_created_title',
                'organization.notifications.invoice_created_body',
                [
                    'club' => $invoice->club?->name,
                    'title' => $invoice->title,
                    'amount' => number_format((float) $invoice->amount, 2, ',', '.'),
                ],
                ['club_id' => $invoice->club_id, 'invoice_id' => $invoice->id],
            );
        }

        $notifiable = $invoice->invoiceNotifiable();
        if (! $notifiable) {
            return;
        }

        $mailer = app(TransactionalMail::class);
        $notification = fn (array $transport) => new ClubInvoiceCreated(
            $invoice,
            $transport['mailer'],
            $transport['address'],
            $transport['name'],
        );

        if ($notifiable instanceof User) {
            $mailer->notifyWithFallback(
                $notifiable,
                $notification,
                $mailer->invoicePrimaryCategory(),
                $mailer->invoiceFallbackCategory(),
                'club.invoice.run.created:'.$invoice->id.':'.$notifiable->id,
                (int) config('airmius_mail.throttle_seconds.invoice_created', 21600),
                ['mail_type' => 'club.invoice.run.created', 'invoice_id' => $invoice->id],
            );

            return;
        }

        $transport = $mailer->transportFor($mailer->invoicePrimaryCategory());
        Notification::route('mail', $invoice->externalMember?->email)->notify(
            new ClubInvoiceCreated($invoice, $transport['mailer'], $transport['address'], $transport['name'])
        );
    }

    private function matchingRule(Club $club, array $row, Carbon $date): ?\App\Models\ClubContributionRule
    {
        $typeId = $row['club_membership_type_id'] ? (int) $row['club_membership_type_id'] : null;

        return $club->contributionRules()
            ->effectiveOn($date->toDateString())
            ->where('billing_interval', $row['contribution_interval'])
            ->when($typeId, fn ($query) => $query->where(fn ($query) => $query->where('club_membership_type_id', $typeId)->orWhereNull('club_membership_type_id')))
            ->when(! $typeId, fn ($query) => $query->whereNull('club_membership_type_id'))
            ->orderByRaw('CASE WHEN club_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
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

    private function titleFor(Carbon $date, ?string $interval): string
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
        $next = Invoice::query()->where('club_id', $club->id)->whereYear('created_at', now()->year)->count() + 1;

        return 'AIR-'.$club->id.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function date(Carbon|string|null $date): Carbon
    {
        return $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date ?: now()->toDateString())->startOfDay();
    }
}
