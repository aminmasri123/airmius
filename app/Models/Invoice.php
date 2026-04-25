<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['club_id','number','amount','due_date'];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
