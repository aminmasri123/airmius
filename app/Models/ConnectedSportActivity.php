<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectedSportActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'connected_sport_account_id',
        'provider',
        'provider_activity_id',
        'activity_type',
        'title',
        'started_at',
        'duration_seconds',
        'distance_meters',
        'calories',
        'metrics',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'metrics' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function connectedAccount()
    {
        return $this->belongsTo(ConnectedSportAccount::class, 'connected_sport_account_id');
    }
}
