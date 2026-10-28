<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasLabel
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
    case Consumed = 'consumed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active', self::Released => 'Libérée', self::Expired => 'Expirée',
            self::Consumed => 'Consommée à la remise',
        };
    }
}
