<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommerceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'access_token',
        'club_id',
        'orderable_type',
        'orderable_id',
        'type',
        'provider',
        'billing_interval',
        'item_gross_cents',
        'shipping_cents',
        'net_cents',
        'tax_cents',
        'amount_cents',
        'commission_cents',
        'payout_id',
        'payout_status',
        'currency',
        'tax_country',
        'tax_rate_percent',
        'customer_type',
        'customer_company',
        'customer_vat_id',
        'status',
        'shipping_status',
        'shipping_carrier',
        'shipping_label_url',
        'tracking_number',
        'tracking_url',
        'invoice_number',
        'credit_note_number',
        'issue_status',
        'issue_note',
        'issue_reported_at',
        'provider_checkout_id',
        'refund_provider_id',
        'payment_reference',
        'refunded_cents',
        'due_at',
        'checkout_url',
        'payload',
        'completed_at',
        'shipped_at',
        'delivered_at',
        'confirmation_email_sent_at',
        'customer_vat_is_valid',
        'customer_vat_validated_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'item_gross_cents' => 'integer',
            'shipping_cents' => 'integer',
            'net_cents' => 'integer',
            'tax_cents' => 'integer',
            'amount_cents' => 'integer',
            'commission_cents' => 'integer',
            'refunded_cents' => 'integer',
            'tax_rate_percent' => 'float',
            'customer_vat_is_valid' => 'boolean',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
            'issue_reported_at' => 'datetime',
            'customer_vat_validated_at' => 'datetime',
        ];
    }

    public function orderable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function payout()
    {
        return $this->belongsTo(MarketplacePayout::class, 'payout_id');
    }

    public function items()
    {
        return $this->hasMany(CommerceOrderItem::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(CommerceReturnRequest::class);
    }
}
