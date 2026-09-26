<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDelivery extends Model
{
    protected $fillable = [
        'dedupe_key',
        'mail_type',
        'club_id',
        'recipient_id',
        'recipient_email',
        'recipient_name',
        'status',
        'attempts',
        'template_key',
        'subject',
        'body',
        'primary_category',
        'fallback_category',
        'used_category',
        'mailer',
        'from_address',
        'error_message',
        'context',
        'queued_at',
        'last_attempt_at',
        'next_attempt_at',
        'scheduled_at',
        'timezone',
        'cancelled_at',
        'failed_at',
        'provider_status',
        'provider_message_id',
        'adapter',
        'created_by',
        'sent_at',
    ];

    protected $casts = [
        'context' => 'array',
        'queued_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
