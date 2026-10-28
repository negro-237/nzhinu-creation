<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use App\Services\TaxonomyService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

abstract class CatalogResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $recordTitleAttribute = 'name';

    public static function saveRecord(array $data, ?Model $record = null, array $rawState = []): Model
    {
        try {
            return static::getModel() === Product::class
                ? app(CatalogService::class)->save(auth()->user(), $data, $record)
                : app(TaxonomyService::class)->save(auth()->user(), static::getModel(), $data, $record);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $state = $rawState;
                $path = [];
                foreach (explode('.', $field) as $segment) {
                    // Repeater values are dehydrated as lists but Livewire uses UUID keys.
                    if (is_array($state) && ! array_key_exists($segment, $state) && ctype_digit($segment)) {
                        $segment = (string) (array_keys($state)[(int) $segment] ?? $segment);
                    }
                    $path[] = $segment;
                    $state = is_array($state) ? ($state[$segment] ?? null) : null;
                }
                $errors['data.'.implode('.', $path)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    public static function recordData(Model $record): array
    {
        return $record instanceof Product
            ? app(CatalogService::class)->formData($record)
            : $record->only(['name', 'slug', 'description', 'is_active', ...($record instanceof Category ? ['icon'] : [])]);
    }

    public static function archiveAction(): DeleteAction
    {
        return DeleteAction::make()->label('Archiver')->modalHeading('Archiver cet élément ?')
            ->modalDescription('Les références et les données historiques seront conservées.')
            ->using(fn (Model $record): bool => $record instanceof Product
                ? app(CatalogService::class)->archive(auth()->user(), $record)
                : app(TaxonomyService::class)->archive(auth()->user(), $record));
    }
}
