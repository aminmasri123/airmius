<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorPartnership extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending_club_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'sponsor_id',
        'club_id',
        'created_by',
        'responded_by',
        'status',
        'starts_at',
        'ends_at',
        'package_code',
        'rights_package',
        'individual_offer_terms',
        'contract_version',
        'renewal_notice_days',
        'renewal_deadline',
        'budget_cents',
        'response_note',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'rights_package' => 'array',
            'renewal_deadline' => 'date',
            'budget_cents' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
