<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Market;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketPriceFactory extends Factory
{
    public function definition(): array
    {
        return ['market_id' => fn () => Market::firstOrCreate(['code' => 'CA'], ['name' => 'Canada', 'currency' => Currency::CAD, 'is_active' => true])->id,
            'product_variant_id' => ProductVariant::factory(),
            'amount_minor' => 4900, 'currency' => fn (array $attributes) => Market::findOrFail($attributes['market_id'])->currency];
    }
}
