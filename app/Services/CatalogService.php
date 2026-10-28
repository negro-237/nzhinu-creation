<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Market;
use App\Models\MarketPrice;
use App\Models\MarketProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\MoneyInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CatalogService
{
    /** Save the complete editable product aggregate. Omitted variants are archived. */
    public function save(User $actor, array $data, ?Product $product = null): Product
    {
        Gate::forUser($actor)->authorize($product ? 'update' : 'create', $product ?? Product::class);

        return DB::transaction(function () use ($actor, $data, $product) {
            if ($product) {
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                Gate::forUser($actor)->authorize('update', $product);
            }
            if (! array_key_exists('variants', $data)) {
                $data['variants'] = [[
                    'sku' => 'NZ-'.Str::upper(Str::random(16)), 'label' => 'Standard', 'is_active' => true, 'prices' => [],
                ]];
            }
            $data['collections'] ??= [];
            $data['markets'] ??= [];
            Validator::make($data, ['variants' => ['required', 'array'], 'variants.*' => ['array'], 'variants.*.sku' => ['nullable', 'string']])->validate();
            foreach ($data['variants'] as &$variant) {
                if (is_array($variant)) {
                    $variant['sku'] = Str::upper(trim((string) ($variant['sku'] ?? '')));
                    if (empty($variant['id'])) {
                        unset($variant['id']);
                    }
                }
            }
            unset($variant);

            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('products', 'slug')->ignore($product?->id)],
                'description' => ['nullable', 'string', 'max:20000'],
                'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
                'status' => ['required', Rule::enum(ProductStatus::class)],
                'version' => [$product ? 'required' : 'nullable', 'integer', 'min:0'],
                'collections' => ['array', 'max:20'],
                'collections.*' => ['integer', 'distinct', Rule::exists('cultural_collections', 'id')->whereNull('deleted_at')->where('is_active', true)],
                'variants' => ['required', 'array', 'min:1', 'max:100'],
                'variants.*' => ['array:id,sku,label,size,color,is_active,prices'],
                'variants.*.id' => ['sometimes', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $product?->id ?? 0)->whereNull('deleted_at')],
                'variants.*.sku' => ['required', 'string', 'max:100', 'regex:/\A[A-Z0-9][A-Z0-9._-]*\z/', 'distinct'],
                'variants.*.label' => ['required', 'string', 'max:100'],
                'variants.*.size' => ['nullable', 'string', 'max:50'],
                'variants.*.color' => ['nullable', 'string', 'max:100'],
                'variants.*.is_active' => ['required', 'boolean'],
                'variants.*.prices' => ['present', 'array', 'max:2'],
                'variants.*.prices.*' => ['array:market_id,amount'],
                'variants.*.prices.*.market_id' => ['required', 'integer', Rule::exists('markets', 'id')->where('is_active', true)],
                'variants.*.prices.*.amount' => ['required', 'string', 'max:20'],
                'markets' => ['array', 'max:2'],
                'markets.*' => ['array:market_id,is_available'],
                'markets.*.market_id' => ['required', 'integer', 'distinct', Rule::exists('markets', 'id')->where('is_active', true)],
                'markets.*.is_available' => ['required', 'boolean'],
            ];
            foreach ($data['variants'] as $key => $variant) {
                $rules["variants.$key.sku"] = [...$rules['variants.*.sku'], Rule::unique('product_variants', 'sku')->ignore($variant['id'] ?? null)];
            }
            $validated = Validator::make($data, $rules)->validate();
            if ($product && (int) $validated['version'] !== $product->version) {
                throw ValidationException::withMessages(['version' => 'Ce produit a changé. Rechargez la page avant de réessayer.']);
            }
            if (! collect($validated['variants'])->contains(fn ($variant) => (bool) $variant['is_active'])) {
                throw ValidationException::withMessages(['variants' => 'Conservez au moins une variante active.']);
            }
            $markets = Market::query()->where('is_active', true)->get()->keyBy('id');
            $enabled = collect($validated['markets'])->where('is_available', true)->pluck('market_id')->map(fn ($id) => (int) $id);
            foreach ($validated['variants'] as $key => &$variant) {
                $seen = [];
                foreach ($variant['prices'] as $priceKey => &$price) {
                    $marketId = (int) $price['market_id'];
                    if (in_array($marketId, $seen, true)) {
                        throw ValidationException::withMessages(["variants.$key.prices.$priceKey.market_id" => 'Un seul prix par marché et variante.']);
                    }
                    $seen[] = $marketId;
                    try {
                        $price['amount_minor'] = MoneyInput::toMinor($price['amount'], $markets[$marketId]->currency);
                    } catch (InvalidArgumentException $exception) {
                        throw ValidationException::withMessages(["variants.$key.prices.$priceKey.amount" => $exception->getMessage()]);
                    }
                }
                unset($price);
                if ($variant['is_active'] && $enabled->diff($seen)->isNotEmpty()) {
                    throw ValidationException::withMessages(["variants.$key.prices" => 'Ajoutez un prix pour chaque marché activé.']);
                }
            }
            unset($variant);

            $attributes = collect($validated)->only(['name', 'slug', 'description', 'category_id', 'status'])->all();
            if ($product) {
                $product->update([...$attributes, 'version' => $product->version + 1]);
            } else {
                $product = Product::create($attributes);
            }
            $product->collections()->sync($validated['collections']);
            $kept = [];
            foreach ($validated['variants'] as $variantData) {
                $variant = isset($variantData['id'])
                    ? $product->variants()->findOrFail($variantData['id'])
                    : new ProductVariant(['product_id' => $product->id]);
                $variant->fill(collect($variantData)->only(['sku', 'label', 'size', 'color', 'is_active'])->all())->save();
                $kept[] = $variant->id;
                $priceMarkets = [];
                foreach ($variantData['prices'] as $price) {
                    $priceMarkets[] = (int) $price['market_id'];
                    MarketPrice::updateOrCreate(['market_id' => $price['market_id'], 'product_variant_id' => $variant->id], [
                        'amount_minor' => $price['amount_minor'], 'currency' => $markets[$price['market_id']]->currency,
                    ]);
                }
                // Current catalog prices may change; order snapshots will preserve historical amounts.
                $variant->prices()->whereNotIn('market_id', $priceMarkets)->delete();
            }
            $product->variants()->whereNotIn('id', $kept)->update(['is_active' => false, 'deleted_at' => now()]);
            $product->marketProducts()->update(['is_available' => false]);
            foreach ($validated['markets'] as $marketData) {
                MarketProduct::updateOrCreate(['product_id' => $product->id, 'market_id' => $marketData['market_id']], [
                    'is_available' => $marketData['is_available'],
                ]);
            }

            return $product->fresh(['variants.prices', 'marketProducts', 'collections']);
        }, attempts: 3);
    }

    public function archive(User $actor, Product $product): bool
    {
        Gate::forUser($actor)->authorize('delete', $product);

        return DB::transaction(function () use ($product) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            return (bool) $product->delete();
        });
    }

    public function formData(Product $product): array
    {
        $product->load(['variants.prices', 'marketProducts', 'collections']);

        return [
            ...$product->only(['name', 'slug', 'description', 'category_id', 'version']),
            'status' => $product->status->value,
            'collections' => $product->collections->modelKeys(),
            'variants' => $product->variants->map(fn ($variant) => [
                ...$variant->only(['id', 'sku', 'label', 'size', 'color', 'is_active']),
                'prices' => $variant->prices->map(fn ($price) => [
                    'market_id' => $price->market_id, 'amount' => MoneyInput::fromMinor($price->amount_minor, $price->currency),
                ])->all(),
            ])->all(),
            'markets' => $product->marketProducts->map->only(['market_id', 'is_available'])->all(),
        ];
    }
}
