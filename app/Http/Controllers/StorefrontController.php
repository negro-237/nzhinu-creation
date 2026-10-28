<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Http\Requests\SelectMarketRequest;
use App\Services\ContentService;
use App\Services\StorefrontCatalog;
use App\Services\StorefrontMarket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function home(Request $request, StorefrontMarket $markets, StorefrontCatalog $catalog)
    {
        $content = __('storefront');
        $content['market_notes'] = array_intersect_key($content['market_notes'], array_flip(config('storefront.markets')));

        return Inertia::render('Welcome', [
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

        return Inertia::render('Catalog', [
            'products' => $catalog->listing($market, $filters), 'filters' => $filters, ...$catalog->options($market),
        ]);
    }

    public function product(Request $request, string $slug, StorefrontMarket $markets, StorefrontCatalog $catalog)
    {
        return Inertia::render('Product', ['product' => $catalog->detail($markets->current($request), $slug)]);
    }

    public function selectMarket(SelectMarketRequest $request, StorefrontMarket $markets)
    {
        $markets->select($request, $request->validated('code'), (bool) $request->validated('clear_cart', false));

        return to_route('shop.catalog')->with('success', __('shop.market_updated'));
    }

    public function page(Request $request, string $slug, StorefrontMarket $markets, ContentService $content)
    {
        $page = $content->page($markets->current($request), $slug);

        return Inertia::render('ContentPage', ['page' => $page->only(['title', 'body', 'meta_description'])]);
    }
}
