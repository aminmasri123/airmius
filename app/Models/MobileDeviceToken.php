<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileDeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'provider',
        'token',
        'token_hash',
        'device_name',
        'app_version',
        'build_number',
        'locale',
        'timezone',
        'channels',
        'permissions',
        'last_seen_at',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'permissions' => 'array',
            'last_seen_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
