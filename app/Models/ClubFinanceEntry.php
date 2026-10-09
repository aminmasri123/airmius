<?php

namespace App\Models;

use App\Models\Concerns\GuardsClosedClubFinanceYears;
use App\Services\ClubYearPeriodResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubFinanceEntry extends Model
{
    use GuardsClosedClubFinanceYears;
    use HasFactory;

    public const TYPES = ['income', 'expense'];

    public const ACCOUNTS = ['cash', 'bank'];

    protected $fillable = [
        'club_id',
        'club_money_account_id',
        'entry_kind',
        'transfer_key',
        'team_id',
        'club_budget_id',
        'club_department_id',
        'club_project_id',
        'club_cost_center_id',
        'club_accounting_account_id',
        'club_business_partner_id',
        'user_id',
        'receipt_file_id',
        'reversal_of_id',
        'type',
        'account',
        'category',
        'title',
        'amount',
        'booked_on',
        'reference',
        'description',
        'correction_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'booked_on' => 'date',
            'correction_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if ($entry->club_id && $entry->booked_on) {
                $entry->business_year_period_id ??= app(ClubYearPeriodResolver::class)->idFor(
                    (int) $entry->club_id,
                    'business',
                    $entry->booked_on,
                );
            }
        });
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function businessYearPeriod()
    {
        return $this->belongsTo(ClubYearPeriod::class, 'business_year_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receiptFile()
    {
        return $this->belongsTo(File::class, 'receipt_file_id');
    }

    public function receiptUploads()
    {
        return $this->hasMany(ClubReceiptUpload::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals()
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
