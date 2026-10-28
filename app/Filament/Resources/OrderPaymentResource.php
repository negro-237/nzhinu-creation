<?php

namespace App\Filament\Resources;

use App\Enums\PaymentEntryStatus;
use App\Enums\PaymentKind;
use App\Enums\StaffPermission;
use App\Filament\Support\InventoryActions;
use App\Models\OrderPayment;
use App\Services\ManualPaymentService;
use App\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrderPaymentResource extends Resource
{
    protected static ?string $model = OrderPayment::class;

    protected static ?string $modelLabel = 'paiement manuel';

    protected static ?string $pluralModelLabel = 'paiements et remboursements';

    protected static string|\UnitEnum|null $navigationGroup = 'Commandes';

    public static function table(Table $table): Table
    {
        $allowed = fn (OrderPayment $record) => auth()->user()->can(StaffPermission::VerifyPayments->value)
            && ($record->kind !== PaymentKind::Refund || auth()->user()->can(StaffPermission::ManageRefunds->value)) && $record->status === PaymentEntryStatus::Pending;

        return $table->columns([
            TextColumn::make('order.number')->label('Commande')->searchable(), TextColumn::make('kind')->label('Type')->badge(),
            TextColumn::make('amount_minor')->label('Montant')->formatStateUsing(fn ($state, OrderPayment $record) => MoneyInput::fromMinor($state, $record->currency)),
            TextColumn::make('currency')->label('Devise'), TextColumn::make('method')->label('Moyen'), TextColumn::make('reference')->label('Référence')->searchable(),
            TextColumn::make('status')->label('Vérification')->badge(), TextColumn::make('recorded_by')->label('Déclarant #'),
            TextColumn::make('verified_by')->label('Vérificateur #'), TextColumn::make('verification_source')->label('Source'),
            TextColumn::make('note')->label('Note')->wrap()->limit(80), TextColumn::make('verification_note')->label('Vérification')->wrap()->limit(80),
            TextColumn::make('rejection_reason')->label('Motif du rejet')->wrap()->limit(80),
        ])->filters([SelectFilter::make('status')->label('Vérification')->options(PaymentEntryStatus::class), SelectFilter::make('kind')->label('Type')->options(PaymentKind::class)])
            ->recordActions([
                Action::make('verify')->label('Vérifier')->visible($allowed)->requiresConfirmation()
                    ->modalDescription('Confirmez le mouvement réellement constaté auprès de la banque, du fournisseur ou dans la caisse. Une capture fournie par le client ne suffit pas. Aucun transfert d’argent ne sera déclenché.')
                    ->schema([Hidden::make('request_key')->default(fn () => (string) Str::uuid()),
                        Select::make('source')->label('Source indépendante')->options(['bank_statement' => 'Relevé bancaire', 'provider_record' => 'Relevé du fournisseur de paiement', 'cash_count' => 'Comptage de la caisse'])->required(),
                        Textarea::make('note')->label('Résultat du contrôle')->required()->minLength(10)->maxLength(1000)])
                    ->action(fn (OrderPayment $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ManualPaymentService::class)->verify(auth()->user(), $record->id, $data['request_key'], $data))),
                Action::make('reject')->label('Rejeter')->visible($allowed)->requiresConfirmation()
                    ->schema([Hidden::make('request_key')->default(fn () => (string) Str::uuid()), Textarea::make('reason')->label('Motif du rejet')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (OrderPayment $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ManualPaymentService::class)->reject(auth()->user(), $record->id, $data['request_key'], $data['reason']))),
            ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => OrderPaymentResource\Pages\ManagePayments::route('/')];
    }
}
