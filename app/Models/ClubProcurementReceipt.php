<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubProcurementReceipt extends Model
{
    protected $fillable = [
        'club_id',
        'club_procurement_request_id',
        'received_by',
        'club_finance_entry_id',
        'received_on',
        'total_cents',
        'reference',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'total_cents' => 'integer',
        ];
    }

    public function procurementRequest()
    {
        return $this->belongsTo(ClubProcurementRequest::class, 'club_procurement_request_id');
    }

    public function financeEntry()
    {
        return $this->belongsTo(ClubFinanceEntry::class, 'club_finance_entry_id');
    }
}
