<?php

namespace App\Filament\Support;

use App\Models\DeliveryZone;
use App\Models\Market;
use App\Models\StockLocation;
use App\Services\DeliveryOptions;
use App\Support\MoneyInput;
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
use Illuminate\Database\Eloquent\Model;

abstract class FulfillmentOptionResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Commandes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('market_id')->label('Marché')->options(fn () => Market::pluck('name', 'id'))->required(),
            TextInput::make('name')->label('Nom public')->required()->maxLength(255),
            ...(static::getModel() === DeliveryZone::class ? [TextInput::make('estimate')->label('Frais estimés (devise du marché)')->helperText('Facultatif. Les frais définitifs seront confirmés par téléphone.')]
                : [Select::make('stock_location_id')->label('Dépôt associé')->options(fn () => StockLocation::where('is_active', true)->where('is_placeholder', false)->pluck('name', 'id'))->required(), Textarea::make('address')->label('Adresse publique réelle')->required()->maxLength(1000)]),
            Toggle::make('is_active')->label('Disponible aux clients')->default(false),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data, Action $action) => InventoryActions::run($action,
            fn () => app(DeliveryOptions::class)->save(auth()->user(), static::getModel(), $data)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Nom')->searchable(), TextColumn::make('market.name')->label('Marché'), IconColumn::make('is_active')->label('Actif')->boolean()])
            ->recordActions([
                EditAction::make()->mutateRecordDataUsing(function (array $data, Model $record) {
                    if ($record instanceof DeliveryZone) {
                        $data['estimate'] = $record->estimated_fee_minor === null ? null : MoneyInput::fromMinor($record->estimated_fee_minor, $record->market->currency);
                    }

                    return $data;
                })->using(fn (Model $record, array $data, Action $action) => InventoryActions::run($action,
                    fn () => app(DeliveryOptions::class)->save(auth()->user(), static::getModel(), $data, $record))),
                DeleteAction::make()->label('Archiver')->using(fn (Model $record) => app(DeliveryOptions::class)->archive(auth()->user(), $record)),
            ]);
    }
}
