<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'refund_id', 'order_item_id', 'product_id',
    'product_name', 'quantity', 'unit_price',
    'refund_amount', 'restock',
])]
class RefundItem extends Model
{
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:3',
            'unit_price'    => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'restock'       => 'boolean',
        ];
    }
}
