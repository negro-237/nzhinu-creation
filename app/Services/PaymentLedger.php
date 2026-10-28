<?php

namespace App\Services;

use App\Enums\PaymentEntryStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Order;

class PaymentLedger
{
    /** Caller holds the order lock. Locking reads avoid stale MySQL snapshots. */
    public function balances(Order $order): array
    {
        $paid = $refunded = $pendingRefunds = $receipts = 0;
        foreach ($order->payments()->orderBy('id')->lockForUpdate()->get() as $payment) {
            if ($payment->status === PaymentEntryStatus::Verified) {
                if ($payment->kind === PaymentKind::Receipt) {
                    $paid += $payment->amount_minor;
                    $receipts++;
                } else {
                    $refunded += $payment->amount_minor;
                }
            } elseif ($payment->kind === PaymentKind::Refund && $payment->status === PaymentEntryStatus::Pending) {
                $pendingRefunds += $payment->amount_minor;
            }
        }

        return compact('paid', 'refunded', 'pendingRefunds', 'receipts') + ['net' => $paid - $refunded];
    }

    public function fullyPaid(Order $order): bool
    {
        $balance = $this->balances($order);

        return $order->total_minor !== null && $balance['receipts'] > 0 && $balance['net'] >= $order->total_minor;
    }

    public function sync(Order $order): void
    {
        $b = $this->balances($order);
        $status = match (true) {
            $b['refunded'] > 0 && $b['net'] === 0 => PaymentStatus::Refunded,
            $b['refunded'] > 0 => PaymentStatus::PartiallyRefunded,
            $order->total_minor !== null && $b['receipts'] > 0 && $b['net'] >= $order->total_minor => PaymentStatus::Paid,
            $b['net'] > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Unpaid,
        };
        $order->update(['verified_paid_minor' => $b['paid'], 'verified_refunded_minor' => $b['refunded'], 'verified_net_minor' => $b['net'], 'payment_status' => $status]);
    }
}
