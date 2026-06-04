<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePushDelivery extends Model
{
    protected $fillable = [
        'notification_id',
        'mobile_device_token_id',
        'user_id',
        'channel',
        'provider',
        'status',
        'payload',
        'provider_message_id',
        'error',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function device()
    {
        return $this->belongsTo(MobileDeviceToken::class, 'mobile_device_token_id');
    }
}
