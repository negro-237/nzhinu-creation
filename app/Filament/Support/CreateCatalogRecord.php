<?php

namespace App\Filament\Support;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

abstract class CreateCatalogRecord extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        return static::getResource()::saveRecord($data, rawState: $this->data ?? []);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
