<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SubscriptionInvoice $invoice): void {
            if ($invoice->recipient_snapshot || ! $invoice->user_id) {
                return;
            }
            $user = User::query()->find($invoice->user_id);
            if (! $user) {
                return;
            }
            $invoice->recipient_snapshot = [
                'name' => $user->name,
                'email' => $user->email,
                'club_name' => $invoice->club_id ? Club::query()->whereKey($invoice->club_id)->value('name') : null,
                'captured_at' => now()->toIso8601String(),
            ];
        });
    }

    protected $fillable = [
        'payment_checkout_id',
        'user_id',
        'club_id',
        'subscription_plan_id',
        'subscription_type',
        'subscription_id',
        'number',
        'title',
        'description',
        'amount_cents',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'billing_period_start',
        'billing_period_end',
        'issued_at',
        'due_at',
        'paid_at',
        'invoice_email_sent_at',
        'payment_confirmation_email_sent_at',
        'reminder_email_sent_at',
        'reminder_count',
        'last_reminder_sent_at',
        'meta',
        'recipient_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'invoice_email_sent_at' => 'datetime',
            'payment_confirmation_email_sent_at' => 'datetime',
            'reminder_email_sent_at' => 'datetime',
            'reminder_count' => 'integer',
            'last_reminder_sent_at' => 'datetime',
            'meta' => 'array',
            'recipient_snapshot' => 'array',
        ];
    }

    public function checkout()
    {
        return $this->belongsTo(PaymentCheckout::class, 'payment_checkout_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
