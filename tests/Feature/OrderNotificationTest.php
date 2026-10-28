<?php

namespace Tests\Feature;

use App\Enums\OrderEventKind;
use App\Jobs\DeliverOrderNotification;
use App\Jobs\ExpandOrderNotifications;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderNotificationEvent;
use App\Notifications\OrderUpdateNotification;
use App\Services\OrderJournal;
use App\Services\OrderNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InventoryFixture;
use Tests\Support\OrderFixture;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use InventoryFixture, OrderFixture, RefreshDatabase;

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        $this->f = $this->fixture();
    }

    public function test_outbox_is_transactional_and_initial_submission_retry_does_not_duplicate_events(): void
    {
        $order = $this->newOrder($this->f);
        $this->assertDatabaseCount('order_notification_events', 2);
        $before = $order->histories()->count();
        try {
            DB::transaction(function () use ($order) {
                app(OrderJournal::class)->record($order, null, 'Opération annulée', event: OrderEventKind::Packed);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('order_notification_events', 2);
        $this->assertSame($before, $order->histories()->count());
        $event = OrderNotificationEvent::firstOrFail();
        app(OrderNotifications::class)->enqueue($event->history, OrderEventKind::Created);
        $this->assertDatabaseCount('order_notification_events', 2);
    }

    public function test_database_notifications_are_idempotent_and_permissions_are_rechecked_at_delivery(): void
    {
        $admin = $this->administrator();
        $this->newOrder($this->f);
        $service = app(OrderNotifications::class);
        $event = OrderNotificationEvent::firstOrFail();
        $service->expand($event->id);
        $service->expand($event->id);
        $this->assertDatabaseCount('order_notification_deliveries', 2);
        $managerDelivery = OrderNotificationDelivery::where('user_id', $this->f['actor']->id)->firstOrFail();
        $this->f['actor']->syncRoles([]);
        $service->deliver($managerDelivery->id);
        $this->assertSame('skipped', $managerDelivery->fresh()->status);
        $adminDelivery = OrderNotificationDelivery::where('user_id', $admin->id)->firstOrFail();
        $service->deliver($adminDelivery->id);
        $service->deliver($adminDelivery->id);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['id' => $adminDelivery->notification_id]);
        $payload = DB::table('notifications')->value('data');
        $this->assertStringNotContainsString('+1514', $payload);
        $this->assertStringNotContainsString('signature=', $payload);
    }

    public function test_optional_email_contains_only_a_private_link_and_no_contact_or_payment_references(): void
    {
        Notification::fake();
        config(['communications.order_email_enabled' => true]);
        $order = $this->newOrder($this->f);
        $order->update(['email' => 'client@example.test']);
        $event = OrderNotificationEvent::firstOrFail();
        $service = app(OrderNotifications::class);
        $service->expand($event->id);
        $delivery = OrderNotificationDelivery::where('channel', 'mail')->firstOrFail();
        $service->deliver($delivery->id);
        $service->deliver($delivery->id);
        Notification::assertSentOnDemandTimes(OrderUpdateNotification::class, 1);
        Notification::assertSentOnDemand(OrderUpdateNotification::class, function ($notification, $channels, $recipient) use ($order) {
            $mail = $notification->toMail($recipient);
            $this->assertStringContainsString('signature=', $mail->actionUrl);
            $this->get($mail->actionUrl)->assertOk();
            $this->assertStringNotContainsString($order->phone, $mail->render()->toHtml());

            return $recipient->routes['mail'] === 'client@example.test' && $channels === ['mail'];
        });
        $serialized = serialize(new DeliverOrderNotification($delivery->id));
        $this->assertStringNotContainsString('client@example.test', $serialized);
        $this->assertStringNotContainsString('signature=', $serialized);
    }

    public function test_disabled_email_or_missing_address_creates_no_mail_delivery_and_scheduler_recovers_outbox(): void
    {
        $order = $this->newOrder($this->f);
        $order->update(['email' => 'client@example.test']);
        $service = app(OrderNotifications::class);
        $service->expand(OrderNotificationEvent::firstOrFail()->id);
        $this->assertSame(0, OrderNotificationDelivery::where('channel', 'mail')->count());
        Queue::fake();
        $service->recover();
        Queue::assertPushed(ExpandOrderNotifications::class);
        Queue::assertPushed(DeliverOrderNotification::class);
        config(['communications.order_email_enabled' => true]);
        $order->update(['email' => null]);
        $service->expand(OrderNotificationEvent::latest('id')->firstOrFail()->id);
        $this->assertSame(0, OrderNotificationDelivery::where('channel', 'mail')->count());
    }

    public function test_delivery_failure_is_bounded_and_error_log_does_not_contain_sensitive_message(): void
    {
        $this->newOrder($this->f);
        $service = app(OrderNotifications::class);
        $service->expand(OrderNotificationEvent::firstOrFail()->id);
        $delivery = OrderNotificationDelivery::firstOrFail();
        Notification::shouldReceive('sendNow')->times(5)->andThrow(new \RuntimeException('SMTP-secret-and-private-link'));
        for ($i = 0; $i < 5; $i++) {
            try {
                $service->deliver($delivery->id);
                $this->fail('Failure expected');
            } catch (\RuntimeException $e) {
                $this->assertStringNotContainsString('SMTP-secret', $e->getMessage());
            }
        }
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame(5, $delivery->fresh()->attempts);
        $this->assertSame('RuntimeException', $delivery->fresh()->last_error);
        $service->deliver($delivery->id);
        $this->artisan('orders:retry-notification', ['id' => $delivery->id])->assertSuccessful();
        $this->assertSame('pending', $delivery->fresh()->status);
    }
}
