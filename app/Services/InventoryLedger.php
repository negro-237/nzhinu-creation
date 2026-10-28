<?php

namespace App\Services;

use App\Enums\StaffPermission;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class InventoryLedger
{
    /** Internal primitive: caller holds the inventory row lock in its transaction. */
    public function record(Inventory $inventory, StockMovementType $type, int $delta, int $reservedDelta, string $reason, ?int $actorId, ?int $reservationId = null): void
    {
        $onHand = $inventory->quantity_on_hand + $delta;
        $reserved = $inventory->quantity_reserved + $reservedDelta;
        if ($onHand < 0 || $onHand > 1000000000 || $reserved < 0 || $reserved > $onHand) {
            throw ValidationException::withMessages(['quantity_delta' => 'Quantité impossible : le stock physique doit couvrir toutes les réservations.']);
        }
        $inventory->quantity_on_hand = $onHand;
        $inventory->quantity_reserved = $reserved;
        $low = $inventory->available <= $inventory->low_stock_threshold;
        $notify = $low && $inventory->low_stock_alerted_at === null;
        $inventory->low_stock_alerted_at = $low ? ($inventory->low_stock_alerted_at ?? now()) : null;
        $inventory->save();
        StockMovement::create([
            'inventory_id' => $inventory->id, 'inventory_reservation_id' => $reservationId,
            'actor_id' => $actorId, 'type' => $type,
            'quantity_delta' => $delta, 'reserved_delta' => $reservedDelta,
            'on_hand_after' => $onHand, 'reserved_after' => $reserved,
            'threshold_after' => $inventory->low_stock_threshold, 'reason' => $reason, 'created_at' => now(),
        ]);
        if ($notify) {
            Notification::send(User::permission(StaffPermission::ManageInventory->value)->get(),
                new LowStockNotification($inventory->id, $inventory->variant->sku, $inventory->location->name, $inventory->available));
        }
    }
}
