<?php

namespace App\Filament\Resources\InventoryReservationResource\Pages;

use App\Filament\Resources\InventoryReservationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReservations extends ManageRecords
{
    protected static string $resource = InventoryReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [InventoryReservationResource::createAction()];
    }
}
