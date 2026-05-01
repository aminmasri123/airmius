<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationXpEvent extends Model
{
    protected $fillable = [
        'user_id',
        'actor_type',
        'source_type',
        'source_id',
        'amount',
        'reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }
}
