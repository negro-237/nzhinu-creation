<?php

namespace App\Support;

use App\Models\Market;
use App\Services\SearchIndex;
use App\Services\SocialLinks;

class StructuredData
{
    public static function forPublic(string $component, array $props, Market $market, string $canonical, bool $demo): array
    {
        $index = app(SearchIndex::class);
        $home = $index->url();
        $store = [
            '@type' => 'ClothingStore', '@id' => $home.'#boutique', 'name' => __('shop.brand'), 'url' => $home,
            'logo' => $index->url('/images/logo-transparent.png'), 'image' => $index->url('/images/logo-transparent.png'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => config('storefront.boutique.street'), 'addressLocality' => config('storefront.boutique.city'), 'addressCountry' => config('storefront.boutique.country_code')],
        ];
        $socials = array_column(app(SocialLinks::class)->publicLinks(), 'url');
        if ($email = filter_var(config('communications.contact_email'), FILTER_VALIDATE_EMAIL)) {
            $store['email'] = $email;
        }
        if ($socials) {
            $store['sameAs'] = $socials;
        }
        $graph = [$store, ['@type' => 'WebSite', '@id' => $home.'#website', 'url' => $home, 'name' => __('shop.brand'), 'inLanguage' => 'fr', 'publisher' => ['@id' => $home.'#boutique']]];
        $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => __('shop.home'), 'item' => $home]];
        if (in_array($component, ['Catalog', 'Product'])) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => __('shop.catalog'), 'item' => $index->url('/boutique')];
        }
        if ($component === 'Product') {
            $product = $props['product'];
            $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $product['category']['name'], 'item' => $index->url('/boutique?category='.rawurlencode($product['category']['slug']))];
            $crumbs[] = ['@type' => 'ListItem', 'position' => 4, 'name' => $product['name'], 'item' => $canonical];
            if (! $demo) {
                $prices = array_column($product['variants'], 'price_minor');
                $digits = $market->currency->fractionDigits();
                $price = fn ($minor) => number_format($minor / (10 ** $digits), $digits, '.', '');
                $offers = ['@type' => count($prices) > 1 ? 'AggregateOffer' : 'Offer', 'url' => $canonical,
                    'priceCurrency' => $market->currency->value, 'availability' => 'https://schema.org/'.($product['in_stock'] ? 'InStock' : 'OutOfStock'), 'seller' => ['@id' => $home.'#boutique']];
                $offers += count($prices) > 1 ? ['lowPrice' => $price(min($prices)), 'highPrice' => $price(max($prices)), 'offerCount' => count($prices)] : ['price' => $price($prices[0])];
                $node = ['@type' => 'Product', '@id' => $canonical.'#product', 'url' => $canonical, 'name' => $product['name'],
                    'description' => StorefrontSeo::plain($product['description'] ?: $product['name']), 'category' => $product['category']['name'], 'offers' => $offers];
                if ($product['images']) {
                    $node['image'] = array_column($product['images'], 'url');
                }
                if (count($product['variants']) === 1) {
                    $node['sku'] = $product['variants'][0]['sku'];
                }
                $graph[] = $node;
            }
        } elseif ($component === 'Catalog') {
            $category = collect($props['categories'])->firstWhere('slug', $props['filters']['category'] ?? null);
            if ($category) {
                $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $category['name'], 'item' => $canonical];
            }
        } elseif ($component === 'ContentPage') {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $props['page']['title'], 'item' => $canonical];
        }
        if (count($crumbs) > 1) {
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
