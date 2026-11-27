<?php

namespace App\Models;

use App\Casts\EncryptedIban;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClubUser extends Pivot
{
    protected $table = 'club_user';

    protected $guarded = [];

    protected $casts = [
        'sepa_iban' => EncryptedIban::class,
        'roles' => 'array',
        'permission_overrides' => 'array',
        'member_card_design' => 'array',
        'membership_ended_at' => 'datetime',
    ];

    public $timestamps = true;
}
