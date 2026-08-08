<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DomainOutboxEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_name',
        'aggregate_type',
        'aggregate_id',
        'aggregate_version',
        'payload',
        'metadata',
        'audience',
        'occurred_at',
        'available_at',
        'processing_at',
        'published_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_version' => 'integer',
            'payload' => 'array',
            'metadata' => 'array',
            'audience' => 'array',
            'occurred_at' => 'datetime',
            'available_at' => 'datetime',
            'processing_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function envelope(): array
    {
        return [
            'event_id' => $this->id,
            'event_name' => $this->event_name,
            'aggregate' => [
                'type' => $this->aggregate_type,
                'id' => $this->aggregate_id,
                'version' => $this->aggregate_version,
            ],
            'payload' => $this->payload ?? [],
            'metadata' => $this->metadata ?? [],
            'audience' => $this->audience ?? [],
            'occurred_at' => $this->occurred_at?->toISOString(),
        ];
    }
}
