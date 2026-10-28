<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\ListCatalogRecords;

class ListProducts extends ListCatalogRecords
{
    protected static string $resource = ProductResource::class;
}
