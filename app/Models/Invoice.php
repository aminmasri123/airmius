<?php

namespace App\Models;

use App\Services\ClubYearPeriodResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public const PAYMENT_STATUSES = ['open', 'paid', 'overdue', 'cancelled', 'waived'];

    public const CLAIM_STATUSES = [
        'open',
        'partially_paid',
        'awaiting_transfer',
        'awaiting_direct_debit',
        'processing_online',
        'paid',
        'overdue',
        'failed',
        'cancelled',
    ];

    public const STATUS_LABELS = [
        'open' => 'Offen',
        'pending' => 'Ausstehend',
        'partially_paid' => 'Teilweise bezahlt',
        'awaiting_transfer' => 'Warte auf Überweisung',
        'awaiting_direct_debit' => 'Warte auf Lastschrift',
        'processing_online' => 'Onlinezahlung wird verarbeitet',
        'paid' => 'Bezahlt',
        'overdue' => 'Überfällig',
        'cancelled' => 'Storniert',
        'waived' => 'Erlassen',
        'failed' => 'Fehlgeschlagen',
    ];

    protected $fillable = [
        'club_id',
        'user_id',
        'membership_user_id',
        'number',
        'title',
        'description',
        'amount',
        'status',
        'claim_status',
        'source',
        'contribution_snapshot',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'issued_at',
        'paid_at',
        'reminder_sent_at',
        'due_soon_notified_at',
        'sepa_exported_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            if (! $invoice->club_id) {
                return;
            }
            $resolver = app(ClubYearPeriodResolver::class);
            $invoice->business_year_period_id ??= $resolver->idFor(
                (int) $invoice->club_id,
                'business',
                $invoice->issued_at ?? now(),
            );
            if (in_array($invoice->source, ['recurring_contribution', 'membership_contribution'], true)) {
                $invoice->contribution_year_period_id ??= $resolver->idFor(
                    (int) $invoice->club_id,
                    'contribution',
                    $invoice->billing_period_start ?? $invoice->due_date ?? $invoice->issued_at ?? now(),
                );
            }
        });
        static::updating(function (self $invoice) {
            if ($invoice->getRawOriginal('source') !== 'sepa_fee_recharge') {
                return;
            }
            abort_if($invoice->getRawOriginal('status') === 'cancelled' && $invoice->isDirty('status'), 422, __('sepa.recharge_invoice_controlled'));
            if ($invoice->isDirty('status')) {
                $covered = $invoice->receivedCents() >= (int) round((float) $invoice->amount * 100);
                abort_unless(in_array($invoice->status, ['open', 'overdue', 'paid'], true)
                    && ($invoice->status === 'paid') === $covered, 422, __('sepa.recharge_invoice_controlled'));
            }
            abort_if($invoice->isDirty(['club_id', 'user_id', 'number', 'title', 'description', 'amount', 'source', 'due_date', 'issued_at', 'billing_period_start', 'billing_period_end'])
                || ($invoice->isDirty('status') && $invoice->status === 'cancelled'), 422, __('sepa.recharge_invoice_controlled'));
        });
        static::deleting(function (self $invoice) {
            abort_if($invoice->source === 'sepa_fee_recharge', 422, __('sepa.recharge_invoice_controlled'));
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contribution_snapshot' => 'array',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'due_soon_notified_at' => 'datetime',
            'sepa_exported_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function businessYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'business_year_period_id');
    }

    public function contributionYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'contribution_year_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membershipUser()
    {
        return $this->belongsTo(User::class, 'membership_user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function settledPayments()
    {
        return $this->payments()->where('status', 'paid');
    }

    public function receivedCents(): int
    {
        if (! $this->exists) {
            return 0;
        }

        $amount = array_key_exists('settled_payments_sum_amount', $this->getAttributes())
            ? $this->getAttribute('settled_payments_sum_amount')
            : $this->settledPayments()->sum('amount');

        return (int) round((float) $amount * 100);
    }

    public function outstandingCents(): int
    {
        // Preserve explicitly settled/cancelled legacy invoices without payment rows.
        if (in_array($this->status, ['paid', 'cancelled', 'waived'], true)) {
            return 0;
        }

        return max(0, (int) round((float) $this->amount * 100) - $this->receivedCents());
    }

    public function balancePayload(): array
    {
        if ($this->exists && ! array_key_exists('settled_payments_sum_amount', $this->getAttributes())) {
            $this->loadSum('settledPayments', 'amount');
        }
        $received = $this->receivedCents();
        $total = (int) round((float) $this->amount * 100);

        return [
            'received_amount' => number_format($received / 100, 2, '.', ''),
            'outstanding_amount' => number_format($this->outstandingCents() / 100, 2, '.', ''),
            'overpaid_amount' => number_format(max(0, $received - $total) / 100, 2, '.', ''),
            'is_partially_paid' => $received > 0 && $received < $total && ! in_array($this->status, ['paid', 'cancelled'], true),
        ];
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function bookingReceipts()
    {
        return $this->hasMany(PaymentBookingReceipt::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ($this->status ?: 'Unbekannt');
    }

    public static function statusOptions(?array $statuses = null): array
    {
        return collect($statuses ?: self::PAYMENT_STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => self::STATUS_LABELS[$status] ?? $status,
            ])
            ->values()
            ->all();
    }
}
