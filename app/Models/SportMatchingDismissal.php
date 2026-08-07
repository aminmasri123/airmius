<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SportMatchingDismissal extends Model
{
    use HasFactory;

    protected $fillable = ['sport_matching_id', 'user_id'];

    public function matching(): BelongsTo
    {
        return $this->belongsTo(SportMatching::class, 'sport_matching_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
