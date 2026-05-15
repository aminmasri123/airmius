<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSenderSetting extends Model
{
    protected $fillable = [
        'category',
        'mailer',
        'from_address',
        'from_name',
        'host',
        'port',
        'username',
        'password',
        'scheme',
        'active',
        'password_updated_at',
        'updated_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'password' => 'encrypted',
            'password_updated_at' => 'datetime',
        ];
    }
}
