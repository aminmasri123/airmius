<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMasterDataChangeRequest extends Model
{
    protected $fillable = [
        'club_id',
        'requested_by',
        'reviewed_by',
        'status',
        'fields',
        'proposed_values',
        'base_values',
        'conflicts',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'proposed_values' => 'array',
            'base_values' => 'array',
            'conflicts' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
