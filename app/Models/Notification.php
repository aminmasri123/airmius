<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications_custom';

    protected $fillable = ['user_id','type','data','read'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
