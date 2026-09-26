<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubDunningEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'invoice_id',
        'club_dunning_rule_id',
        'rule_version',
        'stage',
        'status',
        'channel',
        'delivery_status',
        'delivered_at',
        'evidence_reference',
        'fee_cents',
        'fee_invoice_number',
        'blocks_service',
        'is_exception',
        'exception_reason',
        'idempotency_key',
        'created_by',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'rule_version' => 'integer',
            'stage' => 'integer',
            'delivered_at' => 'datetime',
            'fee_cents' => 'integer',
            'blocks_service' => 'boolean',
            'is_exception' => 'boolean',
            'payload' => 'array',
        ];
    }
}
