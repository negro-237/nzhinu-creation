<?php

namespace App\Filament\Resources;

use App\Filament\Support\InventoryActions;
use App\Models\Market;
use App\Models\StoreBanner;
use App\Services\ContentService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoreBannerResource extends Resource
{
    protected static ?string $model = StoreBanner::class;

    protected static ?string $modelLabel = 'bannière';

    protected static ?string $pluralModelLabel = 'bannières d’accueil';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenu';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('version')->default(0), TextInput::make('title')->label('Titre')->required()->maxLength(160),
            Select::make('market_id')->label('Marché (vide = les deux)')->options(fn () => Market::pluck('name', 'id')),
            Textarea::make('body')->label('Message en texte simple')->required()->maxLength(2000)->columnSpanFull(),
            TextInput::make('link_path')->label('Lien interne facultatif')->helperText('/boutique, /pages/identifiant ou /produits/identifiant')->maxLength(255),
            TextInput::make('link_label')->label('Libellé du lien')->maxLength(80),
            DateTimePicker::make('starts_at')->label('Début (UTC)')->seconds(false), DateTimePicker::make('ends_at')->label('Fin (UTC)')->seconds(false),
            TextInput::make('sort_order')->label('Ordre')->integer()->minValue(0)->maxValue(10000)->default(0)->required(), Toggle::make('is_published')->label('Publié')->default(false),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data, Action $action) => InventoryActions::run($action, fn () => app(ContentService::class)->saveBanner(auth()->user(), $data)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label('Titre')->searchable(), TextColumn::make('market_id')->label('Marché #'),
            IconColumn::make('is_published')->label('Publié')->boolean(), TextColumn::make('starts_at')->label('Début')->dateTime(), TextColumn::make('ends_at')->label('Fin')->dateTime()])
            ->recordActions([
                EditAction::make()->using(fn (StoreBanner $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ContentService::class)->saveBanner(auth()->user(), $data, $record->id))),
                Action::make('archive')->label('Archiver')->visible(fn (StoreBanner $record) => auth()->user()->can('update', $record))->requiresConfirmation()
                    ->schema([Hidden::make('version')->default(fn (StoreBanner $record) => $record->version)])
                    ->action(fn (StoreBanner $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ContentService::class)->archive(auth()->user(), $record, (int) $data['version']))),
            ])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => StoreBannerResource\Pages\ManageBanners::route('/')];
    }
}
