<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_holder',
        'iban',
        'bic',
        'paypal_email',
        'tax_number',
        'country_code',
        'tax_status',
        'beneficial_owner_confirmed',
        'terms_version',
        'terms_accepted_at',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'beneficial_owner_confirmed' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
