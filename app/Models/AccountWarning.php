<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountWarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'moderation_flag_id',
        'severity',
        'points',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(ModerationFlag::class, 'moderation_flag_id');
    }
}
