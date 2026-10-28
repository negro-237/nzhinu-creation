<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CatalogTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'vetements' => ['Vêtements', 'clothing', 'Des tenues et des imprimés pour exprimer votre style au quotidien comme lors de vos occasions.'],
            'accessoires' => ['Accessoires', 'bag', 'Sacs, pochettes et foulards : les détails qui complètent votre silhouette.'],
            'bijoux' => ['Bijoux', 'jewelry', 'Colliers, bracelets et boucles d’oreilles pour apporter une touche personnelle à chaque tenue.'],
        ] as $slug => [$name, $icon, $description]) {
            $category = Category::withTrashed()->firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true,
                'icon' => $icon, 'description' => $description]);
            // Enrich legacy categories once; preserve names, text, chosen icons, deactivation and archival.
            if (! $category->trashed() && $category->is_active && $category->icon === null) {
                Category::whereKey($category->id)->whereNull('icon')->update([
                    'icon' => $icon, 'description' => $category->description ?? $description,
                ]);
            }
        }
    }
}
