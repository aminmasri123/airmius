<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercePriceList extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'created_by',
        'name',
        'version',
        'status',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
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

    public function items()
    {
        return $this->hasMany(CommercePriceListItem::class);
    }

    public function assortments()
    {
        return $this->hasMany(EventCommerceAssortment::class);
    }

    public function scopeActiveFor(Builder $query, mixed $moment = null): Builder
    {
        $moment ??= now();

        return $query
            ->where('status', 'active')
            ->where('valid_from', '<=', $moment)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('valid_until')
                ->orWhere('valid_until', '>', $moment));
    }
}
