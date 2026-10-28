<?php

require __DIR__.'/e2e-bootstrap.php';

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderNotificationEvent;
use App\Models\StockMovement;
use App\Notifications\OrderUpdateNotification;
use App\Services\OrderNotifications;
use Illuminate\Support\Facades\DB;

$order = Order::where('number', $argv[1] ?? '')->firstOrFail();
if ($order->status !== OrderStatus::Completed || $order->reservation->status !== ReservationStatus::Consumed || $order->verified_net_minor !== $order->total_minor
    || StockMovement::where('inventory_reservation_id', $order->inventory_reservation_id)->where('type', 'handover')->count() !== 1) {
    throw new RuntimeException('Browser order/payment/stock invariants failed.');
}
$stock = Inventory::findOrFail($order->items->first()->inventory_id);
foreach (Inventory::all() as $inventory) {
    $consumed = -(int) StockMovement::where('inventory_id', $inventory->id)->where('type', 'handover')->sum('quantity_delta');
    if ($inventory->quantity_on_hand !== 12 - $consumed || $inventory->quantity_reserved !== 0) {
        throw new RuntimeException('Physical stock was not consumed exactly once at its own location.');
    }
}
$notifications = app(OrderNotifications::class);
foreach (OrderNotificationEvent::whereNull('expanded_at')->get() as $event) {
    $notifications->expand($event->id);
}
foreach (OrderNotificationDelivery::where('status', 'pending')->get() as $delivery) {
    $notifications->deliver($delivery->id);
}
if (! DB::table('notifications')->where('type', OrderUpdateNotification::class)->exists()) {
    throw new RuntimeException('Missing staff notifications');
}
echo json_encode(['completed' => true, 'on_hand' => $stock->quantity_on_hand, 'reserved' => 0, 'notifications' => true], JSON_THROW_ON_ERROR);
