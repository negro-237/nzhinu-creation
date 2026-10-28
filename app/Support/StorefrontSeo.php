<?php

namespace App\Support;

use App\Models\Market;
use App\Models\Product;
use App\Services\SearchIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontSeo
{
    public static function forPublic(string $component, array $props, Market $market, Request $request): array
    {
        $index = app(SearchIndex::class);
        $filters = array_filter($props['filters'] ?? [], fn ($value) => $value !== null && $value !== '');
        $category = $component === 'Catalog' ? collect($props['categories'])->firstWhere('slug', $filters['category'] ?? null) : null;
        $complex = array_diff(array_keys($filters), ['category', 'page']) !== [];
        $page = $component === 'Catalog' ? $props['products']['current_page'] : 1;
        $query = [];
        if ($category) {
            $query['category'] = $category['slug'];
        }
        if (! $complex && $page > 1) {
            $query['page'] = $page;
        }
        $canonical = $index->url($request->getPathInfo()).($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
        $title = match ($component) {
            'Welcome' => __('seo.home_title'),
            'Catalog' => $category ? $category['name'] : __('seo.catalog_title'),
            'Product' => $props['product']['name'],
            'ContentPage' => $props['page']['title'],
        };
        if ($page > 1) {
            $title .= ' — '.__('seo.page_number', ['number' => $page]);
        }
        $description = match ($component) {
            'Welcome' => __('seo.home_description'),
            'Catalog' => ($category['description'] ?? null) ?: __('seo.catalog_description'),
            'Product' => $props['product']['description'] ?: __('seo.product_description', ['name' => $props['product']['name']]),
            'ContentPage' => $props['page']['meta_description'],
        };
        $demo = $component === 'Product' && ! Product::whereKey($props['product']['id'])->withoutDemonstration()->exists();
        $indexable = config('seo.indexable') && $index->market()?->id === $market->id && ! $demo;
        if ($component === 'Catalog') {
            $real = Product::availableIn($market)->withoutDemonstration();
            if ($category) {
                $real->whereHas('category', fn ($query) => $query->where('slug', $category['slug']));
            }
            $indexable = $indexable && ! $complex && (! isset($filters['category']) || $category) && $props['products']['items'] !== [] && $real->exists();
        }
        $image = $component === 'Product' && ! empty($props['product']['images']) ? $props['product']['images'][0] : ['url' => $index->url('/images/logo-transparent.png'), 'alt' => __('shop.brand')];

        return [
            'public' => true, 'head_title' => $title, 'title' => $title.' | '.__('shop.brand'), 'description' => self::plain($description),
            'canonical' => $canonical, 'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            'image' => $image['url'], 'image_alt' => $image['alt'],
            'category' => $category ? collect($category)->only(['name', 'slug', 'description'])->all() : null,
            'structured_json' => json_encode(StructuredData::forPublic($component, $props, $market, $canonical, $demo),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    public static function plain(string $value): string
    {
        return Str::limit(Str::squish(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 160, '');
    }

    /** Initial HTML metadata without requiring a separate Node SSR server. */
    public static function fromPage(array $page): array
    {
        $props = $page['props'];
        if (isset($props['seo'])) {
            return $props['seo'];
        }
        $text = $props['shop']['text'];
        $component = $page['component'];
        $title = match ($component) {
            'Welcome' => $props['content']['title'].' '.$props['content']['title_accent'],
            'Catalog' => $text['catalog'],
            'Product' => $props['product']['name'],
            'Checkout' => $props['content']['checkout_title'],
            'OrderConfirmation' => ($props['order']['status_code'] ?? 'pending') === 'pending' ? $props['content']['confirmation_title'] : $props['content']['tracking_title'],
            'ContentPage' => $props['page']['title'],
            default => $text['cart'],
        };

        return [
            'public' => false, 'structured_json' => null,
            'title' => $title.' | '.$text['brand'],
            'description' => match ($component) {
                'Welcome' => $props['content']['intro'],
                'Product' => $props['product']['meta_description'],
                'Catalog' => $text['catalog_intro'],
                'ContentPage' => $props['page']['meta_description'],
                default => $text['cart_intro'],
            },
            'canonical' => $props['canonical'],
            'robots' => in_array($component, ['Cart', 'Checkout', 'OrderConfirmation']) ? 'noindex,nofollow'
                : ($component === 'Catalog' && array_diff(array_keys($props['filters']), ['page']) ? 'noindex,follow' : 'index,follow'),
            'product' => $component === 'Product' ? $props['product'] : null,
        ];
    }
}
