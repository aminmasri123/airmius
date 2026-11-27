<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteRequestAttachment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['disk', 'path', 'sha256'];

    public function message()
    {
        return $this->belongsTo(WebsiteRequestMessage::class, 'website_request_message_id');
    }
}
