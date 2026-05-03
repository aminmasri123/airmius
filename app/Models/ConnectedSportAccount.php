<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectedSportAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'display_name',
        'status',
        'scopes',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_synced_at',
        'sync_summary',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_summary' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activities()
    {
        return $this->hasMany(ConnectedSportActivity::class);
    }
}
