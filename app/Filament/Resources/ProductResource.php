<?php

namespace App\Filament\Resources;

use App\Enums\ProductStatus;
use App\Filament\Support\CatalogResource;
use App\Models\Category;
use App\Models\CulturalCollection;
use App\Models\Market;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends CatalogResource
{
    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'produit';

    protected static ?string $pluralModelLabel = 'produits';

    public static function marketOptions(): array
    {
        return Market::where('is_active', true)->orderBy('code')->get()
            ->mapWithKeys(fn ($market) => [$market->id => $market->name.' — '.$market->currency->value])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('version')->default(0),
            Section::make('Présentation')->schema([
                TextInput::make('name')->label('Nom')->required()->maxLength(255),
                TextInput::make('slug')->label('Identifiant URL')->required()->maxLength(255),
                Select::make('category_id')->label('Catégorie')->options(fn () => Category::where('is_active', true)->orderBy('name')->pluck('name', 'id'))->required()->searchable(),
                Select::make('status')->label('Statut')->options(ProductStatus::class)->default('draft')->required(),
                Select::make('collections')->label('Collections culturelles')->multiple()->options(fn () => CulturalCollection::where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->default([]),
                Textarea::make('description')->label('Description')->maxLength(20000)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Variantes et prix')->description('Un SKU unique par variante. CAD : dollars, au plus 2 décimales ; XAF : francs entiers. Aucun calcul de stock dans cet écran.')
                ->schema([
                    Repeater::make('variants')->label('Variantes')->minItems(1)->maxItems(100)->defaultItems(1)->required()
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('sku')->label('SKU')->required()->maxLength(100),
                            TextInput::make('label')->label('Libellé')->default('Standard')->required()->maxLength(100),
                            TextInput::make('size')->label('Taille')->maxLength(50),
                            TextInput::make('color')->label('Couleur')->maxLength(100),
                            Toggle::make('is_active')->label('Variante active')->default(true),
                            Repeater::make('prices')->label('Prix par marché')->default([])->maxItems(2)->schema([
                                Select::make('market_id')->label('Marché et devise')->options(fn () => static::marketOptions())->required(),
                                TextInput::make('amount')->label('Prix dans la devise du marché')->required()->maxLength(20)->inputMode('decimal')->helperText('Exemples : 49,95 CAD ou 25000 XAF.'),
                            ])->columns(2)->columnSpanFull(),
                        ])->columns(2)->reorderable(false)->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                ])->columnSpanFull(),
            Section::make('Publication par marché')->description('Chaque variante active doit avoir un prix pour les marchés activés. Un produit en brouillon reste invisible.')
                ->schema([
                    Repeater::make('markets')->label('Marchés')->default([])->maxItems(2)->schema([
                        Select::make('market_id')->label('Marché')->options(fn () => static::marketOptions())->required(),
                        Toggle::make('is_available')->label('Disponible au catalogue')->default(false),
                    ])->columns(2)->reorderable(false),
                ])->columnSpanFull(),
            Section::make('Images')->schema([
                ViewField::make('gallery')->view('filament.products.gallery')->dehydrated(false),
            ])->visible(fn (?Product $record) => $record !== null)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nom')->searchable()->sortable(),
            TextColumn::make('category.name')->label('Catégorie')->sortable(),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('variants_count')->counts('variants')->label('Variantes'),
            TextColumn::make('updated_at')->label('Modifié le')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->label('Statut')->options(ProductStatus::class),
            SelectFilter::make('category_id')->label('Catégorie')->relationship('category', 'name'),
        ])->recordActions([ViewAction::make(), EditAction::make(), static::archiveAction()])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ProductResource\Pages\ListProducts::route('/'),
            'create' => ProductResource\Pages\CreateProduct::route('/create'),
            'view' => ProductResource\Pages\ViewProduct::route('/{record}'),
            'edit' => ProductResource\Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
