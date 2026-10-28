<?php

namespace App\Filament\Resources\StockLocationResource\Pages;

use App\Filament\Resources\StockLocationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStockLocations extends ManageRecords
{
    protected static string $resource = StockLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [StockLocationResource::createAction()];
    }
}
