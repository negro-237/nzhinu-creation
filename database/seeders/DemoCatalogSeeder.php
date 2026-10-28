<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Enums\StaffPermission;
use App\Models\Category;
use App\Models\Market;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Le catalogue de démonstration est réservé aux environnements local et testing.');
        }
        $actor = User::permission(StaffPermission::ManageCatalog->value)->orderBy('id')->first();
        if (! $actor) {
            throw new \LogicException('Un compte existant autorisé à gérer le catalogue est requis. Aucun compte ne sera créé.');
        }
        DB::transaction(function () use ($actor) {
            $this->call([MarketsSeeder::class, CatalogTaxonomySeeder::class]);
            $markets = Market::whereIn('code', config('storefront.markets'))->where('is_active', true)->get()->keyBy('code');
            $currencies = ['CA' => Currency::CAD, 'CM' => Currency::XAF];
            if ($markets->isEmpty() || $markets->count() !== count(config('storefront.markets')) || $markets->contains(fn ($market) => ($currencies[$market->code] ?? null) !== $market->currency)) {
                throw new \LogicException('Les marchés publics configurés doivent être actifs avec leur devise attendue.');
            }
            foreach ($this->samples() as $index => [$name, $slug, $categorySlug, $cad, $xaf, $sizes]) {
                $slug = 'demo-'.$slug;
                // Existing and archived rows are intentionally left untouched, including their prices and variants.
                if (Product::withTrashed()->where('slug', $slug)->exists()) {
                    continue;
                }
                $skus = array_map(fn ($size) => sprintf('DEMO-NZ-%02d-%s', $index + 1, $size ?? 'STD'), $sizes);
                if (ProductVariant::withTrashed()->whereIn('sku', $skus)->exists()) {
                    continue;
                }
                $category = Category::where('slug', $categorySlug)->where('is_active', true)->first();
                if (! $category) {
                    throw new \LogicException('Une catégorie de démonstration est inactive ou archivée : '.$categorySlug);
                }
                $attributes = Product::factory()->make(['category_id' => $category->id, 'name' => $name, 'slug' => $slug,
                    'status' => ProductStatus::Published,
                    'description' => 'Produit de démonstration — présentation et prix indicatifs, à remplacer ou archiver avant la mise en vente réelle. Aucun stock physique n’est créé.']);
                $variants = [];
                foreach ($sizes as $size) {
                    $variant = ProductVariant::factory()->make(['product_id' => 0, 'sku' => sprintf('DEMO-NZ-%02d-%s', $index + 1, $size ?? 'STD'),
                        'label' => $size ? 'Taille '.$size : 'Standard', 'size' => $size, 'color' => null, 'is_active' => true]);
                    $variants[] = [...$variant->only(['sku', 'label', 'size', 'color', 'is_active']),
                        'prices' => $markets->map(fn ($market) => ['market_id' => $market->id, 'amount' => $market->code === 'CA' ? $cad : $xaf])->values()->all()];
                }
                app(CatalogService::class)->save($actor, [...$attributes->only(['name', 'slug', 'description', 'category_id']),
                    'status' => ProductStatus::Published->value, 'variants' => $variants,
                    'markets' => $markets->map(fn ($market) => ['market_id' => $market->id, 'is_available' => true])->values()->all()]);
            }
        }, attempts: 3);
        $this->call(DemoProductImagesSeeder::class);
    }

    private function samples(): array
    {
        return [
            ['Robe en wax — démo', 'robe-wax', 'vetements', '89.90', '35000', ['S', 'M', 'L']],
            ['Chemise à motifs — démo', 'chemise-motifs', 'vetements', '69.00', '28000', ['M', 'L', 'XL']],
            ['Ensemble deux pièces — démo', 'ensemble-deux-pieces', 'vetements', '129.00', '52000', ['S', 'M', 'L']],
            ['Jupe portefeuille — démo', 'jupe-portefeuille', 'vetements', '59.90', '24000', ['S', 'M', 'L']],
            ['Tunique unisexe — démo', 'tunique-unisexe', 'vetements', '79.00', '32000', ['M', 'L']],
            ['Veste à imprimés — démo', 'veste-imprimes', 'vetements', '119.00', '48000', ['M', 'L']],
            ['Sac en tissu imprimé — démo', 'sac-tissu', 'accessoires', '45.00', '18000', [null]],
            ['Pochette de soirée — démo', 'pochette-soiree', 'accessoires', '29.90', '12000', [null]],
            ['Foulard imprimé — démo', 'foulard-imprime', 'accessoires', '25.00', '10000', [null]],
            ['Collier de perles — démo', 'collier-perles', 'bijoux', '39.90', '16000', [null]],
            ['Bracelet de perles — démo', 'bracelet-perles', 'bijoux', '19.90', '8000', [null]],
            ['Boucles d’oreilles — démo', 'boucles-oreilles', 'bijoux', '24.90', '10000', [null]],
        ];
    }
}
