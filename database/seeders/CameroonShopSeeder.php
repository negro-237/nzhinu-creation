<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\Market;
use App\Models\PickupPoint;
use App\Models\StockLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CameroonShopSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $market = Market::where('code', 'CM')->lockForUpdate()->firstOrFail();
            if ($market->currency !== Currency::XAF) {
                throw new \LogicException('Le marché camerounais doit utiliser le XAF.');
            }
            if (! $market->is_active) {
                return;
            }
            $location = StockLocation::withTrashed()->firstOrCreate(['code' => 'CM-DOUALA-CHACHA223'], [
                'market_id' => $market->id, 'name' => config('storefront.boutique.name'),
                'address' => config('storefront.boutique.address'), 'is_placeholder' => false, 'is_active' => true,
            ]);
            if ($location->market_id !== $market->id) {
                throw new \LogicException('Le code de la boutique est déjà utilisé dans un autre marché.');
            }
            // Preserve staff edits, deactivation and archival on repeated deployments.
            if ($location->trashed() || ! $location->is_active || $location->is_placeholder) {
                return;
            }
            PickupPoint::withTrashed()->firstOrCreate(['market_id' => $market->id, 'stock_location_id' => $location->id], [
                'name' => config('storefront.boutique.name'), 'address' => config('storefront.boutique.address'), 'is_active' => true,
            ]);
        });
    }
}
