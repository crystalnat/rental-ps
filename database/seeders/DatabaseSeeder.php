<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\PriceLog;
use App\Models\Store;
use App\Models\StoreInventory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $brand = Brand::create([
            'name'     => 'Crystal Rental PS',
            'slug'     => 'crystal-rental-ps',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'tax_rate' => 0.00,
            'is_active' => true,
        ]);

        $defaultPaymentMethods = [
            ['name' => 'Tunai', 'code' => 'cash', 'sort_order' => 1, 'requires_cash_input' => true],
            ['name' => 'QRIS', 'code' => 'qris', 'sort_order' => 2, 'requires_cash_input' => false],
            ['name' => 'Transfer Bank', 'code' => 'bank_transfer', 'sort_order' => 3, 'requires_cash_input' => false],
            ['name' => 'E-Wallet', 'code' => 'e_wallet', 'sort_order' => 4, 'requires_cash_input' => false],
        ];

        foreach ($defaultPaymentMethods as $pm) {
            \App\Models\PaymentMethod::firstOrCreate(
                ['brand_id' => $brand->id, 'code' => $pm['code']],
                [
                    'name' => $pm['name'],
                    'sort_order' => $pm['sort_order'],
                    'is_active' => true,
                    'requires_cash_input' => $pm['requires_cash_input'],
                ]
            );
        }

        $owner = User::create([
            'brand_id'  => $brand->id,
            'store_id'  => null,
            'name'      => 'Owner Crystal',
            'email'     => 'owner@crystalrental.id',
            'password'  => Hash::make('password'),
            'role'      => 'owner',
            'is_active' => true,
        ]);

        $store = Store::create([
            'brand_id'  => $brand->id,
            'name'      => 'Crystal Rental PS - Pusat',
            'slug'      => 'crystal-rental-pusat',
            'city'      => 'Jakarta',
            'province'  => 'DKI Jakarta',
            'phone'     => '081234567890',
            'is_active' => true,
        ]);

        User::create([
            'brand_id'  => $brand->id,
            'store_id'  => $store->id,
            'name'      => 'Admin Crystal',
            'email'     => 'admin@crystalrental.id',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $cashier = User::create([
            'brand_id'  => $brand->id,
            'store_id'  => $store->id,
            'name'      => 'Kasir 1',
            'email'     => 'kasir1@crystalrental.id',
            'password'  => Hash::make('password'),
            'role'      => 'cashier',
            'is_active' => true,
        ]);

        // Creating Floor
        $floor = \App\Models\Floor::create([
            'store_id' => $store->id,
            'name' => 'Area Utama',
            'width_meters' => 12,
            'length_meters' => 8,
            'sort_order' => 1,
        ]);

        // Seed 12 PS Units
        foreach (range(1, 12) as $n) {
            $isVip = $n > 8;
            $name = $isVip ? "PS-VIP " . ($n - 8) : "PS-" . str_pad($n, 2, '0', STR_PAD_LEFT);
            DiningTable::create([
                'store_id' => $store->id,
                'floor_id' => $floor->id,
                'name'     => $name,
                'qr_code'  => Str::uuid()->toString(),
                'capacity' => $isVip ? 4 : 2,
                'floor'    => 'Area Utama',
                'status'   => 'available',
                'is_active' => true,
                'rental_price_per_hour' => $isVip ? 15000 : 10000,
                // These will be filled by the user, but we seed placeholders
                'tuya_device_id' => null,
                'tuya_status' => false,
                // Layout positioning (example)
                'x_meters' => (($n-1) % 4) * 2.5 + 1,
                'y_meters' => floor(($n-1) / 4) * 2 + 1,
                'width_meters' => 1.2,
                'length_meters' => 0.8,
            ]);
        }

        $catRental = Category::create([
            'brand_id'   => $brand->id,
            'name'       => 'Rental PS',
            'slug'       => 'rental-ps',
            'icon'       => 'gamepad',
            'color'      => '#9C27B0',
            'sort_order' => 1,
            'is_active'  => true,
        ]);

        $catMinuman = Category::create([
            'brand_id'   => $brand->id,
            'name'       => 'Minuman',
            'slug'       => 'minuman',
            'icon'       => 'coffee',
            'color'      => '#2196F3',
            'sort_order' => 2,
            'is_active'  => true,
        ]);

        $catSnack = Category::create([
            'brand_id'   => $brand->id,
            'name'       => 'Snack',
            'slug'       => 'snack',
            'icon'       => 'cookie',
            'color'      => '#FF9800',
            'sort_order' => 3,
            'is_active'  => true,
        ]);

        $products = [
            ['name' => 'Sewa PS4 (1 Jam)', 'sku' => 'PS4-H', 'category_id' => $catRental->id, 'buy' => 0, 'sell' => 10000],
            ['name' => 'Sewa PS5 (1 Jam)', 'sku' => 'PS5-H', 'category_id' => $catRental->id, 'buy' => 0, 'sell' => 15000],
            ['name' => 'Indomie Goreng', 'sku' => 'IDM-G', 'category_id' => $catSnack->id, 'buy' => 3500, 'sell' => 8000],
            ['name' => 'Indomie Kuah', 'sku' => 'IDM-K', 'category_id' => $catSnack->id, 'buy' => 3500, 'sell' => 8000],
            ['name' => 'Es Teh Manis', 'sku' => 'ETM-01', 'category_id' => $catMinuman->id, 'buy' => 1000, 'sell' => 5000],
            ['name' => 'Kopi Kapal Api', 'sku' => 'KKO-01', 'category_id' => $catMinuman->id, 'buy' => 2000, 'sell' => 5000],
            ['name' => 'Coca Cola', 'sku' => 'CCL-01', 'category_id' => $catMinuman->id, 'buy' => 4500, 'sell' => 8000],
        ];

        foreach ($products as $data) {
            $product = Product::create([
                'brand_id'    => $brand->id,
                'category_id' => $data['category_id'],
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']),
                'sku'         => $data['sku'],
                'unit'        => 'pcs',
                'is_available' => true,
                'track_stock'  => $data['buy'] > 0,
                'is_active'    => true,
            ]);

            PriceLog::create([
                'product_id'  => $product->id,
                'store_id'    => null,
                'buy_price'   => $data['buy'],
                'sell_price'  => $data['sell'],
                'started_at'  => now(),
                'ended_at'    => null,
                'created_by'  => $owner->id,
            ]);

            StoreInventory::create([
                'store_id'     => $store->id,
                'product_id'   => $product->id,
                'current_stock' => 100,
                'min_stock'    => 10,
            ]);
        }

    }
}
