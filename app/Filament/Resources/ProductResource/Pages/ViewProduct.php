<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\ViewCatalogRecord;

class ViewProduct extends ViewCatalogRecord
{
    protected static string $resource = ProductResource::class;
}
