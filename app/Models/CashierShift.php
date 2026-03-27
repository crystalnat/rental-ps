<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'store_id', 'user_id',
    'scheduled_start', 'scheduled_end',
    'opened_at', 'closed_at',
    'opening_cash', 'expected_cash', 'actual_cash', 'cash_difference',
    'total_sales', 'total_orders', 'total_cash_sales', 'total_non_cash_sales',
    'total_expenses', 'total_refunds',
    'notes', 'status',
])]
class CashierShift extends Model
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    protected function casts(): array
    {
        return [
            'opened_at'           => 'datetime',
            'closed_at'           => 'datetime',
            'opening_cash'        => 'decimal:2',
            'expected_cash'       => 'decimal:2',
            'actual_cash'         => 'decimal:2',
            'cash_difference'     => 'decimal:2',
            'total_sales'         => 'decimal:2',
            'total_cash_sales'    => 'decimal:2',
            'total_non_cash_sales'=> 'decimal:2',
            'total_expenses'      => 'decimal:2',
            'total_refunds'       => 'decimal:2',
            'total_orders'        => 'integer',
        ];
    }
}
