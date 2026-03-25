<?php

use App\Models\Brand;
use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Brand::all() as $brand) {
            PaymentMethod::firstOrCreate(
                ['brand_id' => $brand->id, 'code' => 'e_wallet'],
                [
                    'name'                => 'E-Wallet',
                    'sort_order'           => 4,
                    'is_active'            => true,
                    'requires_cash_input'  => false,
                ]
            );
        }
    }

    public function down(): void
    {
        PaymentMethod::where('code', 'e_wallet')->delete();
    }
};
