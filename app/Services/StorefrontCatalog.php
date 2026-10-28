<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CulturalCollection;
use App\Models\Inventory;
use App\Models\Market;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Support\MoneyInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StorefrontCatalog
{
    private function localStock(Builder|Relation $query, Market $market): Builder|Relation
    {
        return $query->whereHas('location', fn (Builder $location) => $location
            ->where('market_id', $market->id)->where('is_active', true)->where('is_placeholder', false)->whereNull('stock_locations.deleted_at'));
    }

    private function query(Market $market): Builder
    {
        return Product::availableIn($market)->with([
            'category', 'media',
            'collections' => fn ($query) => $query->where('is_active', true),
            'variants' => fn ($query) => $query->where('is_active', true)
                ->whereHas('prices', fn ($query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value)),
            'variants.prices' => fn ($query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value),
            'variants.inventories' => fn ($query) => $this->localStock($query, $market)->with('location'),
        ]);
    }

    public function options(Market $market): array
    {
        return [
            'categories' => Category::where('is_active', true)->whereHas('products', fn ($query) => $query->availableIn($market))->orderBy('name')->get(['name', 'slug', 'description']),
            'collections' => CulturalCollection::where('is_active', true)->whereHas('products', fn ($query) => $query->availableIn($market))->orderBy('name')->get(['name', 'slug']),
        ];
    }

    public function featured(Market $market): array
    {
        return $this->query($market)->latest('products.id')->limit(4)->get()->map(fn ($product) => $this->card($product))->all();
    }

    public function homeCategories(Market $market): array
    {
        return Category::where('is_active', true)->whereHas('products', fn ($query) => $query->availableIn($market))
            ->orderBy('name')->get(['name', 'slug', 'description', 'icon'])->map(fn ($category) => [
                'name' => $category->name, 'slug' => $category->slug, 'description' => $category->description,
                'icon' => array_key_exists((string) $category->icon, config('catalog.category_icons')) ? $category->icon : 'grid',
            ])->all();
    }

    public function listing(Market $market, array $filters): array
    {
        $minimum = $this->priceFilter($filters['min_price'] ?? null, $market, 'min_price');
        $maximum = $this->priceFilter($filters['max_price'] ?? null, $market, 'max_price');
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw ValidationException::withMessages(['max_price' => __('shop.price_range_error')]);
        }
        $query = $this->query($market)->select('products.*')->selectSub(
            MarketPrice::selectRaw('MIN(market_prices.amount_minor)')
                ->join('product_variants', 'product_variants.id', '=', 'market_prices.product_variant_id')
                ->whereColumn('product_variants.product_id', 'products.id')->whereNull('product_variants.deleted_at')
                ->where('product_variants.is_active', true)->where('market_id', $market->id)->where('currency', $market->currency->value),
            'starting_price_minor',
        );
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("products.name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("products.description LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('variants', fn ($query) => $query->where('is_active', true)->whereRaw("sku LIKE ? ESCAPE '!'", [$pattern])
                    ->whereHas('prices', fn ($query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value))));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($query) => $query->where('slug', $filters['category']));
        }
        if (! empty($filters['collection'])) {
            $query->whereHas('collections', fn ($query) => $query->where('is_active', true)->where('slug', $filters['collection']));
        }
        if (! empty($filters['in_stock'])) {
            $query->whereHas('variants', fn ($query) => $query->where('is_active', true)
                ->whereHas('prices', fn ($query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value))
                ->whereHas('inventories', fn ($query) => $this->localStock($query, $market)->whereColumn('quantity_on_hand', '>', 'quantity_reserved')));
        }
        // Price range applies to at least one eligible variant, not to an unrelated country's price.
        if ($minimum !== null || $maximum !== null) {
            $query->whereHas('variants', fn ($query) => $query->where('is_active', true)->whereHas('prices', function ($query) use ($market, $minimum, $maximum) {
                $query->where('market_id', $market->id)->where('currency', $market->currency->value);
                if ($minimum !== null) {
                    $query->where('amount_minor', '>=', $minimum);
                }
                if ($maximum !== null) {
                    $query->where('amount_minor', '<=', $maximum);
                }
            }));
        }
        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('starting_price_minor'),
            'price_desc' => $query->orderByDesc('starting_price_minor'),
            'name' => $query->orderBy('products.name'),
            default => $query->orderByDesc('products.created_at'),
        };
        $page = $query->orderByDesc('products.id')->paginate(12)->appends($filters);

        return [
            'items' => $page->getCollection()->map(fn ($product) => $this->card($product))->all(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
            'previous_url' => $page->previousPageUrl(), 'next_url' => $page->nextPageUrl(),
        ];
    }

    private function priceFilter(?string $value, Market $market, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return MoneyInput::toMinor($value, $market->currency);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field => __('shop.price_error', ['currency' => $market->currency->value])]);
        }
    }

    public function detail(Market $market, string $slug): array
    {
        $product = $this->query($market)->where('slug', $slug)->firstOrFail();

        return [...$this->card($product),
            'similar_products' => $this->query($market)->where('category_id', $product->category_id)->whereKeyNot($product->id)
                ->latest('products.id')->limit(4)->get()->map(fn ($similar) => $this->card($similar))->all(),
            'description' => $product->description,
            'meta_description' => Str::limit($product->description ?: $product->name, 155, ''),
            'collections' => $product->collections->map->only(['name', 'slug'])->all(),
            'images' => $product->getMedia('images')->map(fn ($image) => ['url' => $image->getUrl(), 'alt' => $image->getCustomProperty('alt') ?: $product->name])->all(),
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id, 'sku' => $variant->sku, 'label' => $variant->label, 'size' => $variant->size, 'color' => $variant->color,
                'price_minor' => $variant->prices->first()->amount_minor,
                'stocks' => $variant->inventories->map(fn ($stock) => ['id' => $stock->id, 'location' => $stock->location->name, 'available' => $stock->available])->all(),
            ])->values()->all(),
        ];
    }

    private function card(Product $product): array
    {
        $image = $product->getFirstMedia('images');

        return [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
            'category' => $product->category->only(['name', 'slug']),
            'price_minor' => $product->variants->flatMap->prices->min('amount_minor'),
            'in_stock' => $product->variants->contains(fn ($variant) => $variant->inventories->contains(fn ($stock) => $stock->available > 0)),
            'image' => $image ? ['url' => $image->getUrl(), 'alt' => $image->getCustomProperty('alt') ?: $product->name] : null,
        ];
    }

    /** Public stock data: addresses, physical/reserved counts and staff details are never serialized. */
    public function stocks(Market $market, array $ids): Collection
    {
        return $this->localStock(Inventory::query(), $market)->whereIn('id', $ids)
            ->whereHas('variant', fn ($query) => $query->where('is_active', true)->whereNull('product_variants.deleted_at')
                ->whereHas('product', fn ($query) => $query->availableIn($market)->whereNull('products.deleted_at'))
                ->whereHas('prices', fn ($query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value)))
            ->with(['location', 'variant.product.media', 'variant.prices' => fn ($query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value)])
            ->get()->mapWithKeys(function ($stock) {
                $product = $stock->variant->product;
                $image = $product->getFirstMedia('images');

                return [$stock->id => [
                    'inventory_id' => $stock->id, 'name' => $product->name, 'slug' => $product->slug,
                    'variant' => $stock->variant->label, 'location' => $stock->location->name,
                    'available' => $stock->available, 'price_minor' => $stock->variant->prices->first()->amount_minor,
                    'image' => $image ? ['url' => $image->getUrl(), 'alt' => $image->getCustomProperty('alt') ?: $product->name] : null,
                ]];
            });
    }
}
