<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubUser extends Model
{
    protected $table = 'club_user';

    protected $fillable = [
        'club_id',
        'user_id',
        'role',
    ];

    public $timestamps = true;
}
