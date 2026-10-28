<?php

namespace App\Http\Middleware;

use App\Services\ContentService;
use App\Services\ShoppingCart;
use App\Services\SocialLinks;
use App\Services\StorefrontCatalog;
use App\Services\StorefrontMarket;
use App\Services\WhatsAppContact;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request), 'locale' => app()->getLocale(),
            'canonical' => $request->url().($request->routeIs('shop.catalog') && $request->integer('page') > 1 ? '?page='.$request->integer('page') : ''),
            'shop' => function () use ($request) {
                $markets = app(StorefrontMarket::class);

                return [
                    'boutique' => config('storefront.boutique'),
                    'social_links' => app(SocialLinks::class)->publicLinks(),
                    'contact_email' => filter_var(config('communications.contact_email'), FILTER_VALIDATE_EMAIL) ?: null,
                    'categories' => app(StorefrontCatalog::class)->homeCategories($markets->current($request)),
                    'pages' => app(ContentService::class)->navigation($markets->current($request)),
                    'whatsapp_url' => app(WhatsAppContact::class)->link($markets->current($request)),
                    'market' => $markets->current($request)->only(['code', 'name', 'currency']),
                    'markets' => $markets->markets($request)->map->only(['code', 'name', 'currency']),
                    'cart_count' => app(ShoppingCart::class)->count($request->session()),
                    'text' => __('shop'),
                ];
            },
            'flash' => fn () => ['success' => $request->session()->get('success')],
        ];
    }
}
