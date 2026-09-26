<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubNewsletterSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'user_id', 'email', 'name', 'status', 'confirmation_token_hash',
        'confirmation_sent_at', 'confirmed_at', 'unsubscribe_token_hash', 'unsubscribed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
