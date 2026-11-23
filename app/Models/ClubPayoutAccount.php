<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubPayoutAccount extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['iban'];
    protected $casts = ['iban' => 'encrypted'];
}
