<?php

namespace App\Models;

use App\Support\NotificationRouting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'priority',
        'dedupe_key',
        'data',
        'read',
        'email_digest_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read' => 'boolean',
            'email_digest_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Notification $notification): void {
            $notification->category = NotificationRouting::normalizeCategory(
                $notification->category,
                $notification->type,
            );
            $notification->priority = NotificationRouting::normalizePriority(
                $notification->priority,
                $notification->type,
            );
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
