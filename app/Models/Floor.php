<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['store_id', 'name', 'width_meters', 'length_meters', 'sort_order', 'is_active'])]
class Floor extends Model
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class, 'floor_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(FloorElement::class, 'floor_id')->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'width_meters'  => 'float',
            'length_meters' => 'float',
            'sort_order'    => 'integer',
            'is_active'     => 'boolean',
        ];
    }
}
