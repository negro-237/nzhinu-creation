<?php

namespace App\Filament\Support;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class ViewCatalogRecord extends ViewRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return static::getResource()::recordData($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
