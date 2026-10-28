<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\DeliveryZoneResource;
use App\Filament\Resources\DeliveryZoneResource\Pages\ManageZones;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ManageOrders;
use App\Filament\Resources\PickupPointResource;
use App\Filament\Resources\PickupPointResource\Pages\ManagePoints;
use App\Models\DeliveryZone;
use App\Models\MarketPrice;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\GuestCheckout;
use App\Services\InventoryService;
use App\Services\OrderWorkflow;
use App\Services\ReservationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\Support\EnforcedCsrf;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GuestOrderTest extends TestCase
{
    use InventoryFixture, RefreshDatabase;

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->f = $this->fixture();
    }

    public function test_delivery_order_is_guest_atomic_encrypted_and_snapshot_based(): void
    {
        $data = $this->prepare();
        $response = $this->post('/commander', [...$data, 'unit_price_minor' => 1, 'currency' => 'XAF'])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertGuest();
        $this->assertSame(10000, $order->subtotal_minor);
        $this->assertNull($order->total_minor);
        $this->assertSame('CAD', $order->currency->value);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(2, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertSame(10, $this->f['inventory']->fresh()->quantity_on_hand);
        $this->assertNull($order->reservation->requested_by);
        $this->assertSame($this->f['location']->id, $order->items->first()->stock_location_id);
        $this->assertNotSame('Client Test', DB::table('orders')->value('customer_name'));
        $this->assertNotSame('+15145550123', DB::table('orders')->value('phone'));
        $this->f['product']->update(['name' => 'Nouveau nom']);
        MarketPrice::query()->update(['amount_minor' => 100]);
        $this->get($response->headers->get('Location'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->component('OrderConfirmation')->where('order.items.0.product_name', 'Produit inventaire')
                ->where('order.items.0.unit_price_minor', 5000)->where('order.payment_status_code', 'unpaid')
                ->where('order.fulfillment_status_code', 'unfulfilled')->missing('order.phone')->missing('order.email')->missing('order.delivery_address'));
        $this->assertDatabaseCount('order_histories', 1);
        $this->assertFalse(session()->has('shop.cart'));
    }

    public function test_duplicate_submission_returns_same_order_even_after_cart_is_cleared(): void
    {
        $data = $this->prepare();
        $this->post('/commander', $data)->assertRedirect();
        $this->post('/commander', $data)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertSame(2, $this->f['inventory']->fresh()->quantity_reserved);
        // An old retry must not clear a newly created basket.
        $this->post('/panier/lignes', ['inventory_id' => $this->f['inventory']->id, 'quantity' => 1]);
        $this->post('/commander', $data)->assertRedirect();
        $this->assertSame(1, session('shop.cart.items.'.$this->f['inventory']->id));
    }

    public function test_a_closed_public_market_cannot_place_an_old_draft_but_existing_retries_still_work(): void
    {
        $data = $this->prepare();
        config(['storefront.markets' => ['CM']]);
        $this->post('/commander', $data)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        config(['storefront.markets' => ['CA', 'CM']]);
        $response = $this->post('/commander', $data)->assertRedirect();
        $order = Order::firstOrFail();
        config(['storefront.markets' => ['CM']]);
        $this->post('/commander', $data)->assertRedirect($response->headers->get('Location'));
        $this->assertDatabaseCount('orders', 1);
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertSame($order->id, Order::firstOrFail()->id);
    }

    public function test_stock_failure_rolls_back_order_items_reservation_and_preserves_cart(): void
    {
        $data = $this->prepare();
        $this->f['inventory']->update(['quantity_reserved' => 9]);
        $this->post('/commander', $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertSame(9, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertTrue(session()->has('shop.cart'));
    }

    public function test_price_change_requires_review_and_client_cannot_override_quote(): void
    {
        $data = $this->prepare();
        MarketPrice::where('market_id', $this->f['ca']->id)->update(['amount_minor' => 6000]);
        $this->post('/commander', [...$data, 'quote' => [['price_minor' => 6000]]])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/commander')->assertOk()->assertInertia(fn (Assert $page) => $page->where('checkout.cart.subtotal_minor', 12000));
        $this->post('/commander', $data)->assertRedirect();
        $this->assertSame(12000, Order::firstOrFail()->subtotal_minor);
    }

    public function test_phone_name_delivery_address_required_email_optional_and_invalid_ids_rejected(): void
    {
        $data = $this->prepare();
        $this->post('/commander', [...$data, 'phone' => '', 'customer_name' => '', 'street' => '', 'city' => ''])->assertSessionHasErrors(['phone', 'customer_name', 'street', 'city']);
        $this->post('/commander', [...$data, 'email' => 'invalid'])->assertSessionHasErrors('email');
        $this->post('/commander', [...$data, 'delivery_zone_id' => 999999])->assertSessionHasErrors('delivery_zone_id');
        $this->post('/commander', [...$data, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('request_key');
        $this->assertDatabaseCount('orders', 0);
        $this->post('/commander', $data)->assertRedirect();
        $this->assertNull(Order::firstOrFail()->email);
    }

    public function test_pickup_requires_point_at_same_stock_location_and_snapshots_address(): void
    {
        $point = PickupPoint::create(['market_id' => $this->f['ca']->id, 'stock_location_id' => $this->f['location']->id, 'name' => 'Retrait test', 'address' => 'Adresse publique test', 'is_active' => true]);
        $data = $this->prepare();
        $this->post('/commander', [...$data, 'method' => 'pickup', 'pickup_point_id' => $point->id, 'street' => '', 'city' => ''])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertNull($order->delivery_address);
        $this->assertSame('Adresse publique test', $order->fulfillment_snapshot['address']);
        $this->assertSame($point->id, $order->pickup_point_id);
    }

    public function test_cross_market_and_other_depot_pickup_points_are_rejected(): void
    {
        $location = StockLocation::factory()->for($this->f['ca'])->create(['address' => 'Adresse test', 'is_placeholder' => false, 'is_active' => true]);
        $point = PickupPoint::create(['market_id' => $this->f['ca']->id, 'stock_location_id' => $location->id, 'name' => 'Autre lieu', 'address' => 'Adresse test', 'is_active' => true]);
        $zone = DeliveryZone::create(['market_id' => $this->f['cm']->id, 'name' => 'Zone CM', 'is_active' => true]);
        $data = $this->prepare();
        $this->post('/commander', [...$data, 'method' => 'pickup', 'pickup_point_id' => $point->id])->assertSessionHasErrors('pickup_point_id');
        $this->post('/commander', [...$data, 'delivery_zone_id' => $zone->id])->assertSessionHasErrors('delivery_zone_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
    }

    public function test_signed_confirmation_cannot_be_guessed_tampered_or_used_after_expiry(): void
    {
        $response = $this->post('/commander', $this->prepare());
        $order = Order::firstOrFail();
        $this->get('/commandes/'.$order->reference)->assertForbidden();
        $this->get('/commandes/'.$order->number)->assertNotFound();
        $url = $response->headers->get('Location');
        $this->get($url.'&extra=1')->assertForbidden();
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->travel(31)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_quote_and_idempotency_cannot_be_reused_by_other_session(): void
    {
        $data = $this->prepare();
        $draft = session('checkout.drafts.'.$data['request_key']);
        $owner = session('checkout.owner');
        $service = app(GuestCheckout::class);
        $service->place($owner, $draft, $data);
        $this->expectException(ValidationException::class);
        $service->place('another-session-secret', $draft, $data);
    }

    public function test_staff_confirmation_cancellation_expiration_reactivation_and_linked_reservation_guards(): void
    {
        $this->post('/commander', $this->prepare());
        $order = Order::firstOrFail();
        $actor = $this->f['actor'];
        $service = app(OrderWorkflow::class);
        $confirmed = $service->confirm($actor, $order->id, 0, ['shipping_fee' => '5.50', 'tax_amount' => '0', 'reason' => 'Appel de confirmation']);
        $this->assertSame(OrderStatus::Confirmed, $confirmed->status);
        $this->assertSame(10550, $confirmed->total_minor);
        $this->assertTrue($confirmed->payment_due_at->equalTo($confirmed->reservation->expires_at));
        try {
            app(ReservationService::class)->release($actor, $order->inventory_reservation_id, 'Bypass interdit', 1);
            $this->fail('Linked reservation cannot bypass workflow');
        } catch (ValidationException) {
            $this->assertSame(2, $this->f['inventory']->fresh()->quantity_reserved);
        }
        $cancelled = $service->cancel($actor, $order->id, 1, 'Annulation demandée');
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        $reactivated = $service->reactivate($actor, $order->id, 2, (string) Str::uuid());
        $this->assertSame(OrderStatus::Pending, $reactivated->status);
        $this->assertNull($reactivated->total_minor);
        $this->travel(25)->hours();
        $this->artisan('inventory:release-expired --sync')->assertSuccessful();
        $this->artisan('inventory:release-expired --sync')->assertSuccessful();
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(0, $this->f['inventory']->fresh()->quantity_reserved);
        $this->assertSame(10, $this->f['inventory']->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('order_histories', 5);
    }

    public function test_paid_or_handed_over_orders_are_never_silently_cancelled_or_expired(): void
    {
        $this->post('/commander', $this->prepare());
        $order = Order::firstOrFail();
        $order->update(['payment_status' => PaymentStatus::Paid]);
        $this->travel(25)->hours();
        app(ReservationService::class)->expire($order->inventory_reservation_id);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(2, $this->f['inventory']->fresh()->quantity_reserved);
        $this->expectException(ValidationException::class);
        app(OrderWorkflow::class)->cancel($this->f['actor'], $order->id, 0, 'Annulation dangereuse');
    }

    public function test_staff_without_order_permission_cannot_read_customer_data_or_mutate_order(): void
    {
        $this->post('/commander', $this->prepare());
        $order = Order::firstOrFail();
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $this->actingAs($staff)->get(OrderResource::getUrl())->assertForbidden();
        $this->get(DeliveryZoneResource::getUrl())->assertForbidden();
        $this->get(PickupPointResource::getUrl())->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(OrderWorkflow::class)->cancel($staff, $order->id, 0, 'Non autorisé');
    }

    public function test_filament_can_view_order_confirm_call_and_configure_fulfillment_options(): void
    {
        $this->post('/commander', $this->prepare());
        $order = Order::firstOrFail();
        $this->actingAs($this->f['actor']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageOrders::class)->callAction(TestAction::make('view')->table($order))->assertHasNoActionErrors();
        Livewire::test(ManageOrders::class)->callAction(TestAction::make('confirm')->table($order), data: ['version' => 0, 'shipping_fee' => '5', 'tax_amount' => '0', 'reason' => 'Appel confirmé'])->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        Livewire::test(ManageZones::class)->callAction('create', data: ['market_id' => $this->f['ca']->id, 'name' => 'Zone réelle test', 'estimate' => '5.50', 'is_active' => true])->assertHasNoActionErrors();
        Livewire::test(ManagePoints::class)->callAction('create', data: ['market_id' => $this->f['ca']->id, 'stock_location_id' => $this->f['location']->id, 'name' => 'Point test', 'address' => 'Adresse publique test', 'is_active' => true])->assertHasNoActionErrors();
        $this->assertDatabaseHas('delivery_zones', ['estimated_fee_minor' => 550]);
    }

    public function test_empty_or_stale_basket_cannot_create_order(): void
    {
        $this->get('/commander')->assertRedirect('/panier');
        $data = $this->prepare();
        $this->post('/panier/lignes', ['inventory_id' => $this->f['inventory']->id, 'quantity' => 3]);
        $this->post('/commander', $data)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_retry_after_commit_before_session_cleanup_preserves_new_basket_and_does_not_flash_pii(): void
    {
        $data = $this->prepare();
        $draft = session('checkout.drafts.'.$data['request_key']);
        app(GuestCheckout::class)->place(session('checkout.owner'), $draft, $data);
        $this->post('/panier/lignes', ['inventory_id' => $this->f['inventory']->id, 'quantity' => 3]);
        $this->post('/commander', $data)->assertRedirect();
        $this->assertSame(3, session('shop.cart.items.'.$this->f['inventory']->id));
        $this->post('/commander', [...$data, 'phone' => 'bad'])->assertSessionHasErrors('phone');
        $this->assertFalse(session()->has('_old_input.phone'));
    }

    public function test_receipt_remains_accessible_when_both_markets_are_disabled(): void
    {
        $response = $this->post('/commander', $this->prepare());
        $this->f['ca']->update(['is_active' => false]);
        $this->f['cm']->update(['is_active' => false]);
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_guest_checkout_post_requires_csrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, EnforcedCsrf::class);
        $this->post('/commander', ['request_key' => (string) Str::uuid()])->assertStatus(419);
    }

    public function test_customer_order_is_priced_in_whole_xaf_and_confirmation_has_no_fractional_fees(): void
    {
        $owner = bin2hex(random_bytes(32));
        $location = StockLocation::where('market_id', $this->f['cm']->id)->firstOrFail();
        $location->update(['address' => 'Adresse test CM', 'is_placeholder' => false, 'is_active' => true]);
        $inventory = app(InventoryService::class)->create($this->f['actor'], [
            'stock_location_id' => $location->id, 'product_variant_id' => $this->f['variant']->id,
            'quantity_on_hand' => 2, 'low_stock_threshold' => 0, 'reason' => 'Stock CM test', 'request_key' => (string) Str::uuid(),
        ]);
        $order = app(GuestCheckout::class)->place($owner, ['market_id' => $this->f['cm']->id, 'currency' => 'XAF', 'quote' => [['inventory_id' => $inventory->id, 'quantity' => 2, 'price_minor' => 25000]]],
            ['request_key' => (string) Str::uuid(), 'customer_name' => 'Client test CM', 'phone' => '+237699000000', 'method' => 'delivery', 'street' => 'Adresse test', 'city' => 'Ville test']);
        $this->assertSame(50000, $order->subtotal_minor);
        $confirmed = app(OrderWorkflow::class)->confirm($this->f['actor'], $order->id, 0, ['shipping_fee' => '1500', 'tax_amount' => '0', 'reason' => 'Appel test CM']);
        $this->assertSame(51500, $confirmed->total_minor);
    }

    private function prepare(): array
    {
        $this->post('/panier/lignes', ['inventory_id' => $this->f['inventory']->id, 'quantity' => 2]);
        $response = $this->get('/commander')->assertOk();
        $key = $response->viewData('page')['props']['checkout']['request_key'];

        return ['request_key' => $key, 'customer_name' => 'Client Test', 'phone' => '+1 (514) 555-0123', 'email' => '',
            'method' => 'delivery', 'street' => 'Adresse fictive de test', 'city' => 'Ville test'];
    }
}
