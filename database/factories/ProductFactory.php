<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Market;
use App\Models\MarketPrice;
use App\Models\MarketProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['category_id' => Category::factory(), 'name' => $name, 'slug' => Str::slug($name), 'status' => ProductStatus::Draft];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if (! $product->variants()->exists()) {
                ProductVariant::factory()->for($product)->create();
            }
        });
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Published]);
    }

    /** Integer minor units; no inventory is invented by this factory. */
    public function withMarketPrices(array $prices = ['CA' => 4900, 'CM' => 20000]): static
    {
        return $this->afterCreating(function (Product $product) use ($prices) {
            foreach (Market::where('is_active', true)->whereIn('code', array_keys($prices))->get() as $market) {
                MarketProduct::firstOrCreate(['market_id' => $market->id, 'product_id' => $product->id], ['is_available' => true]);
                foreach ($product->variants as $variant) {
                    MarketPrice::factory()->for($market)->for($variant, 'variant')
                        ->create(['amount_minor' => $prices[$market->code]]);
                }
            }
        });
    }
}
