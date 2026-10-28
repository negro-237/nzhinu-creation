<?php

namespace App\Filament\Resources;

use App\Filament\Support\FulfillmentOptionResource;
use App\Models\PickupPoint;

class PickupPointResource extends FulfillmentOptionResource
{
    protected static ?string $model = PickupPoint::class;

    protected static ?string $modelLabel = 'point de retrait';

    protected static ?string $pluralModelLabel = 'points de retrait';

    public static function getPages(): array
    {
        return ['index' => PickupPointResource\Pages\ManagePoints::route('/')];
    }
}
