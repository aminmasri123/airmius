<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClubUser extends Pivot
{
    protected $table = 'club_user';

    protected $guarded = [];

    protected $casts = [
        'roles' => 'array',
    ];

    public $timestamps = true;
}
