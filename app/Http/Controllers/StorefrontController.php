<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Http\Requests\SelectMarketRequest;
use App\Services\ContentService;
use App\Services\StorefrontCatalog;
use App\Services\StorefrontMarket;
use App\Support\StorefrontSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function home(Request $request, StorefrontMarket $markets, StorefrontCatalog $catalog)
    {
        $content = __('storefront');
        $content['market_notes'] = array_intersect_key($content['market_notes'], array_flip(config('storefront.markets')));

        return $this->render($request, 'Welcome', [
            'markets' => $markets->markets($request)->map->only(['code', 'name', 'currency']),
            'content' => $content, 'featured' => $catalog->featured($markets->current($request)),
            'categories' => $catalog->homeCategories($markets->current($request)),
            'banners' => app(ContentService::class)->banners($markets->current($request)),
        ]);
    }

    public function catalog(CatalogFilterRequest $request, StorefrontMarket $markets, StorefrontCatalog $catalog)
    {
        $market = $markets->current($request);
        $filters = $request->validated();

        $products = $catalog->listing($market, $filters);
        abort_if($products['current_page'] > $products['last_page'], 404);

        return $this->render($request, 'Catalog', [
            'products' => $products, 'filters' => $filters, ...$catalog->options($market),
        ]);
    }

    public function product(Request $request, string $slug, StorefrontMarket $markets, StorefrontCatalog $catalog)
    {
        return $this->render($request, 'Product', ['product' => $catalog->detail($markets->current($request), $slug)]);
    }

    public function selectMarket(SelectMarketRequest $request, StorefrontMarket $markets)
    {
        $markets->select($request, $request->validated('code'), (bool) $request->validated('clear_cart', false));

        return to_route('shop.catalog')->with('success', __('shop.market_updated'));
    }

    public function page(Request $request, string $slug, StorefrontMarket $markets, ContentService $content)
    {
        $page = $content->page($markets->current($request), $slug);

        return $this->render($request, 'ContentPage', ['page' => $page->only(['title', 'body', 'meta_description'])]);
    }

    private function render(Request $request, string $component, array $props)
    {
        $props['seo'] = StorefrontSeo::forPublic($component, $props, app(StorefrontMarket::class)->current($request), $request);

        return Inertia::render($component, $props);
    }
}
