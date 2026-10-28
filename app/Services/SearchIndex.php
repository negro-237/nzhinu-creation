<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Market;
use App\Models\Product;

class SearchIndex
{
    public function market(): ?Market
    {
        foreach (config('storefront.markets') as $code) {
            $market = Market::where('code', $code)->where('is_active', true)->first();
            if ($market) {
                return $market;
            }
        }

        return null;
    }

    public function url(string $path = '/'): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /** Public canonical URLs only; never depend on a visitor's session or order data. */
    public function urls(): \Generator
    {
        $market = $this->market();
        if (! config('seo.indexable') || ! $market) {
            return;
        }
        yield $this->url();
        $products = Product::availableIn($market)->withoutDemonstration();
        if ((clone $products)->exists()) {
            yield $this->url('/boutique');
        }
        foreach (Category::where('is_active', true)->whereHas('products', fn ($query) => $query->availableIn($market)->withoutDemonstration())->orderBy('id')->cursor() as $category) {
            yield $this->url('/boutique?'.http_build_query(['category' => $category->slug], '', '&', PHP_QUERY_RFC3986));
        }
        foreach ($products->orderBy('products.id')->cursor() as $product) {
            yield $this->url('/produits/'.$product->slug);
        }
        foreach (CmsPage::where('is_published', true)->where(fn ($query) => $query->whereNull('market_id')->orWhere('market_id', $market->id))->orderBy('id')->cursor() as $page) {
            yield $this->url('/pages/'.$page->slug);
        }
    }
}
