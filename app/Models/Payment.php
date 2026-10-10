<?php

namespace App\Models;

use App\Models\Concerns\GuardsClosedClubFinanceYears;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use GuardsClosedClubFinanceYears;

    protected ?string $sepaReturnBookedOn = null;

    public function recordSepaReturn(string $bookedOn): void
    {
        $this->sepaReturnBookedOn = $bookedOn;
        try {
            $this->update(['status' => 'returned']);
        } finally {
            $this->sepaReturnBookedOn = null;
        }
    }

    use HasFactory;

    protected $fillable = [
        'club_id',
        'club_money_account_id',
        'team_id',
        'club_budget_id',
        'club_department_id',
        'club_project_id',
        'club_cost_center_id',
        'user_id',
        'club_external_member_id',
        'invoice_id',
        'purpose',
        'amount',
        'status',
        'method',
        'reference',
        'receipt_number',
        'donation_number',
        'donation_type',
        'donation_restriction',
        'donation_campaign',
        'donor_type',
        'donor_snapshot',
        'club_business_partner_id',
        'sponsor_id',
        'paid_at',
        'notes',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'donor_snapshot' => 'array',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function moneyAccount()
    {
        return $this->belongsTo(ClubMoneyAccount::class, 'club_money_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function externalMember()
    {
        return $this->belongsTo(ClubExternalMember::class, 'club_external_member_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function bookingReceipts()
    {
        return $this->hasMany(PaymentBookingReceipt::class);
    }
}
