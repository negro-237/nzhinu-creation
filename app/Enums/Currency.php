<?php

namespace App\Enums;

enum Currency: string
{
    case CAD = 'CAD';
    case XAF = 'XAF';

    public function fractionDigits(): int
    {
        return match ($this) {
            self::CAD => 2,
            self::XAF => 0,
        };
    }
}
