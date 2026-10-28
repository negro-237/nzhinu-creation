<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Support\ListCatalogRecords;

class ListCategories extends ListCatalogRecords
{
    protected static string $resource = CategoryResource::class;
}
