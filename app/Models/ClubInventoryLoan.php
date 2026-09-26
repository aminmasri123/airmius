<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubInventoryLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_inventory_item_id', 'borrower_id', 'responsible_user_id', 'external_renter_name',
        'external_renter_email', 'external_renter_phone', 'requested_by', 'checked_out_by',
        'returned_to', 'responsibility_transferred_to', 'quantity', 'booking_priority', 'rental_type', 'status',
        'rental_contract_number', 'rental_price_cents', 'rental_deposit_cents', 'rental_deposit_held_cents',
        'rental_deposit_refunded_cents', 'rental_damage_claim_cents', 'rental_price_snapshot',
        'handover_protocol', 'return_protocol', 'rental_invoice_id', 'checked_out_at', 'starts_at',
        'issued_at', 'due_at', 'returned_at', 'lost_at', 'damaged_at',
        'responsibility_transferred_at', 'return_condition', 'damage_description', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'booking_priority' => 'integer',
        'rental_price_cents' => 'integer',
        'rental_deposit_cents' => 'integer',
        'rental_deposit_held_cents' => 'integer',
        'rental_deposit_refunded_cents' => 'integer',
        'rental_damage_claim_cents' => 'integer',
        'rental_price_snapshot' => 'array',
        'handover_protocol' => 'array',
        'return_protocol' => 'array',
        'checked_out_at' => 'datetime',
        'starts_at' => 'datetime',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'lost_at' => 'datetime',
        'damaged_at' => 'datetime',
        'responsibility_transferred_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function item()
    {
        return $this->belongsTo(ClubInventoryItem::class, 'club_inventory_item_id');
    }

    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function returnedTo()
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    public function responsibilityTransferredTo()
    {
        return $this->belongsTo(User::class, 'responsibility_transferred_to');
    }

    public function rentalInvoice()
    {
        return $this->belongsTo(Invoice::class, 'rental_invoice_id');
    }
}
