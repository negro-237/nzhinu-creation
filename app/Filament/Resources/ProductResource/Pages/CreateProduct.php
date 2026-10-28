<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\CreateCatalogRecord;

class CreateProduct extends CreateCatalogRecord
{
    protected static string $resource = ProductResource::class;
}
