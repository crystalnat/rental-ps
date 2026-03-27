<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['brand_id', 'category_id', 'name', 'slug', 'sku', 'description', 'image', 'unit', 'is_available', 'track_stock', 'discount_percent', 'is_active'])]
class Product extends Model
{
    use SoftDeletes;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(PriceLog::class);
    }

    /**
     * Active price for a specific store (or global fallback if store_id is null).
     */
    public function currentPrice(?int $storeId = null): HasOne
    {
        return $this->hasOne(PriceLog::class)
            ->where(fn ($q) => $storeId
                ? $q->where('store_id', $storeId)->orWhereNull('store_id')
                : $q->whereNull('store_id'))
            ->whereNull('ended_at')
            ->latest('started_at');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(StoreInventory::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class)->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'is_available'     => 'boolean',
            'track_stock'      => 'boolean',
            'is_active'        => 'boolean',
            'discount_percent' => 'float',
        ];
    }
}
