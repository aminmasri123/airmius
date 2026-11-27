<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteRequestMessage extends Model
{
    protected $fillable = ['user_id', 'kind', 'status', 'body', 'request_key'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
