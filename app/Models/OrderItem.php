<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'price_log_id', 'product_name', 'quantity', 'unit', 'unit_price', 'buy_price', 'discount_amount', 'subtotal', 'notes'])]
class OrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceLog(): BelongsTo
    {
        return $this->belongsTo(PriceLog::class);
    }

    public function margin(): float
    {
        if ($this->unit_price <= 0) {
            return 0;
        }

        return (($this->unit_price - $this->buy_price) / $this->unit_price) * 100;
    }

    public function grossProfit(): float
    {
        return ($this->unit_price - $this->buy_price - $this->discount_amount) * $this->quantity;
    }

    protected function casts(): array
    {
        return [
            'quantity'        => 'decimal:3',
            'unit_price'      => 'decimal:2',
            'buy_price'       => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal'        => 'decimal:2',
        ];
    }
}
