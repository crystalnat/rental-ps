<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['store_id', 'product_id', 'type', 'quantity', 'stock_before', 'stock_after', 'mutatable_type', 'mutatable_id', 'notes', 'created_by'])]
class StockMutation extends Model
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Polymorphic source of the mutation (Order, DailyExpense, or manual).
     */
    public function mutatable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after'  => 'decimal:3',
        ];
    }
}
