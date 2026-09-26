<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubProcurementRequest extends Model
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'ordered', 'partially_received', 'received', 'cancelled'];

    protected $fillable = [
        'club_id',
        'club_budget_id',
        'requested_by',
        'approved_by',
        'ordered_by',
        'title',
        'supplier',
        'status',
        'estimated_total_cents',
        'ordered_total_cents',
        'received_total_cents',
        'finance_account',
        'reference',
        'description',
        'submitted_at',
        'approved_at',
        'ordered_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_total_cents' => 'integer',
            'ordered_total_cents' => 'integer',
            'received_total_cents' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function budget()
    {
        return $this->belongsTo(ClubBudget::class, 'club_budget_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function orderer()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function items()
    {
        return $this->hasMany(ClubProcurementItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(ClubProcurementReceipt::class);
    }
}
