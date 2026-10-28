<?php

namespace App\Filament\Resources;

use App\Filament\Support\InventoryActions;
use App\Models\CmsPage;
use App\Models\Market;
use App\Services\ContentService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
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

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static ?string $modelLabel = 'page';

    protected static ?string $pluralModelLabel = 'pages publiques';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenu';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('version')->default(0), TextInput::make('title')->label('Titre')->required()->maxLength(160),
            TextInput::make('slug')->label('Identifiant URL')->required()->maxLength(120),
            Select::make('market_id')->label('Marché (vide = les deux)')->options(fn () => Market::pluck('name', 'id')),
            Textarea::make('body')->label('Contenu en texte simple')->helperText('Les balises sont affichées comme du texte. Aucun HTML ni script ne sera exécuté.')->required()->maxLength(20000)->rows(12)->columnSpanFull(),
            Textarea::make('meta_description')->label('Description pour les moteurs de recherche')->required()->maxLength(255),
            Toggle::make('is_published')->label('Publié')->default(false),
        ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->using(fn (array $data, Action $action) => InventoryActions::run($action, fn () => app(ContentService::class)->savePage(auth()->user(), $data)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label('Titre')->searchable(), TextColumn::make('slug')->label('URL'),
            TextColumn::make('market_id')->label('Marché # (vide = tous)'), IconColumn::make('is_published')->label('Publié')->boolean()])
            ->recordActions([
                EditAction::make()->using(fn (CmsPage $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ContentService::class)->savePage(auth()->user(), $data, $record->id))),
                Action::make('archive')->label('Archiver')->visible(fn (CmsPage $record) => auth()->user()->can('update', $record))->requiresConfirmation()
                    ->schema([Hidden::make('version')->default(fn (CmsPage $record) => $record->version)])
                    ->action(fn (CmsPage $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ContentService::class)->archive(auth()->user(), $record, (int) $data['version']))),
            ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => CmsPageResource\Pages\ManagePages::route('/')];
    }
}
