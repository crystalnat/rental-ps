<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;

$colors = [
    '#dc2626', // Red
    '#ea580c', // Orange
    '#d97706', // Amber
    '#ca8a04', // Yellow
    '#65a30d', // Lime
    '#16a34a', // Green
    '#059669', // Emerald
    '#0d9488', // Teal
    '#0891b2', // Cyan
    '#0284c7', // Sky
    '#2563eb', // Blue
    '#4f46e5', // Indigo
    '#7c3aed', // Violet
    '#9333ea', // Purple
    '#c026d3', // Fuchsia
    '#db2777', // Pink
    '#e11d48', // Rose
    '#4b5563', // Slate
];

$categories = Category::all();
foreach ($categories as $cat) {
    $randomColor = $colors[array_rand($colors)];
    $cat->update(['color' => $randomColor]);
    echo "Updated Category: {$cat->name} with Color: {$randomColor}\n";
}
