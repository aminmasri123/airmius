<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'scope',
        'event_key',
        'event_type',
        'status',
        'subject_type',
        'subject_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
