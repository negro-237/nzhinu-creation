<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Market;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\DeploymentReadiness;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CameroonLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('public');
        config(['storefront.markets' => ['CM']]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_only_cameroon_is_public_even_with_a_canadian_session_and_active_canadian_records(): void
    {
        Market::where('code', 'CA')->update(['is_active' => true]);
        $this->withSession(['shop.market' => 'CA'])->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shop.market.code', 'CM')->where('shop.market.currency', 'XAF')->has('shop.markets', 1)->has('markets', 1)
            ->where('shop.boutique.address', 'Douala, Bonaberi Rail, Marché ChaCha 223')->missing('content.market_notes.CA'));
        $this->post('/marche', ['code' => 'CA'])->assertSessionHasErrors('code');
        $this->assertDatabaseHas('markets', ['code' => 'CA', 'is_active' => true]);
        $checks = collect(app(DeploymentReadiness::class)->checks())->keyBy('code');
        $this->assertArrayNotHasKey('catalog_CA', $checks->all());
        $this->assertArrayHasKey('catalog_CM', $checks->all());
    }

    public function test_boutique_seeding_is_repeatable_without_fake_inventory_and_preserves_staff_edits(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('stock_locations', 3);
        $this->assertDatabaseCount('pickup_points', 1);
        $this->assertDatabaseCount('inventories', 0);
        $location = StockLocation::where('code', 'CM-DOUALA-CHACHA223')->firstOrFail();
        $this->assertTrue($location->is_active);
        $this->assertFalse($location->is_placeholder);
        $this->assertSame('Douala, Bonaberi Rail, Marché ChaCha 223', $location->address);
        $point = PickupPoint::firstOrFail();
        $this->assertSame($location->id, $point->stock_location_id);
        $point->update(['name' => 'Point renommé', 'address' => 'Adresse modifiée par le personnel', 'is_active' => false]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('pickup_points', 1);
        $this->assertSame('Point renommé', $point->fresh()->name);
        $point->delete();
        $this->seed(DatabaseSeeder::class);
        $this->assertSoftDeleted($point);
        $location->delete();
        $this->seed(DatabaseSeeder::class);
        $this->assertSoftDeleted($location);
        $this->assertSame('Adresse modifiée par le personnel', $point->fresh()->address);
        $this->assertFalse($point->fresh()->is_active);
        $this->assertDatabaseCount('stock_locations', 3);
        $this->assertDatabaseCount('pickup_points', 1);
    }

    public function test_demo_catalog_also_seeds_on_a_fresh_cameroon_only_installation(): void
    {
        User::factory()->create()->assignRole(StaffRole::Manager);
        $this->seed(DemoCatalogSeeder::class);
        $this->seed(DemoCatalogSeeder::class);
        $this->assertDatabaseCount('products', 12);
        $this->assertDatabaseCount('market_prices', 22);
        $this->assertDatabaseCount('market_products', 12);
        $this->assertDatabaseCount('media', 24);
        $this->assertDatabaseCount('inventories', 0);
        $this->assertDatabaseMissing('market_prices', ['currency' => 'CAD']);
        $this->get('/produits/demo-robe-wax')->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.variants.0.price_minor', 35000));
    }

    public function test_guest_can_reserve_real_cameroon_stock_for_pickup_at_the_boutique(): void
    {
        $actor = User::factory()->create()->assignRole(StaffRole::Manager);
        $product = Product::factory()->published()->withMarketPrices(['CM' => 25000])->create();
        $location = StockLocation::where('code', 'CM-DOUALA-CHACHA223')->firstOrFail();
        $stock = app(InventoryService::class)->create($actor, ['stock_location_id' => $location->id,
            'product_variant_id' => $product->variants->first()->id, 'quantity_on_hand' => 3, 'low_stock_threshold' => 1,
            'reason' => 'Stock de test', 'request_key' => (string) Str::uuid()]);
        $this->post('/panier/lignes', ['inventory_id' => $stock->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->get('/commander')->assertOk()->assertInertia(fn (Assert $page) => $page->has('checkout.points', 1)
            ->where('checkout.points.0.address', 'Douala, Bonaberi Rail, Marché ChaCha 223'));
        $key = array_key_first(session('checkout.drafts'));
        $this->post('/commander', ['request_key' => $key, 'customer_name' => 'Client Cameroun', 'phone' => '+237699000000',
            'method' => 'pickup', 'pickup_point_id' => PickupPoint::firstOrFail()->id])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame('XAF', $order->currency->value);
        $this->assertSame(25000, $order->subtotal_minor);
        $this->assertSame(1, $stock->fresh()->quantity_reserved);
        $this->assertSame(3, $stock->fresh()->quantity_on_hand);
        $this->assertSame('Douala, Bonaberi Rail, Marché ChaCha 223', $order->fulfillment_snapshot['address']);
    }
}
