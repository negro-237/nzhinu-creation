<?php

namespace App\Filament\Resources;

use App\Enums\ReservationStatus;
use App\Enums\StaffPermission;
use App\Filament\Support\InventoryActions;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Market;
use App\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class InventoryReservationResource extends Resource
{
    protected static ?string $model = InventoryReservation::class;

    protected static ?string $modelLabel = 'réservation';

    protected static ?string $pluralModelLabel = 'réservations';

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    public static function canCreate(): bool
    {
        return auth()->user()->can(StaffPermission::ManageReservations->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('request_key')->default(fn () => (string) Str::uuid()),
            Select::make('market_id')->label('Marché')->options(fn () => Market::where('is_active', true)->pluck('name', 'id'))->required(),
            Repeater::make('lines')->label('Stocks à réserver')->minItems(1)->maxItems(100)->schema([
                Select::make('inventory_id')->label('Lieu et SKU')->options(fn () => Inventory::with(['location', 'variant'])->get()
                    ->mapWithKeys(fn ($stock) => [$stock->id => $stock->location->name.' / '.$stock->variant->sku.' — '.$stock->available.' disponible(s)']))->required()->searchable(),
                TextInput::make('quantity')->label('Quantité')->integer()->minValue(1)->required(),
            ])->reorderable(false),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data, Action $action) => InventoryActions::run($action,
            fn () => app(ReservationService::class)->reserve(auth()->user(), (int) $data['market_id'], $data['lines'], $data['request_key'])));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->label('Référence')->searchable()->copyable(),
            TextColumn::make('market.code')->label('Marché'),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('cycle')->label('Cycle'),
            TextColumn::make('expires_at')->label('Échéance')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([SelectFilter::make('status')->label('Statut')->options(ReservationStatus::class)])
            ->recordActions([
                ViewAction::make()->schema([
                    ViewField::make('details')->view('filament.inventory.reservation')->dehydrated(false),
                ]),
                Action::make('release')->label('Libérer')->requiresConfirmation()
                    ->visible(fn (InventoryReservation $record) => static::canCreate() && $record->status === ReservationStatus::Active)
                    ->schema([
                        Hidden::make('cycle')->default(fn (InventoryReservation $record) => $record->cycle),
                        Textarea::make('reason')->label('Motif')->required()->minLength(3)->maxLength(1000),
                    ])
                    ->action(fn (InventoryReservation $record, array $data, Action $action) => InventoryActions::run($action,
                        fn () => app(ReservationService::class)->release(auth()->user(), $record->id, $data['reason'], (int) $data['cycle']))),
                Action::make('reactivate')->label('Réactiver')->requiresConfirmation()
                    ->visible(fn (InventoryReservation $record) => static::canCreate() && in_array($record->status, [ReservationStatus::Released, ReservationStatus::Expired], true))
                    ->schema([Hidden::make('request_key')->default(fn () => (string) Str::uuid())])
                    ->action(fn (InventoryReservation $record, array $data, Action $action) => InventoryActions::run($action,
                        fn () => app(ReservationService::class)->reactivate(auth()->user(), $record->id, $data['request_key']))),
            ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => InventoryReservationResource\Pages\ManageReservations::route('/')];
    }
}
