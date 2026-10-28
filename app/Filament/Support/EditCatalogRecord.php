<?php

namespace App\Filament\Support;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

abstract class EditCatalogRecord extends EditRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return static::getResource()::recordData($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->record = static::getResource()::saveRecord($data, $record, $this->data ?? []);
    }

    protected function afterSave(): void
    {
        $this->form->fill(static::getResource()::recordData($this->record));
    }

    protected function getHeaderActions(): array
    {
        return [static::getResource()::archiveAction()];
    }
}
