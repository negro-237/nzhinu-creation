<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SimilarProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function piece(Category $category, array $prices = ['CM' => 22000, 'CA' => 4900]): Product
    {
        return Product::factory()->for($category)->published()->withMarketPrices($prices)->create();
    }

    public function test_similar_products_are_limited_to_four_other_products_of_the_same_category(): void
    {
        $category = Category::where('slug', 'vetements')->firstOrFail();
        $product = $this->piece($category);
        $ids = [];
        for ($index = 0; $index < 6; $index++) {
            $ids[] = $this->piece($category)->id;
        }
        $this->piece(Category::where('slug', 'bijoux')->firstOrFail());
        $expected = array_slice(array_reverse($ids), 0, 4);
        $this->withSession(['shop.market' => 'CM'])->get('/produits/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('product.similar_products', 4)->where('product.similar_products.0.id', $expected[0])
            ->where('product.similar_products.3.id', $expected[3])->where('product.similar_products.0.price_minor', 22000)
            ->where('product.similar_products.0.in_stock', false)->missing('product.similar_products.0.variants'));
        $this->withSession(['shop.market' => 'CA'])->get('/produits/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.similar_products.0.price_minor', 4900));
        $this->assertDatabaseCount('inventories', 0);
    }

    public function test_recommendations_exclude_hidden_archived_unpriced_and_other_market_products(): void
    {
        $category = Category::where('slug', 'vetements')->firstOrFail();
        $product = $this->piece($category);
        $valid = $this->piece($category);
        $this->piece($category)->update(['status' => 'draft']);
        $this->piece($category)->delete();
        $this->piece($category, ['CA' => 5000]);
        $hidden = $this->piece($category);
        $hidden->marketProducts()->update(['is_available' => false]);
        $unpriced = $this->piece($category);
        $unpriced->variants->first()->prices()->delete();
        $inactive = $this->piece($category);
        $inactive->variants()->update(['is_active' => false]);
        $wrongCurrency = $this->piece($category);
        $wrongCurrency->variants->first()->prices()->where('currency', 'XAF')->update(['currency' => 'CAD']);
        $this->withSession(['shop.market' => 'CM'])->get('/produits/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('product.similar_products', 1)->where('product.similar_products.0.id', $valid->id));
    }

    public function test_no_unrelated_fallback_is_offered_if_there_are_no_similar_products(): void
    {
        $product = $this->piece(Category::where('slug', 'vetements')->firstOrFail());
        $this->piece(Category::where('slug', 'accessoires')->firstOrFail());
        $this->withSession(['shop.market' => 'CM'])->get('/produits/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->has('product.similar_products', 0));
        $product->category->update(['is_active' => false]);
        $this->get('/produits/'.$product->slug)->assertNotFound();
    }
}
