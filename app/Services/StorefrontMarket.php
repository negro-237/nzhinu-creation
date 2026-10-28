<?php

namespace App\Services;

use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StorefrontMarket
{
    public function markets(Request $request): Collection
    {
        if (! $request->attributes->has('shop.markets')) {
            $request->attributes->set('shop.markets', Market::where('is_active', true)->whereIn('code', config('storefront.markets'))->orderBy('code')->get());
        }

        return $request->attributes->get('shop.markets');
    }

    public function current(Request $request): Market
    {
        if ($request->attributes->get('shop.receipt_market') instanceof Market) {
            return $request->attributes->get('shop.receipt_market');
        }
        $markets = $this->markets($request);
        $market = $markets->firstWhere('code', $request->session()->get('shop.market')) ?? $markets->first();
        abort_unless($market, 503, 'La boutique est temporairement indisponible.');

        return $market;
    }

    public function select(Request $request, string $code, bool $clearCart): void
    {
        $market = $this->markets($request)->firstWhere('code', $code);
        abort_unless($market, 404);
        $cart = $request->session()->get('shop.cart', []);
        if (! empty($cart['items']) && ($cart['market_id'] ?? null) !== $market->id) {
            if (! $clearCart) {
                throw ValidationException::withMessages(['code' => __('shop.switch_warning')]);
            }
            $request->session()->forget('shop.cart');
        }
        $request->session()->put('shop.market', $market->code);
    }
}
