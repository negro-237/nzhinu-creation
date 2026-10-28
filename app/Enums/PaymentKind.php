<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentKind: string implements HasLabel
{
    case Receipt = 'receipt';
    case Refund = 'refund';

    public function getLabel(): string
    {
        return $this === self::Receipt ? 'Encaissement' : 'Remboursement';
    }
}
