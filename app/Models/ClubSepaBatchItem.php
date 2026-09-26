<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaBatchItem extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['debtor_snapshot'];

    protected function casts(): array
    {
        return ['debtor_snapshot' => 'encrypted:array', 'amount_cents' => 'integer'];
    }

    public function batch()
    {
        return $this->belongsTo(ClubSepaBatch::class, 'club_sepa_batch_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function settlement()
    {
        return $this->hasOne(ClubSepaSettlement::class);
    }
}
