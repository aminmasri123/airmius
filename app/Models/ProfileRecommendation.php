<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileRecommendation extends Model
{
    protected $fillable = [
        'profile_user_id',
        'author_id',
        'relationship',
        'body',
        'status',
    ];

    public function profileUser()
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
