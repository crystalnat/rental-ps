<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Store;
use Illuminate\Support\Facades\Cache;

$storeId = 2;
$store = Store::find($storeId);
if (!$store) {
    echo "Store 2 not found\n";
    exit;
}

$cacheKey = "brand_categories_{$store->brand_id}";
$categories = Cache::get($cacheKey);

echo "Cache Key: {$cacheKey}\n";
if ($categories === null) {
    echo "Cache is EMPTY (null)\n";
} else {
    echo "Cache contains data of type: " . gettype($categories) . "\n";
    if (is_array($categories)) {
        echo "Found " . count($categories) . " categories in cache.\n";
        print_r($categories[0] ?? "First element missing");
    } else {
        echo "Data is NOT an array.\n";
        print_r($categories);
    }
}
