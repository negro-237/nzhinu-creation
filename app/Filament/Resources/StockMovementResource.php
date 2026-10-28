<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $pluralModelLabel = 'journal des mouvements';

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
            TextColumn::make('inventory.location.name')->label('Lieu'),
            TextColumn::make('inventory.variant.sku')->label('SKU')->searchable(),
            TextColumn::make('type')->label('Opération')->badge(),
            TextColumn::make('quantity_delta')->label('Δ physique'),
            TextColumn::make('reserved_delta')->label('Δ réservé'),
            TextColumn::make('on_hand_after')->label('Physique après'),
            TextColumn::make('reserved_after')->label('Réservé après'),
            TextColumn::make('actor_id')->label('Personnel ID'),
            TextColumn::make('reason')->label('Motif')->wrap(),
        ])->filters([SelectFilter::make('type')->label('Opération')->options(StockMovementType::class)])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => StockMovementResource\Pages\ListMovements::route('/')];
    }
}
