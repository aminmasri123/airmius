<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubFinanceAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'assignable_type',
        'assignable_id',
        'club_business_partner_id',
        'club_accounting_account_id',
        'club_cost_center_id',
        'club_project_id',
        'club_department_id',
        'club_year_period_id',
        'valid_from',
        'valid_until',
        'snapshot',
    ];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'snapshot' => 'array'];
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('valid_from', '<=', $date)
            ->where(fn (Builder $window) => $window->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }

    public function assignable()
    {
        return $this->morphTo();
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function businessPartner()
    {
        return $this->belongsTo(ClubBusinessPartner::class, 'club_business_partner_id');
    }

    public function accountingAccount()
    {
        return $this->belongsTo(ClubAccountingAccount::class, 'club_accounting_account_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(ClubCostCenter::class, 'club_cost_center_id');
    }

    public function project()
    {
        return $this->belongsTo(ClubProject::class, 'club_project_id');
    }

    public function department()
    {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    public function yearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'club_year_period_id');
    }
}
