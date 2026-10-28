<?php

namespace Database\Seeders;

use App\Enums\StaffPermission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DemoProductImagesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Les images de démonstration sont réservées aux environnements local et testing.');
        }
        $actor = User::permission(StaffPermission::ManageCatalog->value)->orderBy('id')->first();
        if (! $actor) {
            throw new \LogicException('Un compte existant autorisé à gérer le catalogue est requis.');
        }
        foreach ($this->slugs() as $index => $slug) {
            $number = sprintf('%02d', $index + 1);
            foreach (['front', 'detail'] as $view) {
                if (! is_file(public_path("images/demo/{$number}-{$view}.webp"))) {
                    throw new \LogicException("Visuel de démonstration manquant : {$number}-{$view}.webp");
                }
            }
        }
        foreach ($this->slugs() as $index => $slug) {
            $number = sprintf('%02d', $index + 1);
            DB::transaction(function () use ($actor, $slug, $number) {
                $product = Product::where('slug', 'demo-'.$slug)
                    ->whereHas('variants', fn ($query) => $query->where('sku', 'like', "DEMO-NZ-{$number}-%"))
                    ->lockForUpdate()->first();
                if (! $product) {
                    return;
                }
                Gate::forUser($actor)->authorize('update', $product);
                $media = $product->getMedia('images');
                // Never supplement or replace staff-provided photos with generated demonstration images.
                if ($media->contains(fn ($image) => ! $image->getCustomProperty('demo_asset'))) {
                    return;
                }
                foreach (['front' => 'vue d’ensemble', 'detail' => 'vue de détail'] as $view => $label) {
                    $key = "{$number}-{$view}";
                    if ($media->contains(fn ($image) => $image->getCustomProperty('demo_asset') === $key)) {
                        continue;
                    }
                    $product->addMedia(public_path("images/demo/{$key}.webp"))->preservingOriginal()
                        ->withCustomProperties(['alt' => $product->name.' — '.$label.' (illustration de démonstration)', 'demo_asset' => $key])
                        ->toMediaCollection('images');
                }
            });
        }
    }

    private function slugs(): array
    {
        return ['robe-wax', 'chemise-motifs', 'ensemble-deux-pieces', 'jupe-portefeuille', 'tunique-unisexe', 'veste-imprimes',
            'sac-tissu', 'pochette-soiree', 'foulard-imprime', 'collier-perles', 'bracelet-perles', 'boucles-oreilles'];
    }
}
