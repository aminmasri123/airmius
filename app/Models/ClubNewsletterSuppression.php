<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubNewsletterSuppression extends Model
{
    protected $fillable = ['club_id', 'email', 'reason', 'suppressed_at', 'metadata'];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
