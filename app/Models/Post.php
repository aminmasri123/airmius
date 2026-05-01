<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    public const VISIBILITIES = ['team', 'organization', 'public'];
    public const TYPES = ['normal', 'question', 'knowledge', 'training_drill', 'tactic', 'analysis', 'experience', 'club_update'];

    protected $fillable = ['club_id','team_id','user_id','sport_id','post_type','content','image','visibility'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function sportSkills()
    {
        return $this->belongsToMany(SportSkill::class, 'post_sport_skill');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function helpfuls()
    {
        return $this->hasMany(PostHelpful::class);
    }

    public function attachments()
    {
        return $this->hasMany(PostAttachment::class);
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'post_attachments');
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
