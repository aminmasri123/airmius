<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaNotice extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['content'];

    protected function casts(): array
    {
        return [
            'content' => 'encrypted:array',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(ClubSepaBatch::class, 'club_sepa_batch_id');
    }

    public function item()
    {
        return $this->belongsTo(ClubSepaBatchItem::class, 'club_sepa_batch_item_id');
    }

    public function providerEvents()
    {
        return $this->hasMany(ClubSepaNoticeProviderEvent::class);
    }
}
