<?php

namespace App\Services;

use App\Enums\OrderEventKind;
use App\Enums\OrderStatus;
use App\Models\Order;

class OrderJournal
{
    public function record(Order $order, ?int $actor, string $reason, ?OrderStatus $from = null, array $context = [], ?OrderEventKind $event = null): void
    {
        $history = $order->histories()->create(['from_status' => ($from ?? $order->status)->value, 'to_status' => $order->status->value,
            'payment_status' => $order->payment_status->value, 'fulfillment_status' => $order->fulfillment_status->value,
            'actor_id' => $actor, 'reason' => $reason, 'created_at' => now(),
            'context' => $context + ['paid_minor' => $order->verified_paid_minor, 'refunded_minor' => $order->verified_refunded_minor,
                'net_minor' => $order->verified_net_minor, 'currency' => $order->currency->value]]);
        if ($event) {
            app(OrderNotifications::class)->enqueue($history, $event);
        }
    }
}
