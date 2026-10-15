<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubDeletionFinancialReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
            'archive_confirmed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
