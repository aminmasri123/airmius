<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubSepaFeeRecharge extends Model
{
    protected $guarded = ['id'];

    public function toArray(): array
    {
        $data = parent::toArray();
        if ($this->relationLoaded('member')) {
            $data['member'] = $this->member?->only(['id', 'name']);
        }

        return $data;
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function voidRequests()
    {
        return $this->hasMany(ClubSepaFeeRechargeVoid::class, 'recharge_id')->orderBy('id');
    }

    public function creditRequests()
    {
        return $this->hasMany(ClubSepaFeeRechargeCredit::class, 'recharge_id')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['fee_revision' => 'integer', 'fee_amount_cents' => 'integer', 'amount_cents' => 'integer', 'due_date' => 'date', 'cancelled_at' => 'datetime', 'approved_at' => 'datetime', 'review_required' => 'boolean'];
    }
}
