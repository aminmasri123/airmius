<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSenderAudit extends Model
{
    protected $fillable = [
        'category',
        'action',
        'actor_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
