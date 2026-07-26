<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'user_id',
        'question',
        'description',
        'audience_type',
        'team_id',
        'quorum',
        'closes_at',
        'status',
    ];

    protected $casts = [
        'quorum' => 'integer',
        'closes_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function options()
    {
        return $this->hasMany(ClubSurveyOption::class)->orderBy('sort_order');
    }

    public function votes()
    {
        return $this->hasMany(ClubSurveyVote::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->closes_at || now()->lt($this->closes_at));
    }
}
