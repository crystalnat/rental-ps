<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\PriceLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

$json = '[
  {"name":"Good Day Original","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Freeze","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Cappuccino","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Mocacinno","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Coolin Coffee","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Carrebian Nut","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Vanilla Latte","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Chococinno","category":"Sachet Kopi","price":4000},
  {"name":"Good Day Tiramisu Bliss","category":"Sachet Kopi","price":4000},
  {"name":"Kapal Api Special Mix","category":"Sachet Kopi","price":3500},
  {"name":"Kapal Api Mantap","category":"Sachet Kopi","price":3500},
  {"name":"Kapal Api Susu","category":"Sachet Kopi","price":3500},
  {"name":"Kapal Api White Coffee","category":"Sachet Kopi","price":3500},
  {"name":"ABC Susu","category":"Sachet Kopi","price":3000},
  {"name":"ABC Mocca","category":"Sachet Kopi","price":3000},
  {"name":"ABC Kopi Hitam","category":"Sachet Kopi","price":3000},
  {"name":"ABC White Coffee","category":"Sachet Kopi","price":3000},
  {"name":"ABC Brown Coffee","category":"Sachet Kopi","price":3000},
  {"name":"Indocafe Coffeemix","category":"Sachet Kopi","price":4000},
  {"name":"Indocafe Fine Blend","category":"Sachet Kopi","price":4000},
  {"name":"Indocafe Cappuccino","category":"Sachet Kopi","price":4000},
  {"name":"Indocafe White Coffee","category":"Sachet Kopi","price":4000},
  {"name":"Torabika Cappuccino","category":"Sachet Kopi","price":3500},
  {"name":"Torabika Duo","category":"Sachet Kopi","price":3500},
  {"name":"Torabika Susu","category":"Sachet Kopi","price":3500},
  {"name":"Torabika Creamy Latte","category":"Sachet Kopi","price":3500},
  {"name":"Luwak White Coffee","category":"Sachet Kopi","price":4000},
  {"name":"Luwak Kopi Hitam","category":"Sachet Kopi","price":4000},
  {"name":"Luwak Kopi Tubruk","category":"Sachet Kopi","price":4000},
  {"name":"Milo","category":"Sachet Coklat","price":4000},
  {"name":"Chocolatos Drink","category":"Sachet Coklat","price":3000},
  {"name":"Dancow Sachet","category":"Sachet Susu","price":3000},
  {"name":"Indomilk Sachet","category":"Sachet Susu","price":3000},
  {"name":"Frisian Flag Sachet","category":"Sachet Susu","price":3000},
  {"name":"Energen Coklat","category":"Sachet Sereal","price":3000},
  {"name":"Energen Vanila","category":"Sachet Sereal","price":3000},
  {"name":"Energen Jahe","category":"Sachet Sereal","price":3000},
  {"name":"Energen Kacang Hijau","category":"Sachet Sereal","price":3000},
  {"name":"Pop Ice Coklat","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Cappuccino","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Taro","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Durian","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Strawberry","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Mangga","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Melon","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Alpukat","category":"Sachet Minuman","price":5000},
  {"name":"Nutrisari Jeruk","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Jeruk Peras","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Mangga","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Jambu","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Anggur","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Lemon Tea","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Jeruk Nipis","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Sweet Orange","category":"Sachet Minuman","price":3000},
  {"name":"Marimas Jeruk","category":"Sachet Minuman","price":2000},
  {"name":"Marimas Mangga","category":"Sachet Minuman","price":2000},
  {"name":"Marimas Anggur","category":"Sachet Minuman","price":2000},
  {"name":"Marimas Strawberry","category":"Sachet Minuman","price":2000},
  {"name":"Marimas Melon","category":"Sachet Minuman","price":2000},
  {"name":"Extra Joss","category":"Sachet Energi","price":2000},
  {"name":"Extra Joss Susu","category":"Sachet Energi","price":2000},
  {"name":"Kuku Bima Ener-G Original","category":"Sachet Energi","price":2000},
  {"name":"Kuku Bima Ener-G Anggur","category":"Sachet Energi","price":2000},
  {"name":"Hemaviton Jreng","category":"Sachet Energi","price":2000},
  {"name":"Jahe Wangi","category":"Sachet Tradisional","price":2500},
  {"name":"Wedang Jahe","category":"Sachet Tradisional","price":2500},
  {"name":"Bandrek","category":"Sachet Tradisional","price":2500},
  {"name":"Susu Jahe","category":"Sachet Tradisional","price":3000},
  {"name":"Teh Celup Sosro","category":"Sachet Teh","price":2000},
  {"name":"Teh Pucuk Sachet","category":"Sachet Teh","price":2000},
  {"name":"Teh Tarik Sachet","category":"Sachet Teh","price":3000},
  {"name":"Kopi Jahe Sachet","category":"Sachet Kopi","price":3000},
  {"name":"White Koffie","category":"Sachet Kopi","price":3500},
  {"name":"Top Coffee","category":"Sachet Kopi","price":3000},
  {"name":"Kopi Luwak 3in1","category":"Sachet Kopi","price":4000},
  {"name":"Nutrisari Blewah","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Sirsak","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Kelapa Muda","category":"Sachet Minuman","price":3000},
  {"name":"Marimas Lemon","category":"Sachet Minuman","price":2000},
  {"name":"Marimas Jeruk Nipis","category":"Sachet Minuman","price":2000},
  {"name":"Pop Ice Bubble Gum","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Vanilla Blue","category":"Sachet Minuman","price":5000},
  {"name":"Energen Kurma","category":"Sachet Sereal","price":3000},
  {"name":"Energen Jagung","category":"Sachet Sereal","price":3000},
  {"name":"ABC Jahe Susu","category":"Sachet Kopi","price":3000},
  {"name":"Indocafe Jahe","category":"Sachet Kopi","price":4000},
  {"name":"Torabika Jahe","category":"Sachet Kopi","price":3500},
  {"name":"Good Day Avocado Delight","category":"Sachet Kopi","price":4000},
  {"name":"Pop Ice Matcha","category":"Sachet Minuman","price":5000},
  {"name":"Pop Ice Red Velvet","category":"Sachet Minuman","price":5000},
  {"name":"Nutrisari Teh Tarik","category":"Sachet Minuman","price":3000},
  {"name":"Nutrisari Es Cincau","category":"Sachet Minuman","price":3000}
]';

