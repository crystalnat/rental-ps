<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'store_id', 'product_id', 'quantity', 'buy_price', 'total_amount',
    'add_to_stock', 'add_to_expense', 'daily_expense_id', 'notes', 'created_by',
])]
class StockIn extends Model
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dailyExpense(): BelongsTo
    {
        return $this->belongsTo(DailyExpense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMutations(): MorphMany
    {
        return $this->morphMany(StockMutation::class, 'mutatable');
    }

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:3',
            'buy_price'    => 'decimal:2',
            'total_amount' => 'decimal:2',
            'add_to_stock' => 'boolean',
            'add_to_expense' => 'boolean',
        ];
    }
}
