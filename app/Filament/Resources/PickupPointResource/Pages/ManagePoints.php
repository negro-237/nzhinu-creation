<?php

namespace App\Filament\Resources\PickupPointResource\Pages;

use App\Filament\Resources\PickupPointResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePoints extends ManageRecords
{
    protected static string $resource = PickupPointResource::class;

    protected function getHeaderActions(): array
    {
        return [static::$resource::createAction()];
    }
}
