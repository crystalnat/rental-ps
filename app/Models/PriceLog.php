<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'store_id', 'buy_price', 'sell_price', 'started_at', 'ended_at', 'created_by'])]
class PriceLog extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function margin(): float
    {
        if ($this->sell_price <= 0) {
            return 0;
        }

        return (($this->sell_price - $this->buy_price) / $this->sell_price) * 100;
    }

    protected function casts(): array
    {
        return [
            'buy_price'  => 'decimal:2',
            'sell_price' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }
}
