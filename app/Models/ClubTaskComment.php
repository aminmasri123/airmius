<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubTaskComment extends Model
{
    protected $fillable = ['club_task_id', 'user_id', 'body'];

    public function task()
    {
        return $this->belongsTo(ClubTask::class, 'club_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
