<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubDunningRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'version',
        'name',
        'stages',
        'exceptions',
        'channel_requirements',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'stages' => 'array',
            'exceptions' => 'array',
            'channel_requirements' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
