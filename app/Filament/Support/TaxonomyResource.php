<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Services\UrlSlugService;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

abstract class TaxonomyResource extends CatalogResource
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255)
                ->live(debounce: 400)
                ->afterStateUpdated(function (?string $state, Set $set, ?Model $record) {
                    if ($record === null) {
                        $set('slug', app(UrlSlugService::class)->generate($state ?? '', (new (static::getModel()))->getTable(), 255));
                    }
                }),
            TextInput::make('slug')->label('Identifiant URL')->readOnly()->maxLength(255)
                ->dehydrateStateUsing(fn (Get $get, ?Model $record): string => $record?->slug
                    ?? app(UrlSlugService::class)->generate((string) $get('name'), (new (static::getModel()))->getTable(), 255))->helperText('Généré automatiquement à partir du nom. Conservé après création.'),
            Textarea::make('description')->label('Description')->maxLength(10000)->columnSpanFull(),
            ...(static::getModel() === Category::class ? [Select::make('icon')->label('Icône illustrative')
                ->options(config('catalog.category_icons'))->default('grid')->placeholder('Icône générale')
                ->helperText('Affichée sur la page d’accueil. La description reste facultative.')] : []),
            Toggle::make('is_active')->label('Actif')->default(true)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nom')->searchable()->sortable(),
            TextColumn::make('slug')->label('Identifiant URL')->searchable(),
            IconColumn::make('is_active')->label('Actif')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Actif')])
            ->recordActions([ViewAction::make(), EditAction::make(), static::archiveAction()])
            ->defaultSort('name');
    }
}
