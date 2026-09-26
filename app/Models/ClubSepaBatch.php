<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaBatch extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['creditor_snapshot', 'export_xml'];

    protected function casts(): array
    {
        return [
            'creditor_snapshot' => 'encrypted:array', 'export_xml' => 'encrypted',
            'collection_date' => 'date', 'notice_sent_on' => 'date', 'notice_days' => 'integer',
            'approved_at' => 'datetime', 'exported_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function items()
    {
        return $this->hasMany(ClubSepaBatchItem::class);
    }

    public function notices()
    {
        return $this->hasMany(ClubSepaNotice::class);
    }
}
