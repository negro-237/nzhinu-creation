<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FulfillmentStatus: string implements HasLabel
{
    case Unfulfilled = 'unfulfilled';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Collected = 'collected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unfulfilled => 'Non remis', self::Packed => 'Emballé', self::Shipped => 'Expédié', self::Collected => 'Retiré'
        };
    }
}
