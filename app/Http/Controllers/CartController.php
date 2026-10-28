<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartLineRequest;
use App\Services\ShoppingCart;
use App\Services\StorefrontMarket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function show(Request $request, ShoppingCart $cart, StorefrontMarket $markets)
    {
        return Inertia::render('Cart', ['cart' => $cart->view($request->session(), $markets->current($request))]);
    }

    public function put(CartLineRequest $request, ShoppingCart $cart, StorefrontMarket $markets)
    {
        $cart->put($request->session(), $markets->current($request), (int) $request->validated('inventory_id'), (int) $request->validated('quantity'));

        return to_route('shop.cart')->with('success', __('shop.cart_updated'));
    }

    public function remove(Request $request, int $inventoryId, ShoppingCart $cart)
    {
        $cart->remove($request->session(), $inventoryId);

        return to_route('shop.cart')->with('success', __('shop.cart_updated'));
    }

    public function clear(Request $request, ShoppingCart $cart)
    {
        $cart->clear($request->session());

        return to_route('shop.cart')->with('success', __('shop.cart_updated'));
    }
}
