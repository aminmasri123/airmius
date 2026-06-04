<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamOnboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'team_structure_ready',
        'roles_defined',
        'calendar_setup_done',
        'communication_setup_done',
        'completed_steps',
        'next_step',
    ];

    protected $casts = [
        'team_structure_ready' => 'boolean',
        'roles_defined' => 'boolean',
        'calendar_setup_done' => 'boolean',
        'communication_setup_done' => 'boolean',
        'completed_steps' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}

