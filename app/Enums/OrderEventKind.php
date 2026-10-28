<?php

namespace App\Enums;

enum OrderEventKind: string
{
    case Created = 'created';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Reactivated = 'reactivated';
    case PaymentRecorded = 'payment_recorded';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';
    case RefundRecorded = 'refund_recorded';
    case RefundVerified = 'refund_verified';
    case RefundRejected = 'refund_rejected';
    case Packed = 'packed';
    case HandedOver = 'handed_over';

    public function customerVisible(): bool
    {
        return ! in_array($this, [self::PaymentRecorded, self::PaymentRejected, self::RefundRecorded, self::RefundRejected], true);
    }

    public function message(): string
    {
        return __('notifications.'.$this->value);
    }
}
