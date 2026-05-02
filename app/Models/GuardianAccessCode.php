<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuardianAccessCode extends Model
{
    protected $fillable = [
        'email',
        'code_hash',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
