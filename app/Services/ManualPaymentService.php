<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderEventKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentEntryStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\StaffPermission;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Support\MoneyInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManualPaymentService
{
    public function __construct(private PaymentLedger $ledger, private OrderAction $actions, private OrderJournal $journal) {}

    public function record(User $actor, int $orderId, string $key, array $data, PaymentKind $kind = PaymentKind::Receipt): OrderPayment
    {
        Gate::forUser($actor)->authorize($kind === PaymentKind::Refund ? StaffPermission::ManageRefunds->value : StaffPermission::RecordPayments->value);
        Validator::make($data, ['amount' => ['required', 'string'], 'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['required', 'string', 'max:120'], 'note' => ['required', 'string', 'min:3', 'max:1000']])->validate();
        $data['reference'] = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $data['reference'])));
        Validator::make($data, ['reference' => ['required', 'string', 'max:120']])->validate();

        return DB::transaction(function () use ($actor, $orderId, $key, $data, $kind) {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $op = $this->actions->claim($actor, $order, $key, 'record_'.$kind->value, $data);
            if ($op->completed_at) {
                return OrderPayment::findOrFail($op->payment_id);
            }
            try {
                $amount = MoneyInput::toMinor($data['amount'], $order->currency);
            } catch (\InvalidArgumentException) {
                $this->invalid('Montant invalide pour la devise de la commande.');
            }
            $b = $this->ledger->balances($order);
            if ($kind === PaymentKind::Refund && ($amount < 1 || $amount > $b['net'] - $b['pendingRefunds'])) {
                $this->invalid('Le remboursement dépasse les fonds vérifiés disponibles, après les remboursements en attente.');
            }
            $inserted = OrderPayment::insertOrIgnore(['order_id' => $order->id, 'market_id' => $order->market_id, 'kind' => $kind->value,
                'status' => 'pending', 'amount_minor' => $amount, 'currency' => $order->currency->value, 'method' => $data['method'],
                'reference' => $data['reference'], 'note' => $data['note'], 'received_at' => now(), 'recorded_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now()]);
            if (! $inserted) {
                $this->invalid('Référence déjà enregistrée dans ce marché pour ce moyen et ce type de paiement.');
            }
            $payment = $order->payments()->where('kind', $kind->value)->where('method', $data['method'])->where('reference', $data['reference'])->firstOrFail();
            $this->finish($order, $actor, $kind === PaymentKind::Refund ? 'Remboursement déclaré, à vérifier' : 'Paiement déclaré, à vérifier', $payment->id, $kind === PaymentKind::Refund ? OrderEventKind::RefundRecorded : OrderEventKind::PaymentRecorded);
            $op->update(['payment_id' => $payment->id, 'completed_at' => now()]);

            return $payment;
        }, attempts: 3);
    }

    public function verify(User $actor, int $paymentId, string $key, array $data): OrderPayment
    {
        Gate::forUser($actor)->authorize(StaffPermission::VerifyPayments->value);
        Validator::make($data, ['source' => ['required', Rule::in(['bank_statement', 'provider_record', 'cash_count'])],
            'note' => ['required', 'string', 'min:10', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $paymentId, $key, $data) {
            $orderId = OrderPayment::findOrFail($paymentId)->order_id;
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $op = $this->actions->claim($actor, $order, $key, 'verify', [$paymentId, $data]);
            $payment = OrderPayment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ($payment->kind === PaymentKind::Refund) {
                Gate::forUser($actor)->authorize(StaffPermission::ManageRefunds->value);
            }
            if ($op->completed_at) {
                return $payment;
            }
            if ($payment->status !== PaymentEntryStatus::Pending) {
                $this->invalid('Seule une déclaration en attente peut être vérifiée.');
            }
            if (($payment->method === PaymentMethod::Cash) !== ($data['source'] === 'cash_count')) {
                $this->invalid('La source de vérification doit correspondre au moyen de paiement.');
            }
            if ($payment->kind === PaymentKind::Refund && $payment->amount_minor > $this->ledger->balances($order)['net']) {
                $this->invalid('Fonds insuffisants pour ce remboursement.');
            }
            // A transfer seen after expiry is recorded without silently reserving stock again.
            if (! $this->ledger->fullyPaid($order) && in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true)) {
                app(OrderWorkflow::class)->expireByReservation($order->inventory_reservation_id);
                $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            }
            $payment->update(['status' => PaymentEntryStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now(),
                'verification_source' => $data['source'], 'verification_note' => $data['note']]);
            $this->ledger->sync($order);
            if ($order->fulfillment_status === FulfillmentStatus::Packed && ! $this->ledger->fullyPaid($order)) {
                $order->update(['fulfillment_status' => FulfillmentStatus::Unfulfilled]);
            }
            $this->finish($order, $actor, 'Vérification manuelle : '.$payment->kind->getLabel(), $payment->id, $payment->kind === PaymentKind::Refund ? OrderEventKind::RefundVerified : OrderEventKind::PaymentVerified);
            $op->update(['payment_id' => $payment->id, 'completed_at' => now()]);

            return $payment;
        }, attempts: 3);
    }

    public function reject(User $actor, int $paymentId, string $key, string $reason): OrderPayment
    {
        Gate::forUser($actor)->authorize(StaffPermission::VerifyPayments->value);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $paymentId, $key, $reason) {
            $order = Order::whereKey(OrderPayment::findOrFail($paymentId)->order_id)->lockForUpdate()->firstOrFail();
            $op = $this->actions->claim($actor, $order, $key, 'reject', [$paymentId, $reason]);
            $payment = OrderPayment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ($payment->kind === PaymentKind::Refund) {
                Gate::forUser($actor)->authorize(StaffPermission::ManageRefunds->value);
            }
            if ($op->completed_at) {
                return $payment;
            }
            if ($payment->status !== PaymentEntryStatus::Pending) {
                $this->invalid('Cette déclaration a déjà été traitée.');
            }
            $payment->update(['status' => PaymentEntryStatus::Rejected, 'rejected_by' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            $this->finish($order, $actor, 'Déclaration rejetée : '.$reason, $payment->id, $payment->kind === PaymentKind::Refund ? OrderEventKind::RefundRejected : OrderEventKind::PaymentRejected);
            $op->update(['payment_id' => $payment->id, 'completed_at' => now()]);

            return $payment;
        }, attempts: 3);
    }

    private function finish(Order $order, User $actor, string $reason, int $paymentId, OrderEventKind $event): void
    {
        $order->update(['version' => $order->version + 1]);
        $this->journal->record($order, $actor->id, $reason, context: ['payment_id' => $paymentId], event: $event);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
