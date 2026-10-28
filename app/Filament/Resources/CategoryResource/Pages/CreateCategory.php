<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Support\CreateCatalogRecord;

class CreateCategory extends CreateCatalogRecord
{
    protected static string $resource = CategoryResource::class;
}
