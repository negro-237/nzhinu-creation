<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\InventoryReservationResource;
use App\Filament\Resources\InventoryReservationResource\Pages\ManageReservations;
use App\Filament\Resources\InventoryResource;
use App\Filament\Resources\InventoryResource\Pages\ManageInventories;
use App\Filament\Resources\StockLocationResource;
use App\Filament\Resources\StockLocationResource\Pages\ManageStockLocations;
use App\Filament\Resources\StockMovementResource;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\StockLocation;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\InventoryService;
use App\Services\ReservationService;
use App\Services\StockLocationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use InventoryFixture, RefreshDatabase;

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->f = $this->fixture();
        $this->actingAs($this->f['actor']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_adjustments_are_audited_and_idempotent_and_cannot_use_another_payload(): void
    {
        $data = $this->adjustment(4);
        $stock = $this->adjust($data);
        $this->assertSame(14, $stock->quantity_on_hand);
        $this->assertSame(14, $this->adjust($data)->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 2);
        $data['quantity_delta'] = 5;
        $this->invalid(fn () => $this->adjust($data), 'request_key');
        $this->assertDatabaseHas('stock_movements', ['quantity_delta' => 4, 'on_hand_after' => 14, 'actor_id' => $this->f['actor']->id]);
    }

    public function test_initialization_retries_once_and_rejects_placeholder_location(): void
    {
        $location = StockLocation::factory()->for($this->f['ca'])->create(['address' => 'Adresse validée', 'is_placeholder' => false]);
        $data = [
            'stock_location_id' => $location->id, 'product_variant_id' => $this->f['variant']->id,
            'quantity_on_hand' => 5, 'low_stock_threshold' => 1, 'reason' => 'Comptage initial', 'request_key' => (string) Str::uuid(),
        ];
        $service = app(InventoryService::class);
        $one = $service->create($this->f['actor'], $data);
        $this->assertTrue($one->is($service->create($this->f['actor'], $data)));
        $this->assertSame(5, $one->fresh()->quantity_on_hand);
        $this->assertCount(1, $one->movements);
        $location->update(['is_placeholder' => true]);
        $data['request_key'] = (string) Str::uuid();
        $this->invalid(fn () => $service->create($this->f['actor'], $data), 'stock_location_id');
    }

    public function test_invalid_quantities_and_reusing_another_actors_key_do_not_mutate_stock(): void
    {
        $data = $this->adjustment(2);
        $this->adjust($data);
        $other = User::factory()->create();
        $other->assignRole(StaffRole::Manager);
        $this->invalid(fn () => app(InventoryService::class)->adjust($other, $this->f['inventory']->id, $data), 'request_key');
        $this->invalid(fn () => $this->reserve(0), 'lines.0.quantity');
        $this->invalid(fn () => $this->adjust(['quantity_delta' => '1.5', 'reason' => 'Invalid quantity', 'request_key' => (string) Str::uuid()]), 'quantity_delta');
        $this->assertSame(12, $this->stock()->quantity_on_hand);
        $this->assertSame(0, $this->stock()->quantity_reserved);
    }

    public function test_reserve_and_retry_reserve_once_from_the_explicit_location(): void
    {
        $key = (string) Str::uuid();
        $reservation = $this->reserve(4, $key);
        $again = $this->reserve(4, $key);
        $this->assertTrue($again->is($reservation));
        $this->assertSame(10, $this->stock()->quantity_on_hand);
        $this->assertSame(4, $this->stock()->quantity_reserved);
        $this->assertSame(6, $this->stock()->available);
        $this->assertSame($this->f['location']->id, $reservation->items->first()->stock_location_id);
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $reservation->expires_at->timestamp, 2);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('reservation_histories', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->invalid(fn () => $this->reserve(5, $key), 'request_key');
    }

    public function test_releases_and_expiration_are_idempotent_and_preserve_physical_stock(): void
    {
        $reservation = $this->reserve(5);
        $service = app(ReservationService::class);
        $service->release($this->f['actor'], $reservation->id, 'Annulation de test', 1);
        $service->release($this->f['actor'], $reservation->id, 'Annulation de test', 1);
        $service->expire($reservation->id);
        $this->assertSame(0, $this->stock()->quantity_reserved);
        $this->assertSame(10, $this->stock()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseCount('reservation_histories', 2);
        $this->assertSame(ReservationStatus::Released, $reservation->fresh()->status);
    }

    public function test_scheduler_job_only_expires_due_reservations_and_is_safe_to_retry(): void
    {
        $reservation = $this->reserve(3);
        (new ReleaseExpiredReservations)->handle(app(ReservationService::class));
        $this->assertSame(3, $this->stock()->quantity_reserved);
        $this->travel(25)->hours();
        $this->artisan('inventory:release-expired --sync')->assertSuccessful();
        $this->artisan('inventory:release-expired --sync')->assertSuccessful();
        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertSame(0, $this->stock()->quantity_reserved);
        $this->assertSame(10, $this->stock()->quantity_on_hand);
        $this->assertDatabaseCount('reservation_histories', 2);
    }

    public function test_configurable_deadline_and_reactivation_recheck_stock(): void
    {
        config(['inventory.reservation_hours' => 2]);
        $this->travelTo(now()->startOfSecond());
        $reservation = $this->reserve(7);
        $this->assertTrue($reservation->expires_at->equalTo(now()->addHours(2)));
        $this->travel(3)->hours();
        app(ReservationService::class)->expire($reservation->id);
        $other = $this->reserve(6);
        $key = (string) Str::uuid();
        $this->invalid(fn () => app(ReservationService::class)->reactivate($this->f['actor'], $reservation->id, $key), 'lines.0.inventory_id');
        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        app(ReservationService::class)->release($this->f['actor'], $other->id, 'Libérer pour réactiver', 1);
        $reactivated = app(ReservationService::class)->reactivate($this->f['actor'], $reservation->id, $key);
        app(ReservationService::class)->reactivate($this->f['actor'], $reservation->id, $key);
        $this->assertSame(2, $reactivated->cycle);
        $this->assertSame(7, $this->stock()->quantity_reserved);
        // An old cancellation retry must never release the new cycle.
        app(ReservationService::class)->release($this->f['actor'], $reservation->id, 'Ancienne annulation', 1);
        $this->assertSame(7, $this->stock()->quantity_reserved);
    }

    public function test_expired_creation_retry_does_not_silently_reactivate(): void
    {
        $key = (string) Str::uuid();
        $reservation = $this->reserve(4, $key);
        $this->travel(25)->hours();
        app(ReservationService::class)->expire($reservation->id);
        $this->assertSame(ReservationStatus::Expired, $this->reserve(4, $key)->status);
        $this->assertSame(0, $this->stock()->quantity_reserved);
    }

    public function test_overselling_and_duplicate_inventory_lines_are_rejected_atomically(): void
    {
        $this->invalid(fn () => $this->reserve(11), 'lines.0.inventory_id');
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseCount('inventory_operations', 1);
        $line = ['inventory_id' => $this->f['inventory']->id, 'quantity' => 6];
        $this->invalid(fn () => app(ReservationService::class)->reserve($this->f['actor'], $this->f['ca']->id, [$line, $line], (string) Str::uuid()), 'lines.0.inventory_id');
        $this->assertSame(0, $this->stock()->quantity_reserved);
    }

    public function test_two_locations_are_independent_and_cross_border_reservation_is_refused(): void
    {
        $location = StockLocation::factory()->for($this->f['cm'])->create(['address' => 'Adresse test CM', 'is_placeholder' => false, 'is_active' => true]);
        $stock = app(InventoryService::class)->create($this->f['actor'], [
            'stock_location_id' => $location->id, 'product_variant_id' => $this->f['variant']->id,
            'quantity_on_hand' => 20, 'low_stock_threshold' => 2, 'reason' => 'Stock Cameroun', 'request_key' => (string) Str::uuid(),
        ]);
        $this->invalid(fn () => app(ReservationService::class)->reserve($this->f['actor'], $this->f['ca']->id,
            [['inventory_id' => $this->f['inventory']->id, 'quantity' => 3], ['inventory_id' => $stock->id, 'quantity' => 1]], (string) Str::uuid()), 'lines.1.inventory_id');
        $this->assertSame(0, $this->stock()->quantity_reserved);
        $this->assertSame(0, $stock->fresh()->quantity_reserved);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }

    public function test_adjustment_cannot_reduce_stock_below_reservations(): void
    {
        $this->reserve(8);
        $this->invalid(fn () => $this->adjust($this->adjustment(-3)), 'quantity_delta');
        $this->assertSame(10, $this->stock()->quantity_on_hand);
        $this->assertSame(8, $this->stock()->quantity_reserved);
        $this->adjust($this->adjustment(-2));
        $this->assertSame(0, $this->stock()->available);
    }

    public function test_inactive_or_archived_catalog_and_placeholder_locations_cannot_be_reserved(): void
    {
        $this->f['location']->update(['is_active' => false]);
        $this->invalid(fn () => $this->reserve(1), 'lines.0.inventory_id');
        $this->f['location']->update(['is_active' => true, 'is_placeholder' => true]);
        $this->invalid(fn () => $this->reserve(1), 'lines.0.inventory_id');
        $this->f['location']->update(['is_placeholder' => false]);
        $this->f['variant']->delete();
        $this->invalid(fn () => $this->reserve(1), 'lines.0.inventory_id');
    }

    public function test_low_stock_alerts_fire_once_per_crossing_and_are_queued_after_commit(): void
    {
        $this->reserve(8);
        Notification::assertSentToTimes($this->f['actor'], LowStockNotification::class, 1);
        $this->adjust($this->adjustment(0));
        Notification::assertSentToTimes($this->f['actor'], LowStockNotification::class, 1);
        $this->adjust($this->adjustment(5));
        $this->assertNull($this->stock()->low_stock_alerted_at);
        $this->reserve(5);
        Notification::assertSentToTimes($this->f['actor'], LowStockNotification::class, 2);
        $notification = new LowStockNotification(1, 'SKU', 'Lieu', 1);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame('filament', $notification->toDatabase($this->f['actor'])['format']);
    }

    public function test_staff_can_read_stocks_but_not_adjust_or_reserve(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(StaffRole::Staff);
        $this->actingAs($staff)->get(InventoryResource::getUrl())->assertOk();
        $this->get(InventoryReservationResource::getUrl())->assertOk();
        $this->get(StockMovementResource::getUrl())->assertOk();
        $this->get(StockLocationResource::getUrl())->assertForbidden();
        Livewire::test(ManageInventories::class)->assertActionHidden(TestAction::make('adjust')->table($this->f['inventory']));
        try {
            app(ReservationService::class)->reserve($staff, $this->f['ca']->id, [['inventory_id' => $this->f['inventory']->id, 'quantity' => 1]], (string) Str::uuid());
            $this->fail('Authorization expected');
        } catch (AuthorizationException) {
            $this->assertSame(0, $this->stock()->quantity_reserved);
        }
        $this->expectException(AuthorizationException::class);
        app(InventoryService::class)->adjust($staff, $this->f['inventory']->id, $this->adjustment(1));
    }

    public function test_filament_adjust_reserve_release_and_view_audit(): void
    {
        Livewire::test(ManageInventories::class)->callAction(TestAction::make('adjust')->table($this->f['inventory']), data: $this->adjustment(2))->assertHasNoActionErrors();
        $this->assertSame(12, $this->stock()->quantity_on_hand);
        Livewire::test(ManageReservations::class)->callAction('create', data: [
            'market_id' => $this->f['ca']->id, 'request_key' => (string) Str::uuid(),
            'lines' => [['inventory_id' => $this->f['inventory']->id, 'quantity' => 4]],
        ])->assertHasNoActionErrors();
        $reservation = InventoryReservation::firstOrFail();
        $this->assertSame(4, $this->stock()->quantity_reserved);
        Livewire::test(ManageReservations::class)->callAction(TestAction::make('view')->table($reservation))->assertHasNoActionErrors();
        Livewire::test(ManageReservations::class)->callAction(TestAction::make('release')->table($reservation), data: ['cycle' => 1, 'reason' => 'Annulation client'])->assertHasNoActionErrors();
        $this->assertSame(0, $this->stock()->quantity_reserved);
        $this->get(StockMovementResource::getUrl())->assertOk();
    }

    public function test_location_activation_and_archival_guard_and_market_immutability(): void
    {
        $location = $this->f['location'];
        $data = $location->only(['market_id', 'code', 'name', 'address', 'is_active', 'is_placeholder']);
        $data['market_id'] = $this->f['cm']->id;
        $this->invalid(fn () => app(StockLocationService::class)->save($this->f['actor'], $data, $location), 'market_id');
        $this->invalid(fn () => app(StockLocationService::class)->archive($this->f['actor'], $location), 'location');
        Livewire::test(ManageStockLocations::class)->callAction('create', data: [
            'market_id' => $this->f['ca']->id, 'code' => 'CA-SECOND', 'name' => 'Second lieu',
            'address' => 'Adresse réelle de test', 'is_placeholder' => false, 'is_active' => true,
        ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('stock_locations', ['code' => 'CA-SECOND']);
        $data = $location->only(['market_id', 'code', 'name', 'address', 'is_active', 'is_placeholder']);
        $data['address'] = '';
        $this->invalid(fn () => app(StockLocationService::class)->save($this->f['actor'], $data, $location), 'address');
    }

    public function test_database_rejects_more_reserved_than_on_hand(): void
    {
        $this->expectException(QueryException::class);
        $this->stock()->update(['quantity_reserved' => 11]);
    }

    public function test_database_rejects_duplicate_location_variant_inventory(): void
    {
        $this->expectException(QueryException::class);
        Inventory::create(['stock_location_id' => $this->f['location']->id, 'product_variant_id' => $this->f['variant']->id]);
    }

    public function test_database_rejects_negative_physical_stock(): void
    {
        $this->expectException(QueryException::class);
        $this->stock()->update(['quantity_on_hand' => -1]);
    }

    private function stock(): Inventory
    {
        return $this->f['inventory']->fresh();
    }

    private function reserve(int $quantity, ?string $key = null): InventoryReservation
    {
        return app(ReservationService::class)->reserve($this->f['actor'], $this->f['ca']->id,
            [['inventory_id' => $this->f['inventory']->id, 'quantity' => $quantity]], $key ?? (string) Str::uuid());
    }

    private function adjustment(int $delta): array
    {
        return ['quantity_delta' => $delta, 'reason' => 'Correction de test', 'request_key' => (string) Str::uuid()];
    }

    private function adjust(array $data): Inventory
    {
        return app(InventoryService::class)->adjust($this->f['actor'], $this->f['inventory']->id, $data);
    }

    private function invalid(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Validation expected: '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
