<?php

namespace Tests\Support;

use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\Market;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

trait InventoryFixture
{
    private function fixture(int $quantity = 10): array
    {
        $this->seed(DatabaseSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole(StaffRole::Manager);
        $ca = Market::where('code', 'CA')->firstOrFail();
        $cm = Market::where('code', 'CM')->firstOrFail();
        $location = StockLocation::where('market_id', $ca->id)->firstOrFail();
        $location->update(['address' => 'Adresse de test validée', 'is_placeholder' => false, 'is_active' => true]);
        $product = app(CatalogService::class)->save($actor, [
            'name' => 'Produit inventaire', 'slug' => 'produit-inventaire', 'category_id' => Category::firstOrFail()->id,
            'status' => 'published', 'markets' => [['market_id' => $ca->id, 'is_available' => true], ['market_id' => $cm->id, 'is_available' => true]],
            'variants' => [['sku' => 'INV-001', 'label' => 'Standard', 'is_active' => true,
                'prices' => [['market_id' => $ca->id, 'amount' => '50'], ['market_id' => $cm->id, 'amount' => '25000']]]],
        ]);
        $variant = $product->variants->first();
        $inventory = app(InventoryService::class)->create($actor, [
            'stock_location_id' => $location->id, 'product_variant_id' => $variant->id, 'quantity_on_hand' => $quantity,
            'low_stock_threshold' => 2, 'reason' => 'Stock initial de test', 'request_key' => (string) Str::uuid(),
        ]);

        return compact('actor', 'ca', 'cm', 'location', 'product', 'variant', 'inventory');
    }
}
