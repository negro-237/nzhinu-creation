<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'TEST-'.fake()->unique()->numerify('############'),
            'label' => 'Standard',
            'is_active' => true,
        ];
    }
}
