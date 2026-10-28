<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\StaffPermission;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Market;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private InventoryOperationService $operations, private InventoryLedger $ledger) {}

    public function reserve(User $actor, int $marketId, array $lines, string $requestKey): InventoryReservation
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageReservations->value);
        Validator::make(['lines' => $lines], [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*' => ['array:inventory_id,quantity'],
            'lines.*.inventory_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ])->validate();
        $lines = collect($lines)->map(fn ($line) => ['inventory_id' => (int) $line['inventory_id'], 'quantity' => (int) $line['quantity']])
            ->sortBy('inventory_id')->values();

        return DB::transaction(function () use ($actor, $marketId, $lines, $requestKey) {
            $operation = $this->operations->claim($actor, $requestKey, 'reservation', [$marketId, $lines->all()]);
            if ($operation->inventory_reservation_id) {
                return InventoryReservation::findOrFail($operation->inventory_reservation_id);
            }
            $inventories = $this->lockInventories($lines);
            $this->checkAvailability($marketId, $lines, $inventories);
            $reservation = InventoryReservation::create([
                'reference' => Str::uuid(), 'market_id' => $marketId, 'requested_by' => $actor->id,
                'status' => ReservationStatus::Active, 'cycle' => 1, 'expires_at' => $this->deadline(),
            ]);
            foreach ($lines as $line) {
                $inventory = $inventories[$line['inventory_id']];
                $reservation->items()->create([
                    ...$line, 'stock_location_id' => $inventory->stock_location_id, 'product_variant_id' => $inventory->product_variant_id,
                ]);
                $this->ledger->record($inventory, StockMovementType::Reservation, 0, $line['quantity'], 'Réservation initiale', $actor->id, $reservation->id);
            }
            $this->history($reservation, null, 'Réservation initiale', $actor->id);
            $operation->update(['inventory_reservation_id' => $reservation->id]);

            return $reservation->load('items');
        }, attempts: 3);
    }

    public function release(User $actor, int $reservationId, string $reason, int $expectedCycle): InventoryReservation
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageReservations->value);
        $this->rejectLinked($reservationId);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:1000']])->validate();

        return $this->close($reservationId, ReservationStatus::Released, $reason, $actor->id, $expectedCycle);
    }

    /** Trusted scheduler entry point: only due active reservations may expire. */
    public function expire(int $reservationId): InventoryReservation
    {
        if (Order::where('inventory_reservation_id', $reservationId)->exists()) {
            return app(OrderWorkflow::class)->expireByReservation($reservationId)->reservation;
        }

        return $this->close($reservationId, ReservationStatus::Expired, 'Délai de réservation dépassé', null);
    }

    private function close(int $id, ReservationStatus $target, string $reason, ?int $actorId, ?int $expectedCycle = null): InventoryReservation
    {
        return DB::transaction(function () use ($id, $target, $reason, $actorId, $expectedCycle) {
            $reservation = InventoryReservation::whereKey($id)->lockForUpdate()->firstOrFail();
            if (($expectedCycle !== null && $reservation->cycle !== $expectedCycle) || $reservation->status !== ReservationStatus::Active || ($target === ReservationStatus::Expired && $reservation->expires_at->isFuture())) {
                return $reservation;
            }
            $lines = $reservation->items()->orderBy('inventory_id')->get();
            $inventories = $this->lockInventories($lines);
            foreach ($lines as $line) {
                $this->ledger->record($inventories[$line->inventory_id], $target === ReservationStatus::Expired ? StockMovementType::Expiry : StockMovementType::Release,
                    0, -$line->quantity, $reason, $actorId, $reservation->id);
            }
            $from = $reservation->status;
            $reservation->update(['status' => $target]);
            $this->history($reservation, $from, $reason, $actorId);

            return $reservation;
        }, attempts: 3);
    }

    public function reactivate(User $actor, int $id, string $requestKey): InventoryReservation
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageReservations->value);
        $this->rejectLinked($id);

        return $this->reactivateInternal($actor, $id, $requestKey);
    }

    private function reactivateInternal(User $actor, int $id, string $requestKey): InventoryReservation
    {

        return DB::transaction(function () use ($actor, $id, $requestKey) {
            $operation = $this->operations->claim($actor, $requestKey, 'reactivation', [$id]);
            $reservation = InventoryReservation::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($operation->inventory_reservation_id) {
                return $reservation;
            }
            if (in_array($reservation->status, [ReservationStatus::Active, ReservationStatus::Consumed], true)) {
                throw ValidationException::withMessages(['reservation' => 'Libérez ou expirez la réservation active avant une réactivation.']);
            }
            $lines = $reservation->items()->orderBy('inventory_id')->get();
            $inventories = $this->lockInventories($lines);
            $this->checkAvailability($reservation->market_id, $lines, $inventories);
            $from = $reservation->status;
            $reservation->update(['status' => ReservationStatus::Active, 'cycle' => $reservation->cycle + 1, 'expires_at' => $this->deadline()]);
            foreach ($lines as $line) {
                $this->ledger->record($inventories[$line->inventory_id], StockMovementType::Reservation, 0, $line->quantity, 'Réactivation après vérification du stock', $actor->id, $id);
            }
            $this->history($reservation, $from, 'Réactivation après vérification du stock', $actor->id);
            $operation->update(['inventory_reservation_id' => $id]);

            return $reservation;
        }, attempts: 3);
    }

    /** Trusted order action only; the caller owns the surrounding DB transaction and order lock. */
    public function reserveForOrder(Order $order, array $lines): InventoryReservation
    {
        if (! $order->exists || DB::transactionLevel() === 0 || $order->inventory_reservation_id) {
            throw new \LogicException('An unreserved persisted order and transaction are required.');
        }
        $lines = collect($lines)->sortBy('inventory_id')->values();
        $inventories = $this->lockInventories($lines);
        $this->checkAvailability($order->market_id, $lines, $inventories);
        $reservation = InventoryReservation::create([
            'reference' => Str::uuid(), 'market_id' => $order->market_id, 'requested_by' => null,
            'status' => ReservationStatus::Active, 'cycle' => 1, 'expires_at' => $this->deadline(),
        ]);
        foreach ($lines as $line) {
            $inventory = $inventories[$line['inventory_id']];
            $reservation->items()->create([...$line, 'stock_location_id' => $inventory->stock_location_id, 'product_variant_id' => $inventory->product_variant_id]);
            $this->ledger->record($inventory, StockMovementType::Reservation, 0, $line['quantity'], 'Commande '.$order->number, null, $reservation->id);
        }
        $this->history($reservation, null, 'Commande '.$order->number, null);

        return $reservation;
    }

    public function closeForOrder(Order $order, bool $expired, string $reason, ?int $actorId): InventoryReservation
    {
        $this->orderContext($order);

        return $this->close($order->inventory_reservation_id, $expired ? ReservationStatus::Expired : ReservationStatus::Released, $reason, $actorId);
    }

    public function reactivateForOrder(Order $order, User $actor, string $key): InventoryReservation
    {
        $this->orderContext($order);

        return $this->reactivateInternal($actor, $order->inventory_reservation_id, $key);
    }

    private function orderContext(Order $order): void
    {
        if (! $order->exists || ! $order->inventory_reservation_id || DB::transactionLevel() === 0) {
            throw new \LogicException('An order transaction is required.');
        }
    }

    /** Called inside the authorized handover transaction, after taking the order lock. */
    public function consumeForOrder(Order $order, User $actor): InventoryReservation
    {
        $this->orderContext($order);
        Gate::forUser($actor)->authorize(StaffPermission::FulfillOrders->value);
        if ($order->status !== OrderStatus::Confirmed || $order->fulfillment_status !== FulfillmentStatus::Packed || ! app(PaymentLedger::class)->fullyPaid($order)) {
            throw ValidationException::withMessages(['order' => 'La remise exige une commande préparée et intégralement payée après vérification.']);
        }
        $reservation = InventoryReservation::whereKey($order->inventory_reservation_id)->lockForUpdate()->firstOrFail();
        if ($reservation->status !== ReservationStatus::Active) {
            throw ValidationException::withMessages(['order' => 'La réservation doit être active.']);
        }
        $lines = $reservation->items()->orderBy('inventory_id')->get();
        $inventories = $this->lockInventories($lines);
        foreach ($lines as $line) {
            $this->ledger->record($inventories[$line->inventory_id], StockMovementType::Handover, -$line->quantity, -$line->quantity,
                'Remise payée de la commande '.$order->number, $actor->id, $reservation->id);
        }
        $reservation->update(['status' => ReservationStatus::Consumed]);
        $this->history($reservation, ReservationStatus::Active, 'Remise payée de la commande '.$order->number, $actor->id);

        return $reservation;
    }

    private function rejectLinked(int $id): void
    {
        if (Order::where('inventory_reservation_id', $id)->exists()) {
            throw ValidationException::withMessages(['reservation' => 'Utilisez le workflow de la commande liée.']);
        }
    }

    private function lockInventories(Collection $lines): Collection
    {
        // Explicit individual primary-key locks ensure identical ordering for every workflow.
        $inventories = collect();
        foreach ($lines->pluck('inventory_id')->sort()->values() as $id) {
            $inventories[$id] = Inventory::whereKey($id)->lockForUpdate()->firstOrFail();
        }

        return $inventories;
    }

    private function checkAvailability(int $marketId, Collection $lines, Collection $inventories): void
    {
        $market = Market::where('is_active', true)->findOrFail($marketId);
        foreach ($lines as $index => $line) {
            $inventory = $inventories[$line['inventory_id']];
            $location = $inventory->location;
            $variant = $inventory->variant;
            $eligible = $location->market_id === $marketId && $location->is_active && ! $location->is_placeholder && ! $location->trashed()
                && $variant->is_active && ! $variant->trashed()
                && Product::availableIn($market)->whereKey($variant->product_id)->exists()
                && $variant->prices()->where('market_id', $marketId)->where('currency', $market->currency->value)->exists();
            if (! $eligible || $inventory->available < $line['quantity']) {
                throw ValidationException::withMessages(["lines.$index.inventory_id" => 'Stock insuffisant, lieu indisponible ou variante non publiée dans ce marché.']);
            }
        }
    }

    private function deadline(): Carbon
    {
        $hours = (int) config('inventory.reservation_hours');
        if ($hours < 1 || $hours > 720) {
            throw ValidationException::withMessages(['reservation' => 'Le délai doit être compris entre 1 et 720 heures.']);
        }

        return now()->addHours($hours);
    }

    private function history(InventoryReservation $reservation, ?ReservationStatus $from, string $reason, ?int $actorId): void
    {
        $reservation->histories()->create([
            'from_status' => $from, 'to_status' => $reservation->status, 'cycle' => $reservation->cycle,
            'actor_id' => $actorId, 'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
