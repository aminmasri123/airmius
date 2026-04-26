<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id','sender_id','message', 'status', 'read_at'];
    protected $appends = ['delivery_status'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receipts()
    {
        return $this->hasMany(MessageReceipt::class);
    }

    public function getDeliveryStatusAttribute(): string
    {
        if ($this->sender_id !== auth()->id()) {
            return '';
        }

        if (!$this->relationLoaded('receipts') || $this->receipts->isEmpty()) {
            return 'sent';
        }

        if ($this->receipts->every(fn ($receipt) => $receipt->read_at !== null)) {
            return 'read';
        }

        if ($this->receipts->every(fn ($receipt) => $receipt->delivered_at !== null)) {
            return 'delivered';
        }

        return 'sent';
    }

    public function markAsRead()
    {
        if ($this->status !== 'read') {
            $this->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }
    }
}
