<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'name', 'code', 'sort_order', 'is_active', 'requires_cash_input', 'qrcode_image', 'account_name', 'account_number'])]
class PaymentMethod extends Model
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'requires_cash_input' => 'boolean',
        ];
    }
}
