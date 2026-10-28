<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\EditCatalogRecord;
use App\Services\ProductMediaService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class EditProduct extends EditCatalogRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addImage')->label('Ajouter une image')
                ->schema([
                    FileUpload::make('image')->label('Image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)->storeFiles(false)->required(),
                    TextInput::make('alt')->label('Description alternative')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    app(ProductMediaService::class)->add(auth()->user(), $this->record, $data['image'], $data['alt']);
                    $this->record->unsetRelation('media');
                }),
            Action::make('removeImage')->label('Supprimer une image')->color('danger')->requiresConfirmation()
                ->schema([
                    Select::make('media_id')->label('Image')->options(fn () => $this->record->getMedia('images')
                        ->mapWithKeys(fn ($media) => [$media->id => $media->getCustomProperty('alt').' ('.$media->id.')']))->required(),
                ])
                ->action(function (array $data): void {
                    app(ProductMediaService::class)->remove(auth()->user(), $this->record, (int) $data['media_id']);
                    $this->record->unsetRelation('media');
                }),
            ...parent::getHeaderActions(),
        ];
    }
}
