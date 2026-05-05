<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'icon',
        'actor_type',
        'trigger',
        'threshold',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'threshold' => 'integer',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot(['awardable_type', 'awardable_id', 'reason', 'meta'])
            ->withTimestamps();
    }
}
