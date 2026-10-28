<?php

namespace App\Filament\Support;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

abstract class ListCatalogRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
