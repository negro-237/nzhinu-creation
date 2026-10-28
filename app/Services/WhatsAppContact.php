<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class WhatsAppContact
{
    public function customerLink(User $actor, Order $order): ?string
    {
        Gate::forUser($actor)->authorize('view', $order);
        if (! preg_match('/\A\+[1-9][0-9]{7,14}\z/', $order->phone)) {
            return null;
        }

        return 'https://wa.me/'.substr($order->phone, 1).'?text='.rawurlencode(__('orders.whatsapp_order', ['number' => $order->number]));
    }

    public function link(Market $market, ?Order $order = null): ?string
    {
        $number = (string) config('communications.whatsapp_'.strtolower($market->code));
        if (! preg_match('/\A[1-9][0-9]{7,14}\z/', $number)) {
            return null;
        }
        $message = $order ? __('orders.whatsapp_order', ['number' => $order->number]) : __('shop.whatsapp_message');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }
}
