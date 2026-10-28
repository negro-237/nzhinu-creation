<?php

namespace App\Filament\Resources;

use App\Filament\Support\FulfillmentOptionResource;
use App\Models\DeliveryZone;

class DeliveryZoneResource extends FulfillmentOptionResource
{
    protected static ?string $model = DeliveryZone::class;

    protected static ?string $modelLabel = 'zone de livraison';

    protected static ?string $pluralModelLabel = 'zones de livraison';

    public static function getPages(): array
    {
        return ['index' => DeliveryZoneResource\Pages\ManageZones::route('/')];
    }
}
