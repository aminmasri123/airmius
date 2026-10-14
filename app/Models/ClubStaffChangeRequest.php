<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubStaffChangeRequest extends Model
{
    use HasFactory;

    public const TYPES = ['swap', 'substitute', 'alternative'];

    public const STATUSES = ['pending', 'accepted', 'declined', 'cancelled'];

    protected $fillable = [
        'club_id',
        'assignment_id',
        'source_assignment_id',
        'requested_by',
        'target_user_id',
        'type',
        'status',
        'note',
        'responded_at',
        'responded_by',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function assignment()
    {
        return $this->belongsTo(ClubStaffAssignment::class, 'assignment_id');
    }

    public function sourceAssignment()
    {
        return $this->belongsTo(ClubStaffAssignment::class, 'source_assignment_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
