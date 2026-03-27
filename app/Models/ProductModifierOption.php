<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierOption extends Model
{
    protected $fillable = ['modifier_group_id', 'name', 'price_extra', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'price_extra' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifierGroup::class, 'modifier_group_id');
    }
}
