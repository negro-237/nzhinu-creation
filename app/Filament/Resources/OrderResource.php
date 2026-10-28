<?php

namespace App\Filament\Resources;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StaffPermission;
use App\Filament\Support\InventoryActions;
use App\Models\Order;
use App\Services\FulfillmentService;
use App\Services\ManualPaymentService;
use App\Services\OrderWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'commande';

    protected static ?string $pluralModelLabel = 'commandes invitées';

    protected static string|\UnitEnum|null $navigationGroup = 'Commandes';

    public static function table(Table $table): Table
    {
        $allowed = fn () => auth()->user()->can(StaffPermission::ManageOrders->value);

        return $table->columns([
            TextColumn::make('number')->label('Numéro')->searchable(), TextColumn::make('market.code')->label('Marché'),
            TextColumn::make('status')->label('Commande')->badge(), TextColumn::make('payment_status')->label('Paiement')->badge(),
            TextColumn::make('fulfillment_status')->label('Remise')->badge(), TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i')->sortable(),
            IconColumn::make('financial_review_required')->label('Revue financière')->boolean(),
        ])->filters([SelectFilter::make('status')->label('Statut')->options(OrderStatus::class),
            SelectFilter::make('payment_status')->label('Paiement')->options(PaymentStatus::class),
            SelectFilter::make('fulfillment_status')->label('Remise')->options(FulfillmentStatus::class)])
            ->recordActions([
                ViewAction::make()->schema([ViewField::make('details')->view('filament.orders.details')->dehydrated(false)]),
                self::paymentAction(false), self::paymentAction(true),
                Action::make('pack')->label('Préparer')->visible(fn (Order $record) => auth()->user()->can(StaffPermission::FulfillOrders->value) && $record->status === OrderStatus::Confirmed && $record->fulfillment_status === FulfillmentStatus::Unfulfilled)
                    ->schema([Hidden::make('request_key')->default(fn () => (string) Str::uuid()), Textarea::make('note')->label('Contrôle de la préparation')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (Order $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(FulfillmentService::class)->pack(auth()->user(), $record->id, $data['request_key'], $data['note']))),
                Action::make('handover')->label('Expédier ou remettre')->requiresConfirmation()
                    ->visible(fn (Order $record) => auth()->user()->can(StaffPermission::FulfillOrders->value) && $record->status === OrderStatus::Confirmed && $record->fulfillment_status === FulfillmentStatus::Packed)
                    ->schema([Hidden::make('request_key')->default(fn () => (string) Str::uuid()),
                        Select::make('method')->label('Mode de remise')->options(['delivery' => 'Expédition', 'pickup' => 'Retrait'])->default(fn (Order $record) => $record->method->value)->required(),
                        TextInput::make('carrier')->label('Transporteur (livraison)')->maxLength(120),
                        TextInput::make('reference')->label('Numéro de suivi ou reçu de retrait')->required()->minLength(3)->maxLength(120),
                        Textarea::make('note')->label('Compte rendu de la remise')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (Order $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(FulfillmentService::class)->handover(auth()->user(), $record->id, $data['request_key'], $data))),
                Action::make('confirm')->label('Appel confirmé')->visible(fn (Order $record) => $allowed() && $record->status === OrderStatus::Pending)
                    ->schema([Hidden::make('version')->default(fn (Order $record) => $record->version),
                        TextInput::make('shipping_fee')->label('Frais définitifs dans la devise de la commande')->required(),
                        TextInput::make('tax_amount')->label('Montant des taxes après revue')->helperText('Aucun taux automatique. Saisir explicitement le montant validé pour ce marché.')->required(),
                        Textarea::make('reason')->label('Compte rendu de l’appel')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (Order $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(OrderWorkflow::class)->confirm(auth()->user(), $record->id, (int) $data['version'], $data))),
                Action::make('cancel')->label('Annuler')->requiresConfirmation()->visible(fn (Order $record) => $allowed() && in_array($record->status, [OrderStatus::Pending, OrderStatus::Confirmed]))
                    ->schema([Hidden::make('version')->default(fn (Order $record) => $record->version), Textarea::make('reason')->label('Motif')->required()->minLength(3)->maxLength(1000)])
                    ->action(fn (Order $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(OrderWorkflow::class)->cancel(auth()->user(), $record->id, (int) $data['version'], $data['reason']))),
                Action::make('reactivate')->label('Réactiver')->requiresConfirmation()->visible(fn (Order $record) => $allowed() && in_array($record->status, [OrderStatus::Cancelled, OrderStatus::Expired]))
                    ->schema([Hidden::make('version')->default(fn (Order $record) => $record->version), Hidden::make('request_key')->default(fn () => (string) Str::uuid())])
                    ->action(fn (Order $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(OrderWorkflow::class)->reactivate(auth()->user(), $record->id, (int) $data['version'], $data['request_key']))),
            ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => OrderResource\Pages\ManageOrders::route('/')];
    }

    private static function paymentAction(bool $refund): Action
    {
        return Action::make($refund ? 'recordRefund' : 'recordPayment')->label($refund ? 'Déclarer un remboursement' : 'Déclarer un paiement')
            ->visible(fn () => auth()->user()->can($refund ? StaffPermission::ManageRefunds->value : StaffPermission::RecordPayments->value))
            ->modalDescription($refund ? 'Enregistrez un remboursement effectué hors de l’application. Il devra être vérifié. Cette action ne transfère aucun argent.' : 'Enregistrez le mouvement à contrôler. Le montant ne comptera qu’après vérification indépendante.')
            ->schema([Hidden::make('request_key')->default(fn () => (string) Str::uuid()), TextInput::make('amount')->label('Montant dans la devise de la commande')->required(),
                Select::make('method')->label('Moyen')->options(PaymentMethod::class)->required(),
                TextInput::make('reference')->label('Référence unique du mouvement')->required()->maxLength(120),
                Textarea::make('note')->label('Note interne')->required()->minLength(3)->maxLength(1000)])
            ->action(fn (Order $record, array $data, Action $action) => InventoryActions::run($action, fn () => app(ManualPaymentService::class)->record(auth()->user(), $record->id, $data['request_key'], $data, $refund ? PaymentKind::Refund : PaymentKind::Receipt)));
    }
}
