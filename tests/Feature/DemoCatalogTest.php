<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\DemoProductImagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        User::factory()->create()->assignRole(StaffRole::Manager);
    }

    public function test_demo_seed_is_repeatable_with_both_currencies_and_no_fake_inventory_or_accounts(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $this->seed(DemoCatalogSeeder::class);
        $this->assertDatabaseCount('products', 12);
        $this->assertDatabaseCount('product_variants', 22);
        $this->assertDatabaseCount('market_prices', 44);
        $this->assertDatabaseCount('market_products', 24);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('inventories', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('media', 24);
        foreach (Product::all() as $item) {
            $this->assertCount(2, $item->getMedia('images'));
            foreach ($item->getMedia('images') as $image) {
                Storage::disk('public')->assertExists($image->getPathRelativeToRoot());
                $this->assertStringContainsString('illustration de démonstration', $image->getCustomProperty('alt'));
            }
        }
        $product = Product::where('slug', 'demo-robe-wax')->firstOrFail();
        $this->assertSame(8990, $product->variants->first()->prices()->where('currency', 'CAD')->value('amount_minor'));
        $this->assertSame(35000, $product->variants->first()->prices()->where('currency', 'XAF')->value('amount_minor'));
        $this->get('/boutique')->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 12));
        $this->get('/produits/demo-robe-wax')->assertOk()->assertInertia(fn (Assert $page) => $page->has('product.images', 2)->has('product.variants', 3)->where('product.in_stock', false)->where('product.variants.0.price_minor', 8990));
        $this->post('/marche', ['code' => 'CM']);
        $this->get('/produits/demo-robe-wax')->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.variants.0.price_minor', 35000));
    }

    public function test_seed_preserves_edits_prices_renamed_slugs_and_archival(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $product = Product::where('slug', 'demo-robe-wax')->firstOrFail();
        $product->update(['name' => 'Modifié par le personnel', 'slug' => 'ma-robe', 'status' => 'draft']);
        $product->variants->first()->prices()->where('currency', 'CAD')->update(['amount_minor' => 1234]);
        $archived = Product::where('slug', 'demo-sac-tissu')->firstOrFail();
        $archived->delete();
        $this->seed(DemoCatalogSeeder::class);
        $this->assertDatabaseCount('products', 12);
        $this->assertSoftDeleted($archived);
        $this->assertSame('Modifié par le personnel', $product->fresh()->name);
        $this->assertSame(1234, $product->variants->first()->prices()->where('currency', 'CAD')->value('amount_minor'));
        $this->assertFalse(Product::where('slug', 'demo-robe-wax')->exists());
    }

    public function test_demo_seeder_refuses_production_without_writing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->seed(DemoCatalogSeeder::class);
            $this->fail('Production seed must be refused');
        } catch (\LogicException) {
            $this->assertDatabaseCount('products', 0);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_factory_supports_explicit_variants_and_market_price_relationships(): void
    {
        $product = Product::factory()->published()->has(ProductVariant::factory()->count(2), 'variants')->withMarketPrices()->create();
        $this->assertCount(2, $product->variants);
        $this->assertDatabaseCount('market_prices', 4);
        foreach (MarketPrice::all() as $price) {
            $this->assertSame($price->market->currency, $price->currency);
        }
        MarketPrice::factory()->create();
        $this->assertDatabaseCount('markets', 2);
        $this->assertDatabaseCount('inventories', 0);
    }

    public function test_image_backfill_preserves_staff_images_and_skips_archived_products(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $product = Product::where('slug', 'demo-robe-wax')->firstOrFail();
        $product->clearMediaCollection('images');
        $photo = $product->addMedia(public_path('images/demo/01-front.webp'))->preservingOriginal()
            ->withCustomProperties(['alt' => 'Photo ajoutée par le personnel'])->toMediaCollection('images');
        $archived = Product::where('slug', 'demo-sac-tissu')->firstOrFail();
        $archived->clearMediaCollection('images');
        $archived->delete();
        $this->seed(DemoProductImagesSeeder::class);
        $this->assertSame([$photo->id], $product->fresh()->getMedia('images')->modelKeys());
        $this->assertSame('Photo ajoutée par le personnel', $photo->fresh()->getCustomProperty('alt'));
        $this->assertCount(0, $archived->fresh()->getMedia('images'));
        $this->assertDatabaseCount('media', 21);
    }

    public function test_image_backfill_requires_catalog_permissions_and_refuses_production(): void
    {
        User::query()->firstOrFail()->syncRoles([]);
        try {
            $this->seed(DemoProductImagesSeeder::class);
            $this->fail('Unauthorized image seed must be refused');
        } catch (\LogicException) {
            $this->assertDatabaseCount('media', 0);
        }
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->seed(DemoProductImagesSeeder::class);
            $this->fail('Production image seed must be refused');
        } catch (\LogicException) {
            $this->assertDatabaseCount('media', 0);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
