<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubYearPeriod extends Model
{
    use HasFactory;

    public const TYPES = ['business', 'contribution', 'sport'];

    protected $fillable = ['club_id', 'type', 'name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function businessInvoices()
    {
        return $this->hasMany(Invoice::class, 'business_year_period_id');
    }

    public function contributionInvoices()
    {
        return $this->hasMany(Invoice::class, 'contribution_year_period_id');
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class, 'business_year_period_id');
    }

    public function financeEntries()
    {
        return $this->hasMany(ClubFinanceEntry::class, 'business_year_period_id');
    }

    public function sportEvents()
    {
        return $this->hasMany(Event::class, 'sport_year_period_id');
    }

    public function teams()
    {
        return $this->hasMany(Team::class, 'sport_year_period_id');
    }

    public function isInUse(): bool
    {
        return $this->businessInvoices()->exists()
            || $this->contributionInvoices()->exists()
            || $this->bankTransactions()->exists()
            || $this->financeEntries()->exists()
            || $this->sportEvents()->exists()
            || $this->teams()->exists();
    }
}
