<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

class OrderAccess
{
    public function link(Order $order): string
    {
        $days = max(1, min(90, (int) config('orders.link_days')));

        return URL::temporarySignedRoute('orders.show', now()->addDays($days), ['reference' => $order->reference]);
    }

    public function summary(Order $order): array
    {
        return [
            'whatsapp_url' => app(WhatsAppContact::class)->link($order->market, $order),
            'number' => $order->number, 'market' => ['code' => $order->market->code, 'name' => $order->market->name, 'currency' => $order->currency->value],
            'status' => $order->status->getLabel(), 'status_code' => $order->status->value, 'payment_status' => $order->payment_status->getLabel(),
            'fulfillment_status' => $order->fulfillment_status->getLabel(), 'method' => $order->method->value,
            'phone_hint' => '•••• '.substr($order->phone, -4), 'fulfillment' => $order->fulfillment_snapshot,
            'subtotal_minor' => $order->subtotal_minor, 'total_minor' => $order->total_minor,
            'shipping_fee_minor' => $order->shipping_fee_minor, 'tax_minor' => $order->tax_minor,
            'verified_paid_minor' => $order->verified_paid_minor, 'verified_refunded_minor' => $order->verified_refunded_minor,
            'verified_net_minor' => $order->verified_net_minor,
            'outstanding_minor' => $order->total_minor === null ? null : max(0, $order->total_minor - $order->verified_net_minor),
            'handover' => $order->fulfillment ? ['at' => $order->fulfillment->handed_over_at->toIso8601String(), 'carrier' => $order->fulfillment->carrier, 'reference' => $order->fulfillment->reference] : null,
            'expires_at' => $order->reservation->expires_at->toIso8601String(),
            'items' => $order->items->map->only(['product_name', 'variant_label', 'sku', 'quantity', 'unit_price_minor', 'line_total_minor'])->all(),
        ];
    }
}
