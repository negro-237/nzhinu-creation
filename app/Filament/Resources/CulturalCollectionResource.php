<?php

namespace App\Filament\Resources;

use App\Filament\Support\TaxonomyResource;
use App\Models\CulturalCollection;

class CulturalCollectionResource extends TaxonomyResource
{
    protected static ?string $model = CulturalCollection::class;

    protected static ?string $modelLabel = 'collection culturelle';

    protected static ?string $pluralModelLabel = 'collections culturelles';

    public static function getPages(): array
    {
        return [
            'index' => CulturalCollectionResource\Pages\ListCulturalCollections::route('/'),
            'create' => CulturalCollectionResource\Pages\CreateCulturalCollection::route('/create'),
            'view' => CulturalCollectionResource\Pages\ViewCulturalCollection::route('/{record}'),
            'edit' => CulturalCollectionResource\Pages\EditCulturalCollection::route('/{record}/edit'),
        ];
    }
}
