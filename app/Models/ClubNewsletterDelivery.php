<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubNewsletterDelivery extends Model
{
    protected $fillable = [
        'club_newsletter_campaign_id', 'club_newsletter_subscription_id', 'club_id', 'email', 'status',
        'provider_message_id', 'bounce_type', 'failure_reason', 'sent_at', 'bounced_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }
}
