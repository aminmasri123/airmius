<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubReceiptUpload extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending_confirmation';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'club_id',
        'uploaded_by',
        'file_id',
        'club_finance_entry_id',
        'status',
        'scan_status',
        'sha256',
        'mime_type',
        'size_bytes',
        'ocr_suggestion',
        'confirmed_payload',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'ocr_suggestion' => 'array',
            'confirmed_payload' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function financeEntry()
    {
        return $this->belongsTo(ClubFinanceEntry::class, 'club_finance_entry_id');
    }
}
