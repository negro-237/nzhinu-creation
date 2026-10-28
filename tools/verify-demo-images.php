<?php

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$products = Product::where('slug', 'like', 'demo-%')->with('media', 'variants.inventories')->get();
$report = $products->map(fn ($product) => [
    'slug' => $product->slug,
    'images' => $product->getMedia('images')->count(),
    'files_exist' => $product->getMedia('images')->every(fn ($image) => is_file($image->getPath())),
    'public_files_exist' => $product->getMedia('images')->every(fn ($image) => is_file(public_path('storage/'.$image->getPathRelativeToRoot()))),
    'inventory_rows' => $product->variants->sum(fn ($variant) => $variant->inventories->count()),
]);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
