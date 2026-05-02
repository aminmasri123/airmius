<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['moderation_status','post_id','user_id','content'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function moderationFlags()
    {
        return $this->morphMany(ModerationFlag::class, 'flaggable');
    }

    public function reports()
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }
}
