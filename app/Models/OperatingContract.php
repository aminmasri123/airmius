<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatingContract extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'paused', 'cancelled', 'ended'];

    public const CATEGORIES = [
        'telecom',
        'mobile',
        'leasing',
        'software',
        'hosting',
        'insurance',
        'office',
        'marketing',
        'finance',
        'service',
        'other',
    ];

    public const BILLING_INTERVALS = ['weekly', 'monthly', 'quarterly', 'yearly', 'one_time'];

    public const PAYMENT_METHODS = [
        'direct_debit',
        'bank_transfer',
        'card',
        'paypal',
        'invoice',
        'cash',
        'other',
    ];

    protected $fillable = [
        'owner_user_id',
        'created_by',
        'updated_by',
        'name',
        'vendor',
        'category',
        'status',
        'amount',
        'currency',
        'billing_interval',
        'payment_method',
        'next_due_on',
        'starts_on',
        'ends_on',
        'notice_until_on',
        'cancellation_period_days',
        'auto_renews',
        'contract_number',
        'account_reference',
        'contact_email',
        'website',
        'document_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_due_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'notice_until_on' => 'date',
            'cancellation_period_days' => 'integer',
            'auto_renews' => 'boolean',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function monthlyEquivalent(): float
    {
        $amount = (float) $this->amount;

        return match ($this->billing_interval) {
            'weekly' => $amount * 52 / 12,
            'quarterly' => $amount / 3,
            'yearly' => $amount / 12,
            'one_time' => 0.0,
            default => $amount,
        };
    }

    public function yearlyEquivalent(): float
    {
        return $this->monthlyEquivalent() * 12;
    }
}
