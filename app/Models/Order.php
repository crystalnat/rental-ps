<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'store_id', 'shift_id', 'table_id', 'customer_id', 'cashier_id', 'promo_id', 'order_code', 'type', 'status', 'notes',
    'subtotal', 'discount_amount', 'tax_rate', 'tax_amount', 'final_amount',
    'payment_method', 'payment_status', 'cash_received', 'change_amount', 'paid_at',
    'created_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
])]
class Order extends Model
{
    use SoftDeletes;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function feedback(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderFeedback::class);
    }

    public function grossProfit(): float
    {
        return (float) $this->items->sum(fn ($item) =>
            ($item->unit_price - $item->buy_price) * $item->quantity
        );
    }

    protected function casts(): array
    {
        return [
            'subtotal'            => 'decimal:2',
            'discount_amount'     => 'decimal:2',
            'tax_rate'            => 'decimal:2',
            'tax_amount'          => 'decimal:2',
            'final_amount'        => 'decimal:2',
            'cash_received'       => 'decimal:2',
            'change_amount'       => 'decimal:2',
            'paid_at'             => 'datetime',
            'completed_at'        => 'datetime',
            'cancelled_at'        => 'datetime',
        ];
    }
}
