<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    protected $fillable = ['message_id', 'file_id'];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
