<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'À appeler', self::Confirmed => 'Confirmée', self::Cancelled => 'Annulée', self::Expired => 'Expirée', self::Completed => 'Remise effectuée'
        };
    }
}
