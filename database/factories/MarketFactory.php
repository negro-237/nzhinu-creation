<?php

namespace Database\Factories;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => 'CA', 'name' => 'Canada', 'currency' => Currency::CAD, 'is_active' => true];
    }

    public function cameroon(): static
    {
        return $this->state(fn () => ['code' => 'CM', 'name' => 'Cameroun', 'currency' => Currency::XAF]);
    }
}
