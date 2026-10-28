<?php

namespace App\Filament\Resources;

use App\Enums\StaffPermission;
use App\Filament\Support\InventoryActions;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $modelLabel = 'stock';

    protected static ?string $pluralModelLabel = 'stocks physiques';

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('request_key')->default(fn () => (string) Str::uuid()),
            Select::make('stock_location_id')->label('Lieu')->options(fn () => StockLocation::where('is_placeholder', false)->pluck('name', 'id'))->required()->searchable(),
            Select::make('product_variant_id')->label('Variante / SKU')->options(fn () => ProductVariant::pluck('sku', 'id'))->required()->searchable(),
            TextInput::make('quantity_on_hand')->label('Quantité physique initiale')->integer()->minValue(0)->default(0)->required(),
            TextInput::make('low_stock_threshold')->label('Seuil d’alerte')->integer()->minValue(0)->default(2)->required(),
            Textarea::make('reason')->label('Motif de l’initialisation')->required()->minLength(3)->maxLength(1000),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data, Action $action) => InventoryActions::run($action,
            fn () => app(InventoryService::class)->create(auth()->user(), $data)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('location.market.code')->label('Marché'),
            TextColumn::make('location.name')->label('Lieu')->searchable(),
            TextColumn::make('variant.sku')->label('SKU')->searchable(),
            TextColumn::make('quantity_on_hand')->label('Physique')->sortable(),
            TextColumn::make('quantity_reserved')->label('Réservé')->sortable(),
            TextColumn::make('available')->label('Disponible')->badge()->color(fn (Inventory $record) => $record->available <= $record->low_stock_threshold ? 'danger' : 'success'),
            TextColumn::make('low_stock_threshold')->label('Seuil'),
        ])->filters([
            SelectFilter::make('stock_location_id')->label('Lieu')->relationship('location', 'name'),
            Filter::make('low')->label('Stock faible')->query(fn ($query) => $query->whereRaw('quantity_on_hand - quantity_reserved <= low_stock_threshold')),
        ])->recordActions([
            Action::make('adjust')->label('Réception / correction')->visible(fn () => auth()->user()->can(StaffPermission::ManageInventory->value))
                ->modalDescription('Réception ou correction de comptage uniquement. La remise au client sera gérée après vérification du paiement.')
                ->schema([
                    Hidden::make('request_key')->default(fn () => (string) Str::uuid()),
                    TextInput::make('quantity_delta')->label('Variation physique (+ / −)')->integer()->required(),
                    TextInput::make('low_stock_threshold')->label('Nouveau seuil (facultatif)')->integer()->minValue(0),
                    Textarea::make('reason')->label('Motif')->required()->minLength(3)->maxLength(1000),
                ])
                ->action(fn (Inventory $record, array $data, Action $action) => InventoryActions::run($action,
                    fn () => app(InventoryService::class)->adjust(auth()->user(), $record->id, $data))),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => InventoryResource\Pages\ManageInventories::route('/')];
    }
}
