<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestOrderRequest;
use App\Models\Order;
use App\Services\GuestCheckout;
use App\Services\OrderAccess;
use App\Services\StorefrontMarket;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function show(Request $request, GuestCheckout $checkout, StorefrontMarket $markets)
    {
        try {
            $prepared = $checkout->prepare($request->session(), $markets->current($request));
        } catch (ValidationException $exception) {
            return to_route('shop.cart')->withErrors($exception->errors());
        }

        return Inertia::render('Checkout', ['checkout' => $prepared, 'content' => __('orders')]);
    }

    public function place(GuestOrderRequest $request, GuestCheckout $checkout, OrderAccess $access)
    {
        try {
            $order = $checkout->submit($request->session(), $request->validated());
        } catch (ValidationException $exception) {
            return to_route('checkout.show')->withErrors($exception->errors());
        }

        return redirect()->to($access->link($order));
    }

    public function confirmation(Request $request, string $reference, OrderAccess $access)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $request->attributes->set('shop.receipt_market', $order->market);

        return Inertia::render('OrderConfirmation', ['order' => $access->summary($order), 'content' => __('orders')]);
    }
}
