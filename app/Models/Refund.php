<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'order_id', 'store_id', 'shift_id', 'user_id', 'refund_code',
    'type', 'reason', 'refund_amount', 'refund_method',
    'status', 'processed_at',
])]
class Refund extends Model
{
    use SoftDeletes;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    /**
     * Total refund (completed) untuk satu/kumpulan toko dalam rentang tanggal.
     * Dipakai pembukuan agar omzet terpotong refund.
     *
     * @param  int|array|\Illuminate\Support\Collection  $storeIds
     */
    public static function sumFor($storeIds, string $dateFrom, string $dateTo): float
    {
        $ids = $storeIds instanceof \Illuminate\Support\Collection
            ? $storeIds->all()
            : (array) $storeIds;

        return (float) static::whereIn('store_id', $ids)
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('refund_amount');
    }

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'processed_at'  => 'datetime',
            'restock'       => 'boolean',
        ];
    }
}
