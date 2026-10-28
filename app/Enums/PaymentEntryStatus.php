<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentEntryStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'À vérifier', self::Verified => 'Vérifié', self::Rejected => 'Rejeté'
        };
    }
}
