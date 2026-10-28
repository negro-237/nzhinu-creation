<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Non payé', self::Partial => 'Partiellement payé', self::Paid => 'Payé', self::Refunded => 'Remboursé', self::PartiallyRefunded => 'Partiellement remboursé'
        };
    }
}
