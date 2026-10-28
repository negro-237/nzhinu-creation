<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Bank = 'bank_transfer';
    case Interac = 'interac';
    case Mobile = 'mobile_money';
    case Cash = 'cash';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Virement bancaire', self::Interac => 'Interac', self::Mobile => 'Mobile Money', self::Cash => 'Espèces', self::Other => 'Autre moyen manuel'
        };
    }
}
