<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubAccessHandoverReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'assignment_snapshot' => 'array',
            'delegation_count' => 'integer',
            'inventory_loan_snapshot' => 'array',
            'proposed_at' => 'datetime',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function departingUser()
    {
        return $this->belongsTo(User::class, 'departing_user_id');
    }

    public function successor()
    {
        return $this->belongsTo(User::class, 'successor_user_id');
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
