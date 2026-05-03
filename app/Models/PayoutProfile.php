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
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
