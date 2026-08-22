<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubInventoryLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_inventory_item_id', 'borrower_id', 'checked_out_by',
        'returned_to', 'quantity', 'status', 'checked_out_at', 'due_at',
        'returned_at', 'return_condition', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'checked_out_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
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

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function returnedTo()
    {
        return $this->belongsTo(User::class, 'returned_to');
    }
}
