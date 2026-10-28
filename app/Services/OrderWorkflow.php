<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderEventKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\StaffPermission;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\User;
use App\Support\MoneyInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderWorkflow
{
    public function __construct(private ReservationService $reservations) {}

    public function confirm(User $actor, int $id, int $version, array $data): Order
    {
        return DB::transaction(function () use ($actor, $id, $version, $data) {
            $order = $this->locked($actor, $id, $version);
            $this->unpaidUnfulfilled($order);
            if ($order->status !== OrderStatus::Pending) {
                $this->invalid();
            }
            $this->checkPickup($order);
            $reservation = InventoryReservation::whereKey($order->inventory_reservation_id)->lockForUpdate()->firstOrFail();
            if ($reservation->status !== ReservationStatus::Active || ! $reservation->expires_at->isFuture()) {
                $this->invalid();
            }
            Validator::make($data, ['shipping_fee' => ['required', 'string'], 'tax_amount' => ['required', 'string'], 'reason' => ['required', 'string', 'min:3', 'max:1000']])->validate();
            try {
                $shipping = MoneyInput::toMinor($data['shipping_fee'], $order->currency);
                $tax = MoneyInput::toMinor($data['tax_amount'], $order->currency);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['shipping_fee' => 'Montants invalides pour la devise de cette commande.']);
            }
            $hours = (int) config('orders.payment_hours');
            if ($hours < 1 || $hours > 720) {
                $this->invalid();
            }
            $deadline = now()->addHours($hours);
            $reservation->update(['expires_at' => $deadline]);
            $order->update(['shipping_fee_minor' => $shipping, 'tax_minor' => $tax, 'total_minor' => $order->subtotal_minor + $shipping + $tax, 'payment_due_at' => $deadline]);
            app(PaymentLedger::class)->sync($order);

            return $this->transition($order, OrderStatus::Confirmed, $data['reason'], $actor->id);
        }, attempts: 3);
    }

    public function cancel(User $actor, int $id, int $version, string $reason): Order
    {
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $id, $version, $reason) {
            $order = $this->locked($actor, $id, $version);
            $this->unpaidUnfulfilled($order, allowPacked: true);
            if (app(PaymentLedger::class)->balances($order)['net'] > 0) {
                Gate::forUser($actor)->authorize(StaffPermission::ManageRefunds->value);
            }
            if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true)) {
                $this->invalid();
            }
            $this->reservations->closeForOrder($order, false, $reason, $actor->id);
            $order->update(['fulfillment_status' => FulfillmentStatus::Unfulfilled]);

            return $this->transition($order, OrderStatus::Cancelled, $reason, $actor->id);
        }, attempts: 3);
    }

    public function reactivate(User $actor, int $id, int $version, string $key): Order
    {
        return DB::transaction(function () use ($actor, $id, $version, $key) {
            $order = $this->locked($actor, $id, $version);
            $this->unpaidUnfulfilled($order);
            if (! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
                $this->invalid();
            }
            $this->checkPickup($order);
            $reservation = $this->reservations->reactivateForOrder($order, $actor, $key);
            if ($reservation->status !== ReservationStatus::Active) {
                $this->invalid();
            }
            $order->update(['shipping_fee_minor' => null, 'tax_minor' => null, 'total_minor' => null, 'payment_due_at' => null]);
            app(PaymentLedger::class)->sync($order);

            return $this->transition($order, OrderStatus::Pending, 'Réactivation : nouvel appel requis', $actor->id);
        }, attempts: 3);
    }

    /** Trusted expiry job. Always lock the order before its reservation and inventories. */
    public function expireByReservation(int $reservationId): Order
    {
        return DB::transaction(function () use ($reservationId) {
            $order = Order::where('inventory_reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true)
                || ($order->payment_status === PaymentStatus::Paid && app(PaymentLedger::class)->balances($order)['receipts'] === 0)
                || app(PaymentLedger::class)->fullyPaid($order) || $order->fulfillment_status !== FulfillmentStatus::Unfulfilled) {
                return $order->load('reservation');
            }
            $reservation = $this->reservations->closeForOrder($order, true, 'Délai dépassé', null);
            if ($reservation->status === ReservationStatus::Expired) {
                $this->transition($order, OrderStatus::Expired, 'Délai de réservation ou de paiement dépassé', null);
            }

            return $order->load('reservation');
        }, attempts: 3);
    }

    private function locked(User $actor, int $id, int $version): Order
    {
        $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $order);
        if ($order->version !== $version) {
            throw ValidationException::withMessages(['version' => 'La commande a changé. Rechargez la liste.']);
        }

        return $order;
    }

    private function unpaidUnfulfilled(Order $order, bool $allowPacked = false): void
    {
        if (($order->payment_status === PaymentStatus::Paid && app(PaymentLedger::class)->balances($order)['receipts'] === 0)
            || ! in_array($order->fulfillment_status, $allowPacked ? [FulfillmentStatus::Unfulfilled, FulfillmentStatus::Packed] : [FulfillmentStatus::Unfulfilled], true)) {
            $this->invalid();
        }
    }

    private function checkPickup(Order $order): void
    {
        if ($order->pickup_point_id) {
            $point = PickupPoint::where('is_active', true)->find($order->pickup_point_id);
            if (! $point || $point->address !== $order->fulfillment_snapshot['address'] || $order->items()->where('stock_location_id', '!=', $point->stock_location_id)->exists()) {
                $this->invalid();
            }
        }
    }

    private function transition(Order $order, OrderStatus $target, string $reason, ?int $actor): Order
    {
        $from = $order->status;
        $order->update(['status' => $target, 'version' => $order->version + 1]);
        $event = match ($target) {
            OrderStatus::Confirmed => OrderEventKind::Confirmed,
            OrderStatus::Cancelled => OrderEventKind::Cancelled,
            OrderStatus::Expired => OrderEventKind::Expired,
            OrderStatus::Pending => OrderEventKind::Reactivated,
            default => null,
        };
        app(OrderJournal::class)->record($order, $actor, $reason, $from, event: $event);

        return $order;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['order' => 'Transition impossible. Vérifiez le statut, le paiement, la remise et l’échéance.']);
    }
}
