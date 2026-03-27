<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    protected $fillable = ['order_item_id', 'modifier_option_id', 'modifier_group_name', 'modifier_option_name', 'price_extra'];

    protected $casts = [
        'price_extra' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductModifierOption::class, 'modifier_option_id');
    }
}
