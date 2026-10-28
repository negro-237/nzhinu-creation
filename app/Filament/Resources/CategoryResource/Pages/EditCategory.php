<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Support\EditCatalogRecord;

class EditCategory extends EditCatalogRecord
{
    protected static string $resource = CategoryResource::class;
}
