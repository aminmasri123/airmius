<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaNoticeProviderEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function notice()
    {
        return $this->belongsTo(ClubSepaNotice::class, 'club_sepa_notice_id');
    }
}
