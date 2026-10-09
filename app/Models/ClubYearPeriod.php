<?php

namespace App\Models;

use App\Services\ClubFinanceYearCloseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClubYearPeriod extends Model
{
    use HasFactory;

    public const TYPES = ['business', 'contribution', 'sport'];

    protected $fillable = ['club_id', 'type', 'name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'finance_closed_at' => 'datetime', 'finance_closing_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $period) {
            if (! app(ClubFinanceYearCloseService::class)->available()) {
                return;
            }
            abort_if(self::whereKey($period->id)->whereNotNull('finance_closed_at')->exists() && $period->isDirty(['club_id', 'type', 'name', 'starts_on', 'ends_on', 'finance_closed_at', 'finance_closed_by', 'finance_next_period_id', 'finance_closing_snapshot']), 422);
            abort_if($period->isDirty(['club_id', 'type', 'starts_on']) && self::where('finance_next_period_id', $period->id)->exists(), 422);
        });
        static::deleting(function (self $period) {
            if (app(ClubFinanceYearCloseService::class)->available()) {
                abort_if(self::whereKey($period->id)->whereNotNull('finance_closed_at')->exists() || self::where('finance_next_period_id', $period->id)->exists(), 422);
            }
        });
    }

    public function save(array $options = [])
    {
        return $this->withFinanceYearLock(fn () => parent::save($options));
    }

    public function delete()
    {
        return $this->withFinanceYearLock(fn () => parent::delete());
    }

    private function withFinanceYearLock(callable $write)
    {
        if (! $this->club_id || ! app(ClubFinanceYearCloseService::class)->available()) {
            return $write();
        }

        return DB::transaction(function () use ($write) {
            Club::whereKey($this->club_id)->lockForUpdate()->first();

            return $write();
        });
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
        return (bool) $this->finance_closed_at
            || (app(ClubFinanceYearCloseService::class)->available() && self::where('finance_next_period_id', $this->id)->exists())
            || $this->businessInvoices()->exists()
            || $this->contributionInvoices()->exists()
            || $this->bankTransactions()->exists()
            || $this->financeEntries()->exists()
            || $this->sportEvents()->exists()
            || $this->teams()->exists();
    }
}
