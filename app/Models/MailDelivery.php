<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDelivery extends Model
{
    protected $fillable = [
        'dedupe_key',
        'mail_type',
        'recipient_id',
        'recipient_email',
        'recipient_name',
        'status',
        'primary_category',
        'fallback_category',
        'used_category',
        'mailer',
        'from_address',
        'error_message',
        'context',
        'sent_at',
    ];

    protected $casts = [
        'context' => 'array',
        'sent_at' => 'datetime',
    ];
}
