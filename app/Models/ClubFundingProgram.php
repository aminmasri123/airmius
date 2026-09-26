<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubFundingProgram extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft',
        'ready',
        'submitted',
        'approved',
        'rejected',
        'own_contribution_secured',
        'paid_out',
        'closed',
        'withdrawn',
    ];

    protected $fillable = [
        'club_id',
        'club_year_period_id',
        'responsible_user_id',
        'program_name',
        'provider_name',
        'status',
        'deadline_on',
        'submitted_on',
        'approved_on',
        'paid_out_on',
        'requested_amount_cents',
        'approved_amount_cents',
        'own_contribution_cents',
        'paid_out_amount_cents',
        'contact_snapshot',
        'application_snapshot',
        'internal_note',
        'status_changed_by',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline_on' => 'date',
            'submitted_on' => 'date',
            'approved_on' => 'date',
            'paid_out_on' => 'date',
            'requested_amount_cents' => 'integer',
            'approved_amount_cents' => 'integer',
            'own_contribution_cents' => 'integer',
            'paid_out_amount_cents' => 'integer',
            'contact_snapshot' => 'array',
            'application_snapshot' => 'array',
            'status_changed_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function yearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'club_year_period_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
}
