<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicContactRequest extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'subject',
        'category',
        'priority',
        'app_version',
        'platform',
        'message',
        'privacy_consent_at',
        'status',
        'internal_notes',
        'status_changed_at',
        'status_changed_by',
        'email_delivery_status',
        'email_delivery_error',
        'retention_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'privacy_consent_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'retention_expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusChanger()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
}
