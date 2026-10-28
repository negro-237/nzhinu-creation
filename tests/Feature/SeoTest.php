<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Market;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['seo.indexable' => true, 'storefront.markets' => ['CM'], 'app.url' => 'https://nzhinu.example.test']);
        $this->seed(DatabaseSeeder::class);
    }

    private function piece(): Product
    {
        return Product::factory()->published()->for(Category::where('slug', 'vetements')->firstOrFail())->withMarketPrices(['CM' => 25000])->create();
    }

    private function document(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    public function test_home_has_local_metadata_structured_data_and_crawlable_html_without_javascript(): void
    {
        $product = $this->piece();
        $response = $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'index,follow')
            ->where('seo.canonical', 'https://nzhinu.example.test/'));
        $xpath = $this->document($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@id="app"]//main//h1')->length);
        $this->assertSame(1, $xpath->query('//head/title')->length);
        $this->assertStringContainsString('Douala', $xpath->query('//head/title')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//*[@id="app"]//a[contains(@href,"/produits/'.$product->slug.'")]')->length);
        $script = $xpath->query('//script[@type="application/ld+json"]')->item(0);
        $data = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ClothingStore', $data['@graph'][0]['@type']);
        $this->assertSame('Douala', $data['@graph'][0]['address']['addressLocality']);
        $this->assertArrayNotHasKey('openingHours', $data['@graph'][0]);
        $this->assertStringContainsString("'nonce-".$script->getAttribute('nonce')."'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_category_and_pagination_canonicals_keep_identity_but_drop_tracking_and_search(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->piece();
        }
        $this->get('/boutique?category=vetements&page=2&utm_source=test')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', 'https://nzhinu.example.test/boutique?category=vetements&page=2')
            ->where('seo.robots', 'index,follow')->where('seo.head_title', 'Vêtements — Page 2'));
        $this->get('/boutique?category=vetements&q=robe')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seo.robots', 'noindex,follow')->where('seo.canonical', 'https://nzhinu.example.test/boutique?category=vetements'));
        $this->get('/boutique?category=inconnue')->assertOk()->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,follow'));
        $this->get('/boutique?page=999')->assertNotFound();
    }

    public function test_product_json_has_real_unit_price_currency_and_stock_without_invented_reviews_or_shipping(): void
    {
        $product = $this->piece();
        $response = $this->get('/produits/'.$product->slug)->assertOk();
        $xpath = $this->document($response->getContent());
        $data = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        $node = collect($data['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame('25000', $node['offers']['price']);
        $this->assertSame('XAF', $node['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/OutOfStock', $node['offers']['availability']);
        $this->assertArrayNotHasKey('aggregateRating', $node);
        $this->assertArrayNotHasKey('shippingDetails', $node['offers']);
        $this->assertStringContainsString($product->name, $xpath->query('//*[@id="app"]//h1')->item(0)->textContent);
    }

    public function test_demo_products_are_not_indexed_or_exported_as_commercial_structured_data(): void
    {
        $product = $this->piece();
        $product->variants->first()->update(['sku' => 'DEMO-NZ-99-STD']);
        $response = $this->get('/produits/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,follow'));
        $xpath = $this->document($response->getContent());
        $data = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse(collect($data['@graph'])->contains('@type', 'Product'));
        $this->assertStringNotContainsString('/produits/'.$product->slug, $this->get('/sitemap.xml')->streamedContent());
    }

    public function test_sitemap_contains_only_public_primary_market_urls_and_no_order_or_filter_urls(): void
    {
        $real = $this->piece();
        $draft = $this->piece();
        $draft->update(['status' => 'draft']);
        $archived = $this->piece();
        $archived->delete();
        $hidden = $this->piece();
        $hidden->marketProducts()->update(['is_available' => false]);
        CmsPage::create(['title' => 'Notre histoire', 'slug' => 'notre-histoire', 'body' => 'Texte', 'meta_description' => 'Notre histoire', 'is_published' => true]);
        CmsPage::create(['title' => 'Brouillon', 'slug' => 'brouillon-secret', 'body' => 'Texte', 'meta_description' => 'Texte', 'is_published' => false]);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->streamedContent();
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($xml));
        $this->assertStringContainsString('https://nzhinu.example.test/produits/'.$real->slug, $xml);
        $this->assertStringContainsString('/boutique?category=vetements', $xml);
        $this->assertStringContainsString('/pages/notre-histoire', $xml);
        foreach ([$draft->slug, $archived->slug, $hidden->slug, 'brouillon-secret', '/panier', '/commander', '/commandes/', '/admin', 'q=', 'sort='] as $excluded) {
            $this->assertStringNotContainsString($excluded, $xml);
        }
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://nzhinu.example.test/sitemap.xml', false);
    }

    public function test_staging_is_not_indexable_and_private_pages_have_no_commercial_graph(): void
    {
        config(['seo.indexable' => false]);
        $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,follow'));
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:', false);
        $this->assertStringNotContainsString('<loc>', $this->get('/sitemap.xml')->streamedContent());
        $this->get('/panier')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('application/ld+json', false);
    }

    public function test_structured_content_is_json_escaped_and_other_market_does_not_get_indexed(): void
    {
        $product = $this->piece();
        $product->update(['name' => 'Nom </script><script>alert(1)</script>']);
        $response = $this->get('/produits/'.$product->slug)->assertOk()->assertDontSee('</script><script>alert(1)</script>', false);
        $xpath = $this->document($response->getContent());
        $this->assertCount(1, $xpath->query('//script[@type="application/ld+json"]'));
        Market::where('code', 'CA')->update(['is_active' => true]);
        config(['storefront.markets' => ['CM', 'CA']]);
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('shop.market.code', 'CM')->where('seo.robots', 'index,follow'));
        $this->withSession(['shop.market' => 'CA'])->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,follow'));
    }
}
