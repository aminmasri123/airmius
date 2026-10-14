<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubStaffShiftComment extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'shift_key', 'user_id', 'message'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
