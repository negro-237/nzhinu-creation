<?php

namespace Tests\Feature;

use App\Models\CulturalCollection;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Models\StockLocation;
use App\Services\CatalogService;
use App\Services\InventoryService;
use App\Services\ProductMediaService;
use App\Services\ShoppingCart;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EnforcedCsrf;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use InventoryFixture, RefreshDatabase;

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->f = $this->fixture();
    }

    public function test_home_catalog_and_product_are_public_and_show_only_current_market_prices(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome')
            ->where('shop.market.code', 'CA')->where('featured.0.price_minor', 5000));
        $this->get('/boutique')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Catalog')
            ->has('products.items', 1)->where('products.items.0.in_stock', true)
            ->where('products.items.0.price_minor', 5000)->where('shop.market.currency', 'CAD'));
        $this->post('/marche', ['code' => 'CM'])->assertRedirect('/boutique');
        $this->get('/produits/'.$this->f['product']->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Product')
            ->where('shop.market.currency', 'XAF')->where('product.variants.0.price_minor', 25000)->has('product.variants.0.stocks', 0));
        $this->assertGuest();
    }

    public function test_public_contacts_are_configurable_validated_and_scoped_to_the_market(): void
    {
        config(['communications.contact_email' => 'infos@nhinu-creation.com', 'communications.whatsapp_ca' => '',
            'communications.whatsapp_cm' => '23755278597']);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('shop.contact_email', 'infos@nhinu-creation.com')->where('shop.whatsapp_url', null)
            ->where('shop.categories.0.slug', $this->f['product']->category->slug));
        $this->post('/marche', ['code' => 'CM'])->assertRedirect();
        $expected = 'https://wa.me/23755278597?text='.rawurlencode(__('shop.whatsapp_message'));
        $this->get('/')->assertOk()->assertSee('mailto:infos@nhinu-creation.com', false)
            ->assertInertia(fn (Assert $page) => $page->where('shop.whatsapp_url', $expected));
        config(['communications.contact_email' => 'javascript:alert(1)', 'communications.whatsapp_cm' => 'javascript:alert(1)']);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shop.contact_email', null)->where('shop.whatsapp_url', null));
    }

    public function test_drafts_unlisted_products_inactive_categories_and_archived_products_are_not_public(): void
    {
        Product::factory()->create(['name' => 'Secret draft']);
        $this->get('/boutique')->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $product = $this->f['product'];
        $product->marketProducts()->where('market_id', $this->f['ca']->id)->update(['is_available' => false]);
        $this->get('/produits/'.$product->slug)->assertNotFound();
        $product->marketProducts()->update(['is_available' => true]);
        $product->category->update(['is_active' => false]);
        $this->get('/produits/'.$product->slug)->assertNotFound();
        $product->category->update(['is_active' => true]);
        $product->delete();
        $this->get('/produits/'.$product->slug)->assertNotFound();
        $this->get('/boutique')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    public function test_search_matches_product_and_sku_and_treats_wildcards_literally(): void
    {
        $this->get('/boutique?q=INV-001')->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $this->get('/boutique?q=absent')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $this->f['product']->update(['name' => 'Étole 100% laine']);
        $this->get('/boutique?'.http_build_query(['q' => '%']))->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $this->get('/boutique?'.http_build_query(['q' => '_']))->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    public function test_category_collection_price_stock_filters_and_sorting(): void
    {
        $collection = CulturalCollection::factory()->create();
        $this->f['product']->collections()->attach($collection);
        $expensive = $this->product('Pièce supérieure', '80');
        $this->get('/boutique?sort=price_desc')->assertInertia(fn (Assert $page) => $page->where('products.items.0.id', $expensive->id));
        $this->get('/boutique?sort=price_asc')->assertInertia(fn (Assert $page) => $page->where('products.items.0.id', $this->f['product']->id));
        $this->get('/boutique?in_stock=1')->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $this->get('/boutique?min_price=49,99&max_price=50,00')->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $this->get('/boutique?collection='.$collection->slug)->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
        $this->get('/boutique?category=absente')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $collection->update(['is_active' => false]);
        $this->get('/boutique?collection='.$collection->slug)->assertInertia(fn (Assert $page) => $page->where('products.total', 0)->has('collections', 0));
    }

    public function test_price_filters_respect_xaf_precision_and_reject_bad_input(): void
    {
        $this->get('/boutique?sort=sql')->assertSessionHasErrors('sort');
        $this->get('/boutique?page=-1')->assertSessionHasErrors('page');
        $this->get('/boutique?q[]=injection')->assertSessionHasErrors('q');
        $this->get('/boutique?min_price=80&max_price=10')->assertSessionHasErrors('max_price');
        $this->post('/marche', ['code' => 'CM']);
        $this->get('/boutique?min_price=25000.50')->assertSessionHasErrors('min_price');
        $this->get('/boutique?min_price=25000&max_price=25000')->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
    }

    public function test_catalog_is_paginated_and_keeps_validated_filters(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product('Pièce pagination '.$i);
        }
        $this->get('/boutique?sort=name')->assertInertia(fn (Assert $page) => $page->where('products.total', 13)
            ->has('products.items', 12)->where('products.last_page', 2)
            ->where('products.next_url', fn ($url) => str_contains($url, 'sort=name') && str_contains($url, 'page=2')));
        $this->get('/boutique?sort=name&page=2')->assertInertia(fn (Assert $page) => $page->has('products.items', 1)->where('products.current_page', 2));
    }

    public function test_product_gallery_and_stock_payload_do_not_expose_private_inventory_details(): void
    {
        Storage::fake('public');
        app(ProductMediaService::class)->add($this->f['actor'], $this->f['product'], UploadedFile::fake()->image('face.jpg'), 'Vue de face');
        $this->get('/produits/'.$this->f['product']->slug)->assertInertia(fn (Assert $page) => $page
            ->has('product.images', 1)->where('product.images.0.alt', 'Vue de face')
            ->where('product.variants.0.stocks.0.available', 10)
            ->missing('product.variants.0.stocks.0.address')->missing('product.variants.0.stocks.0.quantity_on_hand')
            ->missing('product.variants.0.stocks.0.quantity_reserved')->missing('product.variants.0.stocks.0.low_stock_threshold'));
    }

    public function test_guest_cart_uses_server_prices_and_does_not_reserve_inventory(): void
    {
        $this->post('/panier/lignes', [...$this->line(2), 'price_minor' => 1, 'currency' => 'XAF'])->assertRedirect('/panier');
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->component('Cart')->where('cart.subtotal_minor', 10000)
            ->where('cart.items.0.quantity', 2)->where('cart.items.0.price_minor', 5000)
            ->where('shop.cart_count', 2)->where('shop.market.currency', 'CAD'));
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertSame(10, $this->f['inventory']->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertGuest();
    }

    public function test_absolute_quantity_retries_update_without_duplicate_lines(): void
    {
        $this->post('/panier/lignes', $this->line(2));
        $this->post('/panier/lignes', $this->line(2));
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->has('cart.items', 1)->where('cart.items.0.quantity', 2));
        $this->post('/panier/lignes', $this->line(3));
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.subtotal_minor', 15000)->where('cart.items.0.quantity', 3));
        $this->delete('/panier/lignes/'.$this->f['inventory']->id)->assertRedirect('/panier');
        $this->delete('/panier/lignes/'.$this->f['inventory']->id)->assertRedirect('/panier');
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->has('cart.items', 0)->where('cart.subtotal_minor', 0));
    }

    public function test_invalid_quantities_missing_stock_and_cross_market_stock_are_rejected(): void
    {
        foreach ([0, -1, 11, 100, '1.5'] as $quantity) {
            $this->post('/panier/lignes', $this->line($quantity))->assertSessionHasErrors('quantity');
        }
        $this->post('/panier/lignes', ['inventory_id' => 999999, 'quantity' => 1])->assertSessionHasErrors('inventory_id');
        $this->post('/marche', ['code' => 'CM']);
        $this->post('/panier/lignes', $this->line(1))->assertSessionHasErrors('inventory_id');
        $this->assertSessionEmptyCart();
    }

    public function test_inactive_locations_and_unpublished_variants_cannot_be_added(): void
    {
        $this->f['location']->update(['is_active' => false]);
        $this->post('/panier/lignes', $this->line(1))->assertSessionHasErrors('inventory_id');
        $this->f['location']->update(['is_active' => true, 'is_placeholder' => true]);
        $this->post('/panier/lignes', $this->line(1))->assertSessionHasErrors('inventory_id');
        $this->f['location']->update(['is_placeholder' => false]);
        $this->f['variant']->delete();
        $this->post('/panier/lignes', $this->line(1))->assertSessionHasErrors('inventory_id');
        $this->assertSessionEmptyCart();
    }

    public function test_changing_market_requires_explicit_cart_clearing_and_never_mixes_currencies(): void
    {
        $this->post('/panier/lignes', $this->line(2));
        $this->post('/marche', ['code' => 'CM'])->assertSessionHasErrors('code');
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('shop.market.code', 'CA')->where('cart.subtotal_minor', 10000));
        $this->post('/marche', ['code' => 'CA'])->assertSessionHasNoErrors();
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.items.0.quantity', 2));
        $this->post('/marche', ['code' => 'CM', 'clear_cart' => true])->assertRedirect('/boutique');
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('shop.market.currency', 'XAF')->has('cart.items', 0));
    }

    public function test_xaf_cart_uses_whole_francs_and_only_local_stock(): void
    {
        $location = StockLocation::where('market_id', $this->f['cm']->id)->firstOrFail();
        $location->update(['address' => 'Adresse de test CM', 'is_placeholder' => false, 'is_active' => true]);
        $stock = app(InventoryService::class)->create($this->f['actor'], [
            'stock_location_id' => $location->id, 'product_variant_id' => $this->f['variant']->id,
            'quantity_on_hand' => 3, 'low_stock_threshold' => 0, 'reason' => 'Stock CM de test', 'request_key' => (string) Str::uuid(),
        ]);
        $this->post('/marche', ['code' => 'CM']);
        $this->post('/panier/lignes', ['inventory_id' => $stock->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.subtotal_minor', 50000)
            ->where('shop.market.currency', 'XAF')->where('cart.items.0.price_minor', 25000));
    }

    public function test_multiple_stock_locations_stay_separate_and_cannot_be_aggregated_into_one_line(): void
    {
        $location = StockLocation::factory()->for($this->f['ca'])->create(['address' => 'Adresse test', 'is_active' => true, 'is_placeholder' => false]);
        $stock = app(InventoryService::class)->create($this->f['actor'], [
            'stock_location_id' => $location->id, 'product_variant_id' => $this->f['variant']->id,
            'quantity_on_hand' => 3, 'low_stock_threshold' => 0, 'reason' => 'Second stock', 'request_key' => (string) Str::uuid(),
        ]);
        $this->post('/panier/lignes', $this->line(12))->assertSessionHasErrors('quantity');
        $this->post('/panier/lignes', $this->line(10));
        $this->post('/panier/lignes', ['inventory_id' => $stock->id, 'quantity' => 3]);
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->has('cart.items', 2)->where('cart.subtotal_minor', 65000));
    }

    public function test_cart_revalidates_prices_stock_and_archived_items_without_silent_quantity_changes(): void
    {
        $this->post('/panier/lignes', $this->line(4));
        MarketPrice::where('market_id', $this->f['ca']->id)->update(['amount_minor' => 6000]);
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.subtotal_minor', 24000));
        $this->f['inventory']->update(['quantity_reserved' => 8]);
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.has_issues', true)
            ->where('cart.subtotal_minor', null)->where('cart.items.0.quantity', 4)->where('cart.items.0.available', 2));
        $this->post('/panier/lignes', $this->line(2));
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.subtotal_minor', 12000)->where('cart.has_issues', false));
        $this->f['product']->delete();
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.items.0.slug', null)->where('cart.items.0.price_minor', null)->where('cart.subtotal_minor', null));
    }

    public function test_an_inactive_market_preserves_old_cart_until_explicit_clear(): void
    {
        $this->post('/panier/lignes', $this->line(2));
        $this->f['ca']->update(['is_active' => false]);
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('shop.market.code', 'CM')
            ->where('cart.market_mismatch', true)->where('cart.stored_count', 2)->where('cart.subtotal_minor', null));
        $this->post('/marche', ['code' => 'CA'])->assertSessionHasErrors('code');
        $this->delete('/panier')->assertRedirect('/panier');
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->where('cart.market_mismatch', false)->where('cart.stored_count', 0));
    }

    public function test_no_active_market_returns_service_unavailable_and_unknown_market_is_rejected(): void
    {
        $this->post('/marche', ['code' => 'FR'])->assertSessionHasErrors('code');
        $this->post('/marche', ['code' => ['CA', 'CM']])->assertSessionHasErrors('code');
        $this->f['ca']->update(['is_active' => false]);
        $this->f['cm']->update(['is_active' => false]);
        $this->get('/boutique')->assertStatus(503);
    }

    public function test_session_carts_are_isolated_and_removing_foreign_line_is_a_noop(): void
    {
        $one = new Store('one', new ArraySessionHandler(120));
        $two = new Store('two', new ArraySessionHandler(120));
        $one->start();
        $two->start();
        $service = app(ShoppingCart::class);
        $service->put($one, $this->f['ca'], $this->f['inventory']->id, 2);
        $service->remove($two, $this->f['inventory']->id);
        $this->assertSame(2, $service->count($one));
        $this->assertSame(0, $service->count($two));
    }

    public function test_mutations_require_csrf_and_public_routes_share_session_locking(): void
    {
        $this->app->bind(ValidateCsrfToken::class, EnforcedCsrf::class);
        $this->withSession(['_token' => 'secure-test-token'])->post('/panier/lignes', $this->line(1))->assertStatus(419);
        $this->post('/panier/lignes', [...$this->line(1), '_token' => 'secure-test-token'])->assertRedirect('/panier');
        foreach (['home', 'shop.catalog', 'shop.product', 'shop.cart', 'shop.market', 'shop.cart.put', 'shop.cart.remove', 'shop.cart.clear'] as $name) {
            $this->assertSame(10, app('router')->getRoutes()->getByName($name)->locksFor());
        }
    }

    public function test_initial_html_includes_escaped_seo_metadata_without_javascript(): void
    {
        $this->f['product']->update(['name' => 'Pièce <script>alert(1)</script>', 'description' => 'Description & identité']);
        $response = $this->get('/produits/'.$this->f['product']->slug)->assertOk();
        $response->assertSee('<title inertia="">Pièce &lt;script&gt;alert(1)&lt;/script&gt; | Nzhinu Création</title>', false)
            ->assertSee('name="description" content="Description &amp; identité"', false)
            ->assertSee('rel="canonical"', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/panier')->assertSee('name="robots" content="noindex,nofollow"', false);
        $this->get('/boutique?q=test')->assertSee('name="robots" content="noindex,follow"', false);
    }

    private function line(mixed $quantity): array
    {
        return ['inventory_id' => $this->f['inventory']->id, 'quantity' => $quantity];
    }

    private function assertSessionEmptyCart(): void
    {
        $this->get('/panier')->assertInertia(fn (Assert $page) => $page->has('cart.items', 0)->where('cart.subtotal_minor', 0));
    }

    private function product(string $name, string $price = '50'): Product
    {
        return app(CatalogService::class)->save($this->f['actor'], [
            'name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->f['product']->category_id, 'status' => 'published',
            'markets' => [['market_id' => $this->f['ca']->id, 'is_available' => true]],
            'variants' => [['sku' => 'SHOP-'.Str::upper(Str::random(12)), 'label' => 'Standard', 'is_active' => true,
                'prices' => [['market_id' => $this->f['ca']->id, 'amount' => $price]]]],
        ]);
    }
}
