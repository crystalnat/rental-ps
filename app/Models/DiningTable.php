<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['store_id', 'floor_id', 'name', 'qr_code', 'capacity', 'floor', 'status', 'is_active', 'x_meters', 'y_meters', 'width_meters', 'length_meters', 'rotation_deg', 'shape'])]
class DiningTable extends Model
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function activeOrder(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id')
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    public function qrUrl(): string
    {
        return url("/order/{$this->store->slug}/{$this->qr_code}");
    }

    protected static function booted(): void
    {
        static::creating(function (self $table) {
            if (empty($table->qr_code)) {
                $table->qr_code = Str::uuid()->toString();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'capacity'     => 'integer',
            'is_active'   => 'boolean',
            'x_meters'    => 'float',
            'y_meters'    => 'float',
            'width_meters'=> 'float',
            'length_meters'=> 'float',
            'rotation_deg'=> 'integer',
        ];
    }
}
