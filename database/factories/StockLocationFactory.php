<?php

namespace Database\Factories;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'code' => fake()->unique()->bothify('TEST-########'),
            'name' => 'Dépôt de test',
            'address' => 'ADRESSE FICTIVE — tests uniquement',
            'is_placeholder' => true,
            'is_active' => false,
        ];
    }
}
