<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ClubSepaSettlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settled_on' => 'date', 'returned_on' => 'date', 'retry_authorized_at' => 'datetime', 'return_fee_cents' => 'integer', 'created_payment' => 'boolean', 'fee_created_entry' => 'boolean'];
    }

    public function feeCorrections()
    {
        return $this->hasMany(ClubSepaFeeCorrection::class, 'settlement_id')->orderBy('revision');
    }

    public function feeRecharges()
    {
        return $this->hasMany(ClubSepaFeeRecharge::class, 'settlement_id')->orderBy('id');
    }

    public function feeEntry()
    {
        return $this->belongsTo(ClubFinanceEntry::class, 'fee_finance_entry_id');
    }

    public function item()
    {
        return $this->belongsTo(ClubSepaBatchItem::class, 'club_sepa_batch_item_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public static function returnedAmount(int $clubId, $from = null, $to = null): float
    {
        if (! Schema::hasTable('club_sepa_settlements')) {
            return 0;
        }

        return (float) self::query()->where('club_sepa_settlements.club_id', $clubId)->where('status', 'returned')->whereNotNull('payment_id')
            ->when($from && $to, fn ($query) => $query->whereBetween('returned_on', [$from->toDateString(), $to->toDateString()]))
            ->join('club_sepa_batch_items', 'club_sepa_batch_items.id', '=', 'club_sepa_settlements.club_sepa_batch_item_id')
            ->sum('club_sepa_batch_items.amount_cents') / 100;
    }
}
