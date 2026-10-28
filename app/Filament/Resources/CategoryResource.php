<?php

namespace App\Filament\Resources;

use App\Filament\Support\TaxonomyResource;
use App\Models\Category;

class CategoryResource extends TaxonomyResource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'catégorie';

    protected static ?string $pluralModelLabel = 'catégories';

    public static function getPages(): array
    {
        return [
            'index' => CategoryResource\Pages\ListCategories::route('/'),
            'create' => CategoryResource\Pages\CreateCategory::route('/create'),
            'view' => CategoryResource\Pages\ViewCategory::route('/{record}'),
            'edit' => CategoryResource\Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
