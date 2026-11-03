<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrainingAiGeneration extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'encrypted:array', 'result' => 'encrypted:array'];
    }
}
