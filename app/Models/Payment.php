<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['club_id','user_id','amount','status'];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
