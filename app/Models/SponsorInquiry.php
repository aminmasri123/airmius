<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorInquiry extends Model
{
    protected $fillable = [
        'sponsor_id', 'club_id', 'created_by', 'responded_by',
        'status', 'subject', 'message', 'response_note', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
