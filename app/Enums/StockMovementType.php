<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Adjustment = 'adjustment';
    case Reservation = 'reservation';
    case Release = 'release';
    case Expiry = 'expiry';
    case Handover = 'handover';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adjustment => 'Réception / correction', self::Reservation => 'Réservation',
            self::Release => 'Libération', self::Expiry => 'Expiration',
            self::Handover => 'Remise payée',
        };
    }
}
