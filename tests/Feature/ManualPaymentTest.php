<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentEntryStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\OrderPaymentResource;
use App\Filament\Resources\OrderPaymentResource\Pages\ManagePayments;
use App\Filament\Resources\OrderResource\Pages\ManageOrders;
use App\Models\MarketPrice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FulfillmentService;
use App\Services\GuestCheckout;
use App\Services\InventoryService;
use App\Services\ManualPaymentService;
use App\Services\OrderAccess;
use App\Services\OrderWorkflow;
use App\Services\ReservationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\InventoryFixture;
use Tests\Support\OrderFixture;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use InventoryFixture, OrderFixture, RefreshDatabase;

    private array $f;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->f = $this->fixture();
        $this->admin = $this->administrator();
    }

    private function denied(callable $action): void
    {
        try {
            $action();
            $this->fail('Action should be rejected');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function handoverData(string $method = 'delivery'): array
    {
        return ['method' => $method, 'carrier' => $method === 'delivery' ? 'Transport test' : null, 'reference' => 'REMISE-TEST', 'note' => 'Remise constatée test'];
    }

    private function pack(Order $order): void
    {
        app(FulfillmentService::class)->pack($this->f['actor'], $order->id, (string) Str::uuid(), 'Articles contrôlés');
    }

    public function test_declarations_do_not_pay_and_partial_verified_payments_cannot_prepare(): void
    {
        $order = $this->newOrder($this->f);
        $p = $this->receipt($this->f['actor'], $order, '50');
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->denied(fn () => $this->pack($order));
        $this->verifyPayment($this->admin, $p);
        $this->assertSame(PaymentStatus::Partial, $order->fresh()->payment_status);
        $this->assertSame(5000, $order->fresh()->verified_net_minor);
        $this->denied(fn () => $this->pack($order));
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order, '55'));
        $this->pack($order);
        $this->assertSame(FulfillmentStatus::Packed, $order->fresh()->fulfillment_status);
        $this->assertSame(10, $this->f['inventory']->fresh()->quantity_on_hand);
        $this->assertSame(2, $this->f['inventory']->fresh()->quantity_reserved);
    }

    public function test_manager_cannot_verify_payments(): void
    {
        $p = $this->receipt($this->f['actor'], $this->newOrder($this->f));
        $this->expectException(AuthorizationException::class);
        $this->verifyPayment($this->f['actor'], $p);
    }

    public function test_manager_cannot_record_refunds(): void
    {
        $order = $this->newOrder($this->f);
        $this->expectException(AuthorizationException::class);
        $this->receipt($this->f['actor'], $order, '1', PaymentKind::Refund);
    }

    public function test_readonly_staff_cannot_record_or_fulfill(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $order = $this->newOrder($this->f);
        foreach (['record', 'pack'] as $action) {
            try {
                $action === 'record' ? $this->receipt($staff, $order) : app(FulfillmentService::class)->pack($staff, $order->id, (string) Str::uuid(), 'Test interdit');
                $this->fail('Authorization required');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_customer_screenshot_and_wrong_source_cannot_verify(): void
    {
        $p = $this->receipt($this->f['actor'], $this->newOrder($this->f));
        foreach (['screenshot', 'cash_count'] as $source) {
            $this->denied(fn () => app(ManualPaymentService::class)->verify($this->admin, $p->id, (string) Str::uuid(), ['source' => $source, 'note' => 'Contrôle de test indépendant']));
        }
        $this->assertSame(PaymentEntryStatus::Pending, $p->fresh()->status);
    }

    public function test_payment_and_verification_retries_are_bound_to_payload(): void
    {
        $order = $this->newOrder($this->f);
        $key = (string) Str::uuid();
        $data = ['amount' => '105', 'method' => 'bank_transfer', 'reference' => ' REF-1 ', 'note' => 'Mouvement de test'];
        $service = app(ManualPaymentService::class);
        $p = $service->record($this->f['actor'], $order->id, $key, $data);
        $this->assertSame($p->id, $service->record($this->f['actor'], $order->id, $key, $data)->id);
        $this->denied(fn () => $service->record($this->f['actor'], $order->id, $key, [...$data, 'amount' => '104']));
        $this->denied(fn () => $service->record($this->f['actor'], $order->id, (string) Str::uuid(), $data));
        $other = $this->newOrder($this->f);
        $this->denied(fn () => $service->record($this->f['actor'], $other->id, (string) Str::uuid(), [...$data, 'reference' => 'ref-1']));
        $verifyKey = (string) Str::uuid();
        $proof = ['source' => 'bank_statement', 'note' => 'Contrôle indépendant du relevé bancaire'];
        $service->verify($this->admin, $p->id, $verifyKey, $proof);
        $service->verify($this->admin, $p->id, $verifyKey, $proof);
        $this->denied(fn () => $service->verify($this->admin, $p->id, (string) Str::uuid(), $proof));
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertSame(10500, $order->fresh()->verified_net_minor);
    }

    public function test_rejection_is_audited_and_verified_entries_are_immutable(): void
    {
        $order = $this->newOrder($this->f);
        $p = $this->receipt($this->f['actor'], $order);
        $key = (string) Str::uuid();
        $service = app(ManualPaymentService::class);
        $service->reject($this->admin, $p->id, $key, 'Aucun mouvement retrouvé');
        $service->reject($this->admin, $p->id, $key, 'Aucun mouvement retrouvé');
        $this->assertSame($this->admin->id, $p->fresh()->rejected_by);
        $this->denied(fn () => $this->verifyPayment($this->admin, $p));
        $p2 = $this->receipt($this->f['actor'], $order);
        $this->verifyPayment($this->admin, $p2);
        $this->denied(fn () => $service->reject($this->admin, $p2->id, (string) Str::uuid(), 'Correction interdite'));
        $this->assertSame(10500, $order->fresh()->verified_net_minor);
        $this->assertNotNull($order->histories()->latest('id')->first()->context);
    }

    public function test_late_payment_records_funds_without_reactivating_stock(): void
    {
        $order = $this->newOrder($this->f);
        $p = $this->receipt($this->f['actor'], $order);
        $order->reservation->update(['expires_at' => now()->subSecond()]);
        $this->verifyPayment($this->admin, $p);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertTrue($order->fresh()->financial_review_required);
        $this->denied(fn () => $this->pack($order));
        $reactivated = app(OrderWorkflow::class)->reactivate($this->admin, $order->id, $order->fresh()->version, (string) Str::uuid());
        $this->assertSame(OrderStatus::Pending, $reactivated->status);
        $confirmed = app(OrderWorkflow::class)->confirm($this->admin, $order->id, $reactivated->version, ['shipping_fee' => '5', 'tax_amount' => '0', 'reason' => 'Nouvel appel confirmé']);
        $this->assertSame(PaymentStatus::Paid, $confirmed->payment_status);
        $this->pack($confirmed);
    }

    public function test_expiry_releases_partially_paid_stock_but_holds_fully_paid_stock(): void
    {
        $order = $this->newOrder($this->f);
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order, '50'));
        $order->reservation->update(['expires_at' => now()->subMinute()]);
        app(ReservationService::class)->expire($order->inventory_reservation_id);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(5000, $order->fresh()->verified_net_minor);
        $other = $this->newOrder($this->f);
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $other));
        $other->reservation->update(['expires_at' => now()->subMinute()]);
        app(ReservationService::class)->expire($other->inventory_reservation_id);
        $this->assertSame(ReservationStatus::Active, $other->reservation->fresh()->status);
    }

    public function test_refund_budget_and_verification_suspend_preparation_without_restoring_stock(): void
    {
        $order = $this->newOrder($this->f);
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order));
        $this->pack($order);
        $r = $this->receipt($this->admin, $order, '60', PaymentKind::Refund);
        $this->denied(fn () => $this->receipt($this->admin, $order, '46', PaymentKind::Refund));
        $this->assertSame(FulfillmentStatus::Packed, $order->fresh()->fulfillment_status);
        $this->verifyPayment($this->admin, $r);
        $this->assertSame(FulfillmentStatus::Unfulfilled, $order->fresh()->fulfillment_status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);
        $this->assertSame(4500, $order->fresh()->verified_net_minor);
        $this->denied(fn () => $this->pack($order));
        $this->assertSame(10, $this->f['inventory']->fresh()->quantity_on_hand);
    }

    public function test_delivery_and_pickup_consume_stock_once_and_refund_after_completion_keeps_history(): void
    {
        foreach (['delivery', 'pickup'] as $method) {
            $order = $this->newOrder($this->f, method: $method);
            $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order));
            $this->pack($order);
            $key = (string) Str::uuid();
            $data = $this->handoverData($method);
            $service = app(FulfillmentService::class);
            $this->denied(fn () => $service->handover($this->f['actor'], $order->id, (string) Str::uuid(), $this->handoverData($method === 'delivery' ? 'pickup' : 'delivery')));
            $service->handover($this->f['actor'], $order->id, $key, $data);
            $service->handover($this->f['actor'], $order->id, $key, $data);
            $this->denied(fn () => $service->handover($this->f['actor'], $order->id, (string) Str::uuid(), $data));
            $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
            $this->assertSame(ReservationStatus::Consumed, $order->reservation->fresh()->status);
            $summary = app(OrderAccess::class)->summary($order->fresh());
            $this->assertSame('paid', $summary['payment_status_code']);
            $this->assertSame($method === 'delivery' ? 'shipped' : 'collected', $summary['fulfillment_status_code']);
            $this->verifyPayment($this->admin, $this->receipt($this->admin, $order, '105', PaymentKind::Refund));
            $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
            $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
            $this->assertSame('refunded', app(OrderAccess::class)->summary($order->fresh())['payment_status_code']);
            $this->assertNotNull(app(OrderAccess::class)->summary($order->fresh())['handover']);
            $this->denied(fn () => app(OrderWorkflow::class)->cancel($this->admin, $order->id, $order->fresh()->version, 'Annulation interdite'));
            $this->denied(fn () => app(ReservationService::class)->reactivate($this->admin, $order->inventory_reservation_id, (string) Str::uuid()));
        }
        $this->assertSame(6, $this->f['inventory']->fresh()->quantity_on_hand);
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertDatabaseCount('order_fulfillments', 2);
        $this->assertSame(2, StockMovement::where('type', 'handover')->count());
    }

    public function test_handover_requires_preparation_tracking_and_actual_verified_ledger(): void
    {
        $order = $this->newOrder($this->f);
        $order->update(['payment_status' => PaymentStatus::Paid, 'verified_net_minor' => 10500]);
        $this->denied(fn () => $this->pack($order));
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order));
        $service = app(FulfillmentService::class);
        $this->denied(fn () => $service->handover($this->f['actor'], $order->id, (string) Str::uuid(), $this->handoverData()));
        $this->pack($order);
        $this->denied(fn () => $service->handover($this->f['actor'], $order->id, (string) Str::uuid(), [...$this->handoverData(), 'carrier' => '']));
        $this->assertDatabaseCount('order_fulfillments', 0);
    }

    public function test_paid_cancellation_releases_only_reservation_and_requires_refund_permission(): void
    {
        $order = $this->newOrder($this->f);
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order));
        try {
            app(OrderWorkflow::class)->cancel($this->f['actor'], $order->id, $order->fresh()->version, 'Annulation demandée');
            $this->fail('Admin required');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        app(OrderWorkflow::class)->cancel($this->admin, $order->id, $order->fresh()->version, 'Annulation demandée');
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertSame(10, $this->f['inventory']->fresh()->quantity_on_hand);
        $this->assertSame(10500, $order->fresh()->verified_net_minor);
        $this->assertTrue($order->fresh()->financial_review_required);
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_public_summary_displays_balances_without_internal_payment_details(): void
    {
        $order = $this->newOrder($this->f);
        $p = $this->receipt($this->f['actor'], $order);
        $this->verifyPayment($this->admin, $p);
        $summary = app(OrderAccess::class)->summary($order->fresh());
        $this->assertSame(10500, $summary['verified_net_minor']);
        $this->assertSame(0, $summary['outstanding_minor']);
        $this->assertStringNotContainsString($p->reference, json_encode($summary));
        $this->assertArrayNotHasKey('payments', $summary);
        $this->get('/commandes/'.$order->reference)->assertForbidden();
    }

    public function test_filament_record_verify_prepare_and_handover_workflow_and_permissions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $order = $this->newOrder($this->f);
        $this->actingAs($this->f['actor']);
        Livewire::test(ManageOrders::class)
            ->callAction(TestAction::make('recordPayment')->table($order), data: ['request_key' => (string) Str::uuid(), 'amount' => '105', 'method' => 'bank_transfer', 'reference' => 'FILAMENT-TEST', 'note' => 'Déclaration test'])->assertHasNoActionErrors();
        $payment = OrderPayment::firstOrFail();
        Livewire::test(ManagePayments::class)
            ->assertActionHidden(TestAction::make('verify')->table($payment));
        $this->actingAs($this->admin);
        Livewire::test(ManagePayments::class)
            ->callAction(TestAction::make('verify')->table($payment), data: ['request_key' => (string) Str::uuid(), 'source' => 'bank_statement', 'note' => 'Contrôle bancaire indépendant test'])->assertHasNoActionErrors();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->actingAs($this->f['actor']);
        Livewire::test(ManageOrders::class)
            ->callAction(TestAction::make('pack')->table($order), data: ['request_key' => (string) Str::uuid(), 'note' => 'Préparation complète'])->assertHasNoActionErrors()
            ->callAction(TestAction::make('handover')->table($order->fresh()), data: ['request_key' => (string) Str::uuid(), ...$this->handoverData()])->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $payment));
        $this->assertFalse(Gate::forUser($this->admin)->allows('delete', $payment));
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $this->actingAs($staff);
        $this->get(OrderPaymentResource::getUrl())->assertForbidden();
    }

    public function test_cash_verification_and_exact_currency_precision(): void
    {
        $order = $this->newOrder($this->f);
        $service = app(ManualPaymentService::class);
        foreach (['-1', '1.001', '1e3', 'NaN'] as $amount) {
            $this->denied(fn () => $this->receipt($this->f['actor'], $order, $amount));
        }
        $cash = $service->record($this->f['actor'], $order->id, (string) Str::uuid(), ['amount' => '105.00', 'method' => 'cash', 'reference' => 'CAISSE-TEST', 'note' => 'Espèces reçues test']);
        $this->denied(fn () => $this->verifyPayment($this->admin, $cash));
        $service->verify($this->admin, $cash->id, (string) Str::uuid(), ['source' => 'cash_count', 'note' => 'Comptage physique de la caisse effectué']);
        $this->assertSame(10500, $order->fresh()->verified_net_minor);
        $location = StockLocation::where('market_id', $this->f['cm']->id)->firstOrFail();
        $location->update(['address' => 'Adresse test CM', 'is_placeholder' => false, 'is_active' => true]);
        $inventory = app(InventoryService::class)->create($this->f['actor'], ['stock_location_id' => $location->id,
            'product_variant_id' => $this->f['variant']->id, 'quantity_on_hand' => 2, 'low_stock_threshold' => 0, 'reason' => 'Stock CM test', 'request_key' => (string) Str::uuid()]);
        $cmOrder = app(GuestCheckout::class)->place(bin2hex(random_bytes(32)), ['market_id' => $this->f['cm']->id, 'currency' => 'XAF',
            'quote' => [['inventory_id' => $inventory->id, 'quantity' => 1, 'price_minor' => 25000]]],
            ['request_key' => (string) Str::uuid(), 'customer_name' => 'Client CM', 'phone' => '+237699000000', 'method' => 'delivery', 'street' => 'Adresse test', 'city' => 'Ville test']);
        app(OrderWorkflow::class)->confirm($this->f['actor'], $cmOrder->id, 0, ['shipping_fee' => '0', 'tax_amount' => '0', 'reason' => 'Appel confirmé']);
        $this->denied(fn () => $this->receipt($this->f['actor'], $cmOrder, '25000.50'));
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $cmOrder, '25000'));
        $this->assertSame(25000, $cmOrder->fresh()->verified_net_minor);
        $this->assertSame('XAF', $cmOrder->currency->value);
    }

    public function test_zero_total_requires_explicit_verification_and_overpayment_needs_review(): void
    {
        MarketPrice::where('market_id', $this->f['ca']->id)->update(['amount_minor' => 0]);
        $draft = ['market_id' => $this->f['ca']->id, 'currency' => 'CAD', 'quote' => [['inventory_id' => $this->f['inventory']->id, 'quantity' => 1, 'price_minor' => 0]]];
        $order = app(GuestCheckout::class)->place(bin2hex(random_bytes(32)), $draft,
            ['request_key' => (string) Str::uuid(), 'customer_name' => 'Client test', 'phone' => '+15145550123', 'method' => 'delivery', 'street' => 'Adresse test', 'city' => 'Ville test']);
        app(OrderWorkflow::class)->confirm($this->f['actor'], $order->id, 0, ['shipping_fee' => '0', 'tax_amount' => '0', 'reason' => 'Gratuité validée']);
        $this->denied(fn () => $this->pack($order));
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order, '0'));
        $this->pack($order);
        $this->verifyPayment($this->admin, $this->receipt($this->f['actor'], $order, '1'));
        $this->assertTrue($order->fresh()->financial_review_required);
    }
}
