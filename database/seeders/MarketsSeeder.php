<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\Market;
use App\Models\StockLocation;
use Illuminate\Database\Seeder;

class MarketsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['CA', 'Canada', Currency::CAD], ['CM', 'Cameroun', Currency::XAF]] as [$code, $name, $currency]) {
            $market = Market::firstOrCreate(['code' => $code], ['name' => $name, 'currency' => $currency, 'is_active' => in_array($code, config('storefront.markets'), true)]);

            StockLocation::withTrashed()->firstOrCreate(['code' => $code.'-EXAMPLE'], [
                'market_id' => $market->id,
                'name' => $name.' — dépôt exemple',
                'address' => 'ADRESSE FICTIVE — à remplacer avant activation',
                'is_placeholder' => true,
                'is_active' => false,
            ]);
        }
    }
}
