<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderNotificationEvent;
use App\Models\OrderPayment;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\FulfillmentService;
use App\Services\InventoryService;
use App\Services\OrderNotifications;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseMigrations, InventoryFixture, \Tests\Support\OrderFixture;

    public function runDatabaseMigrations(): void
    {
        // Financial history prevents rollback. Only the disposable test database may be reset.
        if (getenv('VERIFY_MYSQL') !== '1' || DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run tools/verify-mysql.php for real MySQL concurrency tests.');
        }
        $this->assertStringStartsWith('nzhinu_verify_', DB::connection()->getDatabaseName());
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            $this->artisan('db:wipe', ['--force' => true]);
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VERIFY_MYSQL') !== '1' || DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run tools/verify-mysql.php for real multi-process MySQL concurrency tests.');
        }
        $this->assertStringStartsWith('nzhinu_verify_', DB::connection()->getDatabaseName());
    }

    public function test_two_simultaneous_reservations_cannot_sell_the_last_unit_twice(): void
    {
        $f = $this->fixture(1);
        $payload = $this->reservationPayload($f);
        $results = $this->race($f['inventory'], [$payload, [...$payload, 'key' => (string) Str::uuid()]]);
        $this->assertSame(['ok', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $this->assertSame(1, $f['inventory']->fresh()->quantity_reserved);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_simultaneous_identical_request_keys_reserve_once(): void
    {
        $f = $this->fixture(1);
        $payload = $this->reservationPayload($f);
        $results = $this->race($f['inventory'], [$payload, $payload]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(1, $f['inventory']->fresh()->quantity_reserved);
    }

    public function test_release_and_expiry_race_release_only_once(): void
    {
        $f = $this->fixture(1);
        $payload = $this->reservationPayload($f);
        $reservation = app(ReservationService::class)->reserve($f['actor'], $f['ca']->id, $payload['lines'], $payload['key']);
        $reservation->update(['expires_at' => now()->subMinute()]);
        $base = ['actor' => $f['actor']->id, 'reservation' => $reservation->id];
        $results = $this->race($f['inventory'], [[...$base, 'mode' => 'release'], [...$base, 'mode' => 'expire']]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame(0, $f['inventory']->fresh()->quantity_reserved);
        $this->assertSame(1, $f['inventory']->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseCount('reservation_histories', 2);
    }

    public function test_multi_line_reservations_lock_in_same_order_even_when_input_is_reversed(): void
    {
        $f = $this->fixture(1);
        $location = StockLocation::factory()->for($f['ca'])->create(['address' => 'Second lieu de test', 'is_placeholder' => false, 'is_active' => true]);
        $second = app(InventoryService::class)->create($f['actor'], [
            'stock_location_id' => $location->id, 'product_variant_id' => $f['variant']->id,
            'quantity_on_hand' => 1, 'low_stock_threshold' => 0, 'reason' => 'Stock second lieu', 'request_key' => (string) Str::uuid(),
        ]);
        $payload = $this->reservationPayload($f);
        $payload['lines'][] = ['inventory_id' => $second->id, 'quantity' => 1];
        $results = $this->race($f['inventory'], [$payload, [...$payload, 'lines' => array_reverse($payload['lines']), 'key' => (string) Str::uuid()]]);
        $this->assertSame(['ok', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $this->assertSame(1, $second->fresh()->quantity_reserved);
        $this->assertSame(1, $f['inventory']->fresh()->quantity_reserved);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    public function test_correction_and_reservation_race_preserves_available_stock_invariant(): void
    {
        $f = $this->fixture(1);
        $results = $this->race($f['inventory'], [$this->reservationPayload($f), [
            'actor' => $f['actor']->id, 'mode' => 'adjust', 'inventory' => $f['inventory']->id, 'key' => (string) Str::uuid(),
        ]]);
        $this->assertSame(['ok', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $stock = $f['inventory']->fresh();
        $this->assertGreaterThanOrEqual(0, $stock->available);
        $this->assertLessThanOrEqual($stock->quantity_on_hand, $stock->quantity_reserved);
    }

    public function test_guest_order_and_reservation_commit_atomically_under_concurrent_last_unit_requests(): void
    {
        $f = $this->fixture(1);
        $first = $this->checkoutPayload($f);
        $second = $this->checkoutPayload($f);
        $results = $this->race($f['inventory'], [$first, $second]);
        $this->assertSame(['ok', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertSame(1, $f['inventory']->fresh()->quantity_reserved);
    }

    public function test_simultaneous_guest_order_retries_produce_one_order_and_one_reservation(): void
    {
        $f = $this->fixture(1);
        $payload = $this->checkoutPayload($f);
        $results = $this->race($f['inventory'], [$payload, $payload]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('order_histories', 1);
        $this->assertSame(1, $f['inventory']->fresh()->quantity_reserved);
    }

    public function test_concurrent_handover_retries_consume_stock_once(): void
    {
        $f = $this->fixture();
        $admin = $this->administrator();
        $order = $this->newOrder($f);
        $this->verifyPayment($admin, $this->receipt($f['actor'], $order));
        app(FulfillmentService::class)->pack($f['actor'], $order->id, (string) Str::uuid(), 'Préparation test');
        $payload = ['mode' => 'handover', 'actor' => $f['actor']->id, 'order' => $order->id, 'key' => (string) Str::uuid(),
            'data' => ['method' => 'delivery', 'carrier' => 'Transport test', 'reference' => 'TRACK-TEST', 'note' => 'Remise de concurrence']];
        $results = $this->race($order, [$payload, $payload]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame(8, $f['inventory']->fresh()->quantity_on_hand);
        $this->assertSame(0, $f['inventory']->fresh()->quantity_reserved);
        $this->assertDatabaseCount('order_fulfillments', 1);
        $this->assertSame(1, StockMovement::where('type', 'handover')->count());
    }

    public function test_simultaneous_partial_payment_verifications_keep_current_balance_and_version(): void
    {
        $f = $this->fixture();
        $admin = $this->administrator();
        $order = $this->newOrder($f);
        $a = $this->receipt($f['actor'], $order, '50');
        $b = $this->receipt($f['actor'], $order, '55');
        $version = $order->fresh()->version;
        $results = $this->race($order, [
            ['mode' => 'verify_payment', 'actor' => $admin->id, 'payment' => $a->id, 'key' => (string) Str::uuid()],
            ['mode' => 'verify_payment', 'actor' => $admin->id, 'payment' => $b->id, 'key' => (string) Str::uuid()],
        ]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame(10500, $order->fresh()->verified_net_minor);
        $this->assertSame($version + 2, $order->fresh()->version);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_two_refund_declarations_cannot_overdraw_verified_funds(): void
    {
        $f = $this->fixture();
        $admin = $this->administrator();
        $order = $this->newOrder($f);
        $this->verifyPayment($admin, $this->receipt($f['actor'], $order));
        $payload = ['mode' => 'record_refund', 'actor' => $admin->id, 'order' => $order->id, 'key' => (string) Str::uuid(),
            'data' => ['amount' => '60', 'method' => 'bank_transfer', 'reference' => 'REFUND-A', 'note' => 'Remboursement de test']];
        $results = $this->race($order, [$payload, [...$payload, 'key' => (string) Str::uuid(), 'data' => [...$payload['data'], 'reference' => 'REFUND-B']]]);
        $this->assertSame(['ok', 'rejected'], collect($results)->pluck('result')->sort()->values()->all());
        $this->assertSame(1, OrderPayment::where('kind', 'refund')->count());
    }

    public function test_refund_verification_racing_handover_preserves_stock_and_financial_state(): void
    {
        $f = $this->fixture();
        $admin = $this->administrator();
        $order = $this->newOrder($f);
        $this->verifyPayment($admin, $this->receipt($f['actor'], $order));
        app(FulfillmentService::class)->pack($f['actor'], $order->id, (string) Str::uuid(), 'Préparation test');
        $refund = $this->receipt($admin, $order, '10', PaymentKind::Refund);
        $this->race($order, [
            ['mode' => 'verify_payment', 'actor' => $admin->id, 'payment' => $refund->id, 'key' => (string) Str::uuid()],
            ['mode' => 'handover', 'actor' => $f['actor']->id, 'order' => $order->id, 'key' => (string) Str::uuid(),
                'data' => ['method' => 'delivery', 'carrier' => 'Transport test', 'reference' => 'TRACK-TEST', 'note' => 'Remise de concurrence']],
        ]);
        $order->refresh();
        $stock = $f['inventory']->fresh();
        $this->assertSame(9500, $order->verified_net_minor);
        if ($order->status === OrderStatus::Completed) {
            $this->assertSame(8, $stock->quantity_on_hand);
            $this->assertSame(0, $stock->quantity_reserved);
            $this->assertSame(ReservationStatus::Consumed, $order->reservation->status);
        } else {
            $this->assertSame(10, $stock->quantity_on_hand);
            $this->assertSame(2, $stock->quantity_reserved);
            $this->assertSame(FulfillmentStatus::Unfulfilled, $order->fulfillment_status);
        }
    }

    public function test_late_verification_racing_expiry_never_re_reserves_stock(): void
    {
        $f = $this->fixture();
        $admin = $this->administrator();
        $order = $this->newOrder($f);
        $payment = $this->receipt($f['actor'], $order);
        $order->reservation->update(['expires_at' => now()->subMinute()]);
        $results = $this->race($order, [
            ['mode' => 'verify_payment', 'actor' => $admin->id, 'payment' => $payment->id, 'key' => (string) Str::uuid()],
            ['mode' => 'expire', 'actor' => $admin->id, 'reservation' => $order->inventory_reservation_id],
        ]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(10500, $order->fresh()->verified_net_minor);
        $this->assertSame(0, $f['inventory']->fresh()->quantity_reserved);
        $this->assertSame(10, $f['inventory']->fresh()->quantity_on_hand);
    }

    private function checkoutPayload(array $f): array
    {
        $key = (string) Str::uuid();

        return ['mode' => 'checkout', 'actor' => $f['actor']->id, 'owner' => bin2hex(random_bytes(32)),
            'draft' => ['market_id' => $f['ca']->id, 'currency' => 'CAD', 'quote' => [['inventory_id' => $f['inventory']->id, 'quantity' => 1, 'price_minor' => 5000]]],
            'data' => ['request_key' => $key, 'customer_name' => 'Client concurrence', 'phone' => '+15145550123', 'method' => 'delivery', 'street' => 'Adresse fictive test', 'city' => 'Ville test']];
    }

    private function reservationPayload(array $f): array
    {
        return ['mode' => 'reserve', 'actor' => $f['actor']->id, 'market' => $f['ca']->id,
            'lines' => [['inventory_id' => $f['inventory']->id, 'quantity' => 1]], 'key' => (string) Str::uuid()];
    }

    public function test_simultaneous_database_notification_deliveries_create_one_notification(): void
    {
        Queue::fake();
        $f = $this->fixture();
        $this->newOrder($f);
        $event = OrderNotificationEvent::firstOrFail();
        app(OrderNotifications::class)->expand($event->id);
        $delivery = OrderNotificationDelivery::firstOrFail();
        $payload = ['mode' => 'deliver_notification', 'actor' => $f['actor']->id, 'delivery' => $delivery->id];
        $results = $this->race($delivery, [$payload, $payload]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'result'));
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, DB::table('notifications')->where('id', $delivery->notification_id)->count());
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    private function race(Inventory|Order|OrderNotificationDelivery $stock, array $payloads): array
    {
        $config = DB::connection()->getConfig();
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => (string) $config['host'], 'DB_PORT' => (string) $config['port'],
            'DB_DATABASE' => $config['database'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array',
        ];
        $workers = [];
        DB::beginTransaction();
        try {
            $stock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            foreach ($payloads as $payload) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/inventory-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), $environment);
                $process->setInput($input)->setTimeout(20)->start();
                $workers[] = [$process, $input];
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')), $process->getErrorOutput());
            }
            foreach ($workers as [$process, $input]) {
                $input->write("GO\n");
                $input->close();
            }
            foreach ($workers as [$process]) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'START')), $process->getErrorOutput());
            }
            // Both independent workers have started while the parent holds the shared stock lock.
            usleep(100000);
            foreach ($workers as [$process]) {
                $this->assertTrue($process->isRunning(), 'Worker should wait for the inventory row lock.');
            }
            DB::commit();
            $results = [];
            foreach ($workers as [$process]) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($results[0]['pid'], $results[1]['pid']);

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as [$process]) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
