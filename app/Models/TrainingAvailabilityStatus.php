<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TrainingAvailabilityStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'created_by',
        'status',
        'visibility',
        'starts_on',
        'ends_on',
        'note',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cleared_at' => 'datetime',
        ];
    }

    public function athlete()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(?\DateTimeInterface $on = null): bool
    {
        $date = $on ? Carbon::parse($on->format('Y-m-d')) : now()->startOfDay();

        return $this->cleared_at === null
            && $this->starts_on?->startOfDay()->lte($date)
            && ($this->ends_on === null || $this->ends_on->endOfDay()->gte($date));
    }
}
