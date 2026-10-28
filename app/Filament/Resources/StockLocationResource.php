<?php

namespace App\Filament\Resources;

use App\Filament\Support\InventoryActions;
use App\Models\Market;
use App\Models\StockLocation;
use App\Services\StockLocationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StockLocationResource extends Resource
{
    protected static ?string $model = StockLocation::class;

    protected static ?string $modelLabel = 'lieu de stockage';

    protected static ?string $pluralModelLabel = 'lieux de stockage';

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('market_id')->label('Marché')->options(fn () => Market::pluck('name', 'id'))->required(),
            TextInput::make('code')->label('Code')->required()->maxLength(50),
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            Textarea::make('address')->label('Adresse réelle')->maxLength(1000),
            Toggle::make('is_placeholder')->label('Adresse fictive / à valider')->default(true),
            Toggle::make('is_active')->label('Actif pour les réservations')->default(false),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data, Action $action) => InventoryActions::run($action,
            fn () => app(StockLocationService::class)->save(auth()->user(), $data)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Code')->searchable(),
            TextColumn::make('name')->label('Nom')->searchable(),
            TextColumn::make('market.name')->label('Marché'),
            IconColumn::make('is_active')->label('Actif')->boolean(),
            IconColumn::make('is_placeholder')->label('Fictif')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (StockLocation $record, array $data, Action $action) => InventoryActions::run($action,
                fn () => app(StockLocationService::class)->save(auth()->user(), $data, $record))),
            DeleteAction::make()->label('Archiver')->using(fn (StockLocation $record, Action $action) => InventoryActions::run($action,
                fn () => app(StockLocationService::class)->archive(auth()->user(), $record))),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => StockLocationResource\Pages\ManageStockLocations::route('/')];
    }
}
