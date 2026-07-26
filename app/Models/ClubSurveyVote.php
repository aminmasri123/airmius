<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubSurveyVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_survey_id',
        'club_survey_option_id',
        'user_id',
    ];

    public function survey()
    {
        return $this->belongsTo(ClubSurvey::class, 'club_survey_id');
    }

    public function option()
    {
        return $this->belongsTo(ClubSurveyOption::class, 'club_survey_option_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
