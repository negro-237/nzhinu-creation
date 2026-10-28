<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Notifications\LowStockNotification;
use App\Services\ManualPaymentService;
use App\Services\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\Support\InventoryFixture;
use Tests\Support\OrderFixture;
use Tests\TestCase;

class AcceptanceRegressionTest extends TestCase
{
    use InventoryFixture, OrderFixture, RefreshDatabase;

    public function test_receipt_views_do_not_consume_the_checkout_placement_quota(): void
    {
        $this->withoutVite();
        Queue::fake();
        $f = $this->fixture();
        $order = $this->newOrder($f);
        $link = app(OrderAccess::class)->link($order);
        for ($i = 0; $i < 6; $i++) {
            $this->get($link)->assertOk();
        }
        $this->post('/panier/lignes', ['inventory_id' => $f['inventory']->id, 'quantity' => 1])->assertRedirect();
        $page = $this->get('/commander')->assertOk();
        $key = $page->viewData('page')['props']['checkout']['request_key'];
        $this->post('/commander', ['request_key' => $key, 'customer_name' => 'Client recette', 'phone' => '+15145550123',
            'method' => 'delivery', 'street' => 'Adresse fictive', 'city' => 'Ville test'])->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_checkout_and_receipt_each_keep_their_own_rate_limit(): void
    {
        $this->withoutVite();
        Queue::fake();
        $f = $this->fixture();
        $order = $this->newOrder($f);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/commander', [])->assertRedirect();
        }
        $this->post('/commander', [])->assertStatus(429);
        $link = app(OrderAccess::class)->link($order);
        for ($i = 0; $i < 30; $i++) {
            $this->get($link)->assertOk();
        }
        $this->get($link)->assertStatus(429);
    }

    public function test_confirmed_receipt_uses_current_tracking_title_and_safe_status_code(): void
    {
        $this->withoutVite();
        Queue::fake();
        $f = $this->fixture();
        $order = $this->newOrder($f);
        $this->get(app(OrderAccess::class)->link($order))->assertOk()->assertSee('Suivi de votre commande')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.status_code', 'confirmed')->missing('order.phone'));
    }

    public function test_payment_reference_is_validated_after_unicode_normalization_without_partial_insert(): void
    {
        Queue::fake();
        $f = $this->fixture();
        $order = $this->newOrder($f);
        try {
            app(ManualPaymentService::class)->record($f['actor'], $order->id, (string) Str::uuid(), [
                'amount' => '55', 'method' => 'bank_transfer', 'reference' => str_repeat('ß', 120), 'note' => 'Déclaration de test',
            ]);
            $this->fail('Normalized references must fit the database column');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reference', $e->errors());
        }
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('order_operations', 0);
        $this->assertSame(1, $order->fresh()->version);
    }

    public function test_delayed_low_stock_alert_rechecks_permissions_before_sending(): void
    {
        Queue::fake();
        $f = $this->fixture();
        $alert = new LowStockNotification($f['inventory']->id, 'INV-001', 'Dépôt test', 1);
        Notification::sendNow($f['actor'], $alert);
        $this->assertDatabaseCount('notifications', 1);
        $queued = serialize(new SendQueuedNotifications($f['actor'],
            new LowStockNotification($f['inventory']->id, 'INV-001', 'Dépôt test', 0), ['database']));
        $f['actor']->syncRoles([StaffRole::Staff]);
        unserialize($queued)->handle(app(ChannelManager::class));
        $this->assertDatabaseCount('notifications', 1);
    }
}
