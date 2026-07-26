<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubSurveyOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_survey_id',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function survey()
    {
        return $this->belongsTo(ClubSurvey::class, 'club_survey_id');
    }

    public function votes()
    {
        return $this->hasMany(ClubSurveyVote::class);
    }
}
