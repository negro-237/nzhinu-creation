<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderEventKind;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\StaffPermission;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FulfillmentService
{
    public function __construct(private PaymentLedger $payments, private OrderAction $actions, private OrderJournal $journal, private ReservationService $reservations) {}

    public function pack(User $actor, int $orderId, string $key, string $note): Order
    {
        Gate::forUser($actor)->authorize(StaffPermission::FulfillOrders->value);
        Validator::make(['note' => $note], ['note' => ['required', 'string', 'min:3', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $orderId, $key, $note) {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $op = $this->actions->claim($actor, $order, $key, 'pack', [$note]);
            if ($op->completed_at) {
                return $order;
            }
            $this->ready($order, FulfillmentStatus::Unfulfilled);
            $order->update(['fulfillment_status' => FulfillmentStatus::Packed, 'version' => $order->version + 1]);
            $this->journal->record($order, $actor->id, 'Préparation : '.$note, event: OrderEventKind::Packed);
            $op->update(['completed_at' => now()]);

            return $order;
        }, attempts: 3);
    }

    public function handover(User $actor, int $orderId, string $key, array $data): Order
    {
        Gate::forUser($actor)->authorize(StaffPermission::FulfillOrders->value);
        Validator::make($data, ['method' => ['required', Rule::in(['delivery', 'pickup'])], 'reference' => ['required', 'string', 'min:3', 'max:120'],
            'carrier' => ['required_if:method,delivery', 'nullable', 'string', 'max:120'], 'note' => ['required', 'string', 'min:3', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $orderId, $key, $data) {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $op = $this->actions->claim($actor, $order, $key, 'handover', $data);
            if ($op->completed_at) {
                return $order;
            }
            $this->ready($order, FulfillmentStatus::Packed);
            if ($order->method->value !== $data['method']) {
                $this->invalid();
            }
            $this->reservations->consumeForOrder($order, $actor);
            $order->fulfillment()->create(['method' => $data['method'], 'reference' => $data['reference'], 'carrier' => $data['carrier'] ?? null,
                'note' => $data['note'], 'handed_over_by' => $actor->id, 'handed_over_at' => now()]);
            $order->update(['status' => OrderStatus::Completed, 'fulfillment_status' => $data['method'] === 'delivery' ? FulfillmentStatus::Shipped : FulfillmentStatus::Collected,
                'version' => $order->version + 1]);
            $this->journal->record($order, $actor->id, 'Remise payée : '.$data['note'], OrderStatus::Confirmed, event: OrderEventKind::HandedOver);
            $op->update(['completed_at' => now()]);

            return $order;
        }, attempts: 3);
    }

    private function ready(Order $order, FulfillmentStatus $status): void
    {
        if ($order->status !== OrderStatus::Confirmed || $order->fulfillment_status !== $status || ! $this->payments->fullyPaid($order)
            || InventoryReservation::whereKey($order->inventory_reservation_id)->lockForUpdate()->firstOrFail()->status !== ReservationStatus::Active) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['order' => 'Une réservation active et un paiement intégral vérifié sont requis. Vérifiez aussi le statut et le mode de remise.']);
    }
}
