<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubCustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'club_custom_field_definition_id', 'subject_type', 'subject_id',
        'payload', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'subject_id' => 'integer'];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function definition()
    {
        return $this->belongsTo(ClubCustomFieldDefinition::class, 'club_custom_field_definition_id');
    }
}
