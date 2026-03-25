<?php

use App\Models\Brand;
use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['name' => 'Tunai', 'code' => 'cash', 'sort_order' => 1, 'requires_cash_input' => true],
            ['name' => 'QRIS', 'code' => 'qris', 'sort_order' => 2, 'requires_cash_input' => false],
            ['name' => 'Transfer Bank', 'code' => 'bank_transfer', 'sort_order' => 3, 'requires_cash_input' => false],
            ['name' => 'E-Wallet', 'code' => 'e_wallet', 'sort_order' => 4, 'requires_cash_input' => false],
            ['name' => 'Lainnya', 'code' => 'other', 'sort_order' => 5, 'requires_cash_input' => false],
        ];

        foreach (Brand::all() as $brand) {
            foreach ($defaults as $d) {
                PaymentMethod::firstOrCreate(
                    ['brand_id' => $brand->id, 'code' => $d['code']],
                    [
                        'name'                => $d['name'],
                        'sort_order'          => $d['sort_order'],
                        'is_active'           => true,
                        'requires_cash_input' => $d['requires_cash_input'],
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        PaymentMethod::truncate();
    }
};
