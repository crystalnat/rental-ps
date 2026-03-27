<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['brand_id', 'name', 'slug', 'logo', 'address', 'city', 'province', 'postal_code', 'phone', 'email', 'open_time', 'close_time', 'is_active', 'receipt_print_enabled', 'daily_sales_target'])]
class Store extends Model
{
    use SoftDeletes;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class)->orderBy('sort_order');
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(StoreInventory::class);
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(PriceLog::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function dailyExpenses(): HasMany
    {
        return $this->hasMany(DailyExpense::class);
    }

    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'receipt_print_enabled' => 'boolean',
        ];
    }
}
