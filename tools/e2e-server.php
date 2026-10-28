<?php

require __DIR__.'/e2e-bootstrap.php';

use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\Market;
use App\Models\PickupPoint;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\ContentService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);
$admin = User::create(['name' => 'Administration E2E', 'email' => 'e2e-admin@example.test', 'password' => 'E2e-Only-123456!']);
$admin->assignRole(StaffRole::Administrator);
foreach ([StaffRole::Manager, StaffRole::Staff] as $role) {
    $user = User::create(['name' => 'Recette '.$role->value, 'email' => 'e2e-'.$role->value.'@example.test', 'password' => 'E2e-Only-123456!']);
    $user->assignRole($role);
}
if (config('storefront.markets') === ['CM']) {
    $market = Market::where('code', 'CM')->firstOrFail();
    $location = StockLocation::where('code', 'CM-DOUALA-CHACHA223')->firstOrFail();
    $product = app(CatalogService::class)->save($admin, ['name' => 'Pièce E2E', 'slug' => 'piece-e2e', 'category_id' => Category::firstOrFail()->id,
        'status' => 'published', 'markets' => [['market_id' => $market->id, 'is_available' => true]],
        'variants' => [['sku' => 'E2E-001', 'label' => 'Standard', 'is_active' => true, 'prices' => [['market_id' => $market->id, 'amount' => '25000']]]]]);
    app(InventoryService::class)->create($admin, ['stock_location_id' => $location->id, 'product_variant_id' => $product->variants->first()->id,
        'quantity_on_hand' => 12, 'low_stock_threshold' => 2, 'reason' => 'Stock de test navigateur uniquement', 'request_key' => (string) Str::uuid()]);
} else {
    $market = Market::where('code', 'CA')->firstOrFail();
    $location = StockLocation::where('market_id', $market->id)->firstOrFail();
    $location->update(['name' => 'Dépôt E2E', 'address' => 'Adresse fictive E2E', 'is_placeholder' => false, 'is_active' => true]);
    $cameroon = Market::where('code', 'CM')->firstOrFail();
    $product = app(CatalogService::class)->save($admin, ['name' => 'Pièce E2E', 'slug' => 'piece-e2e', 'category_id' => Category::firstOrFail()->id,
        'status' => 'published', 'markets' => [['market_id' => $market->id, 'is_available' => true], ['market_id' => $cameroon->id, 'is_available' => true]],
        'variants' => [['sku' => 'E2E-001', 'label' => 'Standard', 'is_active' => true, 'prices' => [['market_id' => $market->id, 'amount' => '50'], ['market_id' => $cameroon->id, 'amount' => '25000']]]]]);
    app(InventoryService::class)->create($admin, ['stock_location_id' => $location->id, 'product_variant_id' => $product->variants->first()->id,
        'quantity_on_hand' => 12, 'low_stock_threshold' => 2, 'reason' => 'Stock de test navigateur', 'request_key' => (string) Str::uuid()]);
    PickupPoint::create(['market_id' => $market->id, 'stock_location_id' => $location->id, 'name' => 'Point E2E', 'address' => 'Retrait fictif E2E', 'is_active' => true]);
    $cmLocation = StockLocation::where('market_id', $cameroon->id)->firstOrFail();
    $cmLocation->update(['name' => 'Dépôt CM E2E', 'address' => 'Adresse fictive CM E2E', 'is_placeholder' => false, 'is_active' => true]);
    app(InventoryService::class)->create($admin, ['stock_location_id' => $cmLocation->id, 'product_variant_id' => $product->variants->first()->id,
        'quantity_on_hand' => 12, 'low_stock_threshold' => 2, 'reason' => 'Stock CM de test navigateur', 'request_key' => (string) Str::uuid()]);
    PickupPoint::create(['market_id' => $cameroon->id, 'stock_location_id' => $cmLocation->id, 'name' => 'Point CM E2E', 'address' => 'Retrait fictif CM E2E', 'is_active' => true]);
}
app(ContentService::class)->savePage($admin, ['title' => 'Notre maison E2E', 'slug' => 'notre-maison-e2e', 'body' => "Texte de présentation E2E\n<script>window.cmsInjected=true</script>", 'meta_description' => 'Présentation E2E', 'is_published' => true]);
app(ContentService::class)->saveBanner($admin, ['title' => 'Bienvenue E2E', 'body' => 'Découvrez notre sélection', 'sort_order' => 0, 'is_published' => true, 'link_path' => '/pages/notre-maison-e2e', 'link_label' => 'Notre histoire E2E']);
$relatedMarkets = Market::where('is_active', true)->whereIn('code', config('storefront.markets'))->get();
app(CatalogService::class)->save($admin, ['name' => 'Création assortie', 'slug' => 'creation-assortie', 'category_id' => $product->category_id,
    'status' => 'published', 'markets' => $relatedMarkets->map(fn ($item) => ['market_id' => $item->id, 'is_available' => true])->all(),
    'variants' => [['sku' => 'E2E-RELATED', 'label' => 'Standard', 'is_active' => true,
        'prices' => $relatedMarkets->map(fn ($item) => ['market_id' => $item->id, 'amount' => $item->code === 'CA' ? '49' : '22000'])->all()]]]);
$process = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port=8765', '--no-reload'], dirname(__DIR__));
$process->setTimeout(null);
exit($process->run(fn ($type, $output) => print ($output)));
