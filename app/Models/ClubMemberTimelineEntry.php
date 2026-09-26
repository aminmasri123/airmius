<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubMemberTimelineEntry extends Model
{
    use HasFactory;

    public const MANUAL_TYPES = ['honor', 'anniversary', 'note'];

    public const TYPES = ['status_change', 'membership_date', ...self::MANUAL_TYPES];

    protected $fillable = [
        'club_id',
        'subject_type',
        'subject_id',
        'type',
        'title',
        'description',
        'occurred_on',
        'from_value',
        'to_value',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'from_value' => $this->from_value,
            'to_value' => $this->to_value,
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