$data = json_decode($json, true);

$mainBrandId = 1; // Kopi Nusantara

$brands = [
    'Good Day', 'Kapal Api', 'ABC', 'Indocafe', 'Torabika', 'Luwak', 'Milo', 
    'Chocolatos', 'Dancow', 'Indomilk', 'Frisian Flag', 'Energen', 'Pop Ice', 
    'Nutrisari', 'Marimas', 'Extra Joss', 'Kuku Bima', 'Hemaviton', 'Jahe Wangi', 
    'Wedang Jahe', 'Bandrek', 'Susu Jahe', 'Teh Pucuk', 'Teh Tarik', 
    'Teh Celup Sosro' => 'Sosro',
    'White Koffie' => 'Luwak',
    'Top Coffee' => 'Top Coffee'
];

DB::transaction(function() use ($data, $mainBrandId) {
    foreach ($data as $item) {
        $name = $item['name'];
        $originalPrice = $item['price'];
        $newPrice = $originalPrice + 3000;

        // Determine Category Name (Merk)
        $categoryName = 'Lain-lain';
        $fullBrands = [
            'Good Day', 'Kapal Api', 'ABC', 'Indocafe', 'Torabika', 'Luwak', 'Milo', 
            'Chocolatos', 'Dancow', 'Indomilk', 'Frisian Flag', 'Energen', 'Pop Ice', 
            'Nutrisari', 'Marimas', 'Extra Joss', 'Kuku Bima', 'Hemaviton', 'Jahe Wangi', 
            'Wedang Jahe', 'Bandrek', 'Susu Jahe', 'Teh Pucuk', 'Teh Tarik', 'Top Coffee'
        ];

        foreach ($fullBrands as $b) {
            if (stripos($name, $b) !== false) {
                $categoryName = $b;
                break;
            }
        }
        
        if (stripos($name, 'Sosro') !== false) {
            $categoryName = 'Sosro';
        }

        // 1. Get or Create Category
        $category = Category::firstOrCreate([
            'brand_id' => $mainBrandId,
            'name' => $categoryName,
        ], [
            'slug' => Str::slug($categoryName),
            'is_active' => true,
        ]);

        // 2. Create Product
        $product = Product::create([
            'brand_id' => $mainBrandId,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'sku' => strtoupper(Str::random(10)),
            'description' => 'Produk ' . $name,
            'is_available' => true,
            'track_stock' => false,
            'is_active' => true,
        ]);

        // 3. Create Price Log
        PriceLog::create([
            'product_id' => $product->id,
            'store_id' => null, // Global price
            'buy_price' => $originalPrice,
            'sell_price' => $newPrice,
            'started_at' => now(),
            'created_by' => 1,
        ]);
        
        echo "Added: $name | Category: $categoryName | Buy: $originalPrice | Sell: $newPrice\n";
    }
});
