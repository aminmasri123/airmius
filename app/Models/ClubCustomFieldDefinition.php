<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubCustomFieldDefinition extends Model
{
    use HasFactory;

    public const ENTITY_TYPES = ['member', 'team', 'event', 'inventory_item'];

    public const FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'boolean', 'select'];

    protected $fillable = [
        'club_id', 'entity_type', 'key', 'label', 'field_type', 'options', 'is_required',
        'is_sensitive', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function values()
    {
        return $this->hasMany(ClubCustomFieldValue::class);
    }
}
