<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Store;
use App\Models\Category;

$storeId = 2;
$store = Store::find($storeId);
if (!$store) {
    echo "Store 2 not found\n";
    exit;
}

$categories = Category::where('brand_id', $store->brand_id)
    ->where('is_active', true)
    ->orderBy('sort_order', 'asc')
    ->orderBy('name', 'asc')
    ->get(['id', 'name', 'color']);

echo "Store Name: " . $store->name . " | Brand ID: " . $store->brand_id . "\n";
echo "Found " . $categories->count() . " categories:\n";
foreach ($categories as $cat) {
    echo "ID: {$cat->id} | Name: {$cat->name} | Color: {$cat->color}\n";
}
