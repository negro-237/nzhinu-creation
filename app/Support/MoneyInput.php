<?php

namespace App\Support;

use App\Enums\Currency;
use InvalidArgumentException;

class MoneyInput
{
    // Deliberately bounded below BIGINT/PHP overflow, also safe in JavaScript integers.
    public const MAX_MINOR = 999999999999;

    public static function toMinor(string $value, Currency $currency): int
    {
        $value = str_replace(',', '.', trim($value));
        $digits = $currency->fractionDigits();
        $pattern = $digits === 0 ? '/\A\d{1,12}\z/' : '/\A\d{1,12}(?:\.\d{1,2})?\z/';
        if (! preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Montant invalide pour '.$currency->value.'.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $minor = (int) $whole * (10 ** $digits) + (int) str_pad($fraction, $digits, '0');
        if ($minor > self::MAX_MINOR) {
            throw new InvalidArgumentException('Montant trop élevé.');
        }

        return $minor;
    }

    public static function fromMinor(int $minor, Currency $currency): string
    {
        if ($currency->fractionDigits() === 0) {
            return (string) $minor;
        }

        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
