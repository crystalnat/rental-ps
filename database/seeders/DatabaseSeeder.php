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
            'name'     => 'Kopi Nusantara',
            'slug'     => 'kopi-nusantara',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'tax_rate' => 11.00,
            'is_active' => true,
        ]);

        $owner = User::create([
            'brand_id'  => $brand->id,
            'store_id'  => null,
            'name'      => 'Owner',
            'email'     => 'owner@kopinusantara.id',
            'password'  => Hash::make('password'),
            'role'      => 'owner',
            'is_active' => true,
        ]);

        $store = Store::create([
            'brand_id'  => $brand->id,
            'name'      => 'Kopi Nusantara - Pusat',
            'slug'      => 'kopi-nusantara-pusat',
            'city'      => 'Jakarta',
            'province'  => 'DKI Jakarta',
            'phone'     => '021-12345678',
            'is_active' => true,
        ]);

        $storeB = Store::create([
            'brand_id'  => $brand->id,
            'name'      => 'Kopi Nusantara - Cabang Selatan',
            'slug'      => 'kopi-nusantara-selatan',
            'city'      => 'Depok',
            'province'  => 'Jawa Barat',
            'phone'     => '021-98765432',
            'is_active' => true,
        ]);

        User::create([
            'brand_id'  => $brand->id,
            'store_id'  => $store->id,
            'name'      => 'Admin Pusat',
            'email'     => 'admin@kopinusantara.id',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $cashier = User::create([
            'brand_id'  => $brand->id,
            'store_id'  => $store->id,
            'name'      => 'Kasir 1',
            'email'     => 'kasir1@kopinusantara.id',
            'password'  => Hash::make('password'),
            'role'      => 'cashier',
            'is_active' => true,
        ]);

        foreach (range(1, 8) as $n) {
            DiningTable::create([
                'store_id' => $store->id,
                'name'     => "Meja {$n}",
                'qr_code'  => Str::uuid()->toString(),
                'capacity' => 4,
                'floor'    => 'Lantai 1',
                'status'   => 'available',
                'is_active' => true,
            ]);
        }

        foreach (range(1, 4) as $n) {
            DiningTable::create([
                'store_id' => $store->id,
                'name'     => "VIP-0{$n}",
                'qr_code'  => Str::uuid()->toString(),
                'capacity' => 6,
                'floor'    => 'Lantai 2',
                'status'   => 'available',
                'is_active' => true,
            ]);
        }

        $catMinuman = Category::create([
            'brand_id'   => $brand->id,
            'name'       => 'Minuman',
            'slug'       => 'minuman',
            'icon'       => 'coffee',
            'color'      => '#6F4E37',
            'sort_order' => 1,
            'is_active'  => true,
        ]);

        $catMakanan = Category::create([
            'brand_id'   => $brand->id,
            'name'       => 'Makanan',
            'slug'       => 'makanan',
            'icon'       => 'utensils',
            'color'      => '#E67E22',
            'sort_order' => 2,
            'is_active'  => true,
        ]);

        $products = [
            ['name' => 'Kopi Susu', 'sku' => 'KSS-001', 'category_id' => $catMinuman->id, 'buy' => 8000, 'sell' => 22000],
            ['name' => 'Americano', 'sku' => 'AMR-001', 'category_id' => $catMinuman->id, 'buy' => 6000, 'sell' => 18000],
            ['name' => 'Matcha Latte', 'sku' => 'MTL-001', 'category_id' => $catMinuman->id, 'buy' => 10000, 'sell' => 25000],
            ['name' => 'Es Teh Manis', 'sku' => 'ETM-001', 'category_id' => $catMinuman->id, 'buy' => 2000, 'sell' => 8000],
            ['name' => 'Roti Bakar', 'sku' => 'RTB-001', 'category_id' => $catMakanan->id, 'buy' => 5000, 'sell' => 15000],
            ['name' => 'Pisang Goreng', 'sku' => 'PSG-001', 'category_id' => $catMakanan->id, 'buy' => 4000, 'sell' => 12000],
        ];

        foreach ($products as $data) {
            $product = Product::create([
                'brand_id'    => $brand->id,
                'category_id' => $data['category_id'],
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']),
                'sku'         => $data['sku'],
                'unit'        => 'porsi',
                'is_available' => true,
                'track_stock'  => true,
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

            foreach ([$store->id, $storeB->id] as $storeId) {
                StoreInventory::create([
                    'store_id'     => $storeId,
                    'product_id'   => $product->id,
                    'current_stock' => 50,
                    'min_stock'    => 10,
                ]);
            }
        }
    }
}
