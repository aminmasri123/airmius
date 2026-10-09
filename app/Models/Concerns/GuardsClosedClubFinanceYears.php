<?php

namespace App\Models\Concerns;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ClubFinanceYearCloseService;
use Illuminate\Support\Facades\DB;

trait GuardsClosedClubFinanceYears
{
    public function save(array $options = [])
    {
        return $this->guardedFinanceWrite(fn () => parent::save($options));
    }

    public function delete()
    {
        return $this->guardedFinanceWrite(fn () => parent::delete(), true);
    }

    private function guardedFinanceWrite(callable $write, bool $deleting = false)
    {
        $guard = app(ClubFinanceYearCloseService::class);
        if (! $this->club_id || ! $guard->available()) {
            return $write();
        }

        return DB::transaction(function () use ($guard, $write, $deleting) {
            Club::whereKey($this->club_id)->lockForUpdate()->first();
            $fields = $this instanceof Invoice
                ? array_diff(array_keys($this->getDirty()), ['updated_at', 'status', 'claim_status', 'paid_at', 'reminder_sent_at', 'due_soon_notified_at', 'sepa_exported_at'])
                : array_diff(array_keys($this->getDirty()), ['updated_at', 'receipt_file_id']);
            // A returned SEPA debit retains its original receipt; its reversal is
            // dated separately and checked by the settlement service.
            $returned = $this instanceof Payment && $this->sepaReturnBookedOn && $this->getRawOriginal('status') === 'paid'
                && $this->status === 'returned' && ! $deleting && count(array_diff(array_keys($this->getDirty()), ['status', 'updated_at'])) === 0;
            if ($returned) {
                $guard->assertOpen((int) $this->club_id, $this->sepaReturnBookedOn);
            }
            if (($deleting || ! $this->exists || ($fields !== [] && $this->isDirty($fields))) && ! $returned) {
                if ($this instanceof Invoice || $this instanceof ClubFinanceEntry) {
                    $guard->assertPeriodOpen((int) $this->club_id, $this->business_year_period_id);
                    $guard->assertPeriodOpen((int) ($this->getRawOriginal('club_id') ?? $this->club_id), $this->getRawOriginal('business_year_period_id'));
                }
                $dateField = $this instanceof ClubFinanceEntry ? 'booked_on' : ($this instanceof Invoice ? 'issued_at' : 'paid_at');
                $guard->assertOpen((int) $this->club_id, $this->{$dateField} ?? $this->created_at ?? now());
                if ($this->exists) {
                    $guard->assertOpen((int) ($this->getRawOriginal('club_id') ?? $this->club_id), $this->getRawOriginal($dateField) ?? $this->getRawOriginal('created_at'));
                }
            }

            return $write();
        });
    }
}
