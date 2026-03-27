<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    protected $fillable = [
        'brand_id',
        'code',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'valid_from',
        'valid_until',
        'quota',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'value' => 'float',
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'valid_from' => 'datetime:Y-m-d',
        'valid_until' => 'datetime:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->quota !== null && $this->used_count >= $this->quota) {
            return false;
        }

        $now = now()->startOfDay();

        if ($this->valid_from && $now->lt(\Carbon\Carbon::parse($this->valid_from)->startOfDay())) {
            return false;
        }

        if ($this->valid_until && $now->gt(\Carbon\Carbon::parse($this->valid_until)->endOfDay())) {
            return false;
        }

        return true;
    }
}
