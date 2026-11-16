<?php

namespace Tests\Feature;

use App\Enums\OrderEventKind;
use App\Enums\PaymentKind;
use App\Jobs\DeliverOrderNotification;
use App\Jobs\ExpandOrderNotifications;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderNotificationEvent;
use App\Models\StockLocation;
use App\Notifications\NewOrderStaffNotification;
use App\Notifications\OrderUpdateNotification;
use App\Services\FulfillmentService;
use App\Services\GuestCheckout;
use App\Services\InventoryService;
use App\Services\OrderJournal;
use App\Services\OrderNotifications;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
        config(['communications.order_staff_email' => null]);
        $this->f = $this->fixture();
    }

    public function test_new_order_alert_reaches_configured_shop_address_once_without_customer_email(): void
    {
        Notification::fake();
        config(['communications.order_staff_email' => 'petitotapondjou@gmail.com', 'communications.order_email_enabled' => false]);
        $order = $this->newOrder($this->f);
        // Orders created before email became mandatory remain supported.
        $order->update(['email' => null]);
        $this->assertNull($order->email);
        $service = app(OrderNotifications::class);
        foreach (OrderNotificationEvent::all() as $event) {
            $service->expand($event->id);
            $service->expand($event->id);
        }
        $this->assertSame(1, OrderNotificationDelivery::where('recipient_key', 'shop')->count());
        $delivery = OrderNotificationDelivery::where('recipient_key', 'shop')->firstOrFail();
        $service->deliver($delivery->id);
        $service->deliver($delivery->id);
        Notification::assertSentOnDemandTimes(NewOrderStaffNotification::class, 1);
        Notification::assertSentOnDemand(NewOrderStaffNotification::class, function ($notification, $channels, $recipient) use ($order) {
            $mail = $notification->toMail($recipient);
            $this->assertStringContainsString($order->number, $mail->subject);
            $this->assertSame(url('/admin/orders'), $mail->actionUrl);
            $this->assertStringNotContainsString($order->phone, $mail->render()->toHtml());

            return $recipient->routes['mail'] === 'petitotapondjou@gmail.com' && $channels === ['mail'];
        });
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_invalid_shop_address_is_ignored_and_removing_it_skips_pending_alert(): void
    {
        Notification::fake();
        config(['communications.order_staff_email' => 'invalid']);
        $this->newOrder($this->f);
        $service = app(OrderNotifications::class);
        $service->expand(OrderNotificationEvent::where('kind', 'created')->firstOrFail()->id);
        $this->assertSame(0, OrderNotificationDelivery::where('recipient_key', 'shop')->count());

        config(['communications.order_staff_email' => 'shop@example.test']);
        $this->newOrder($this->f);
        $service->expand(OrderNotificationEvent::where('kind', 'created')->latest('id')->firstOrFail()->id);
        $delivery = OrderNotificationDelivery::where('recipient_key', 'shop')->firstOrFail();
        config(['communications.order_staff_email' => null]);
        $service->deliver($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Notification::assertNothingSent();
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

    public function test_customer_receives_tracking_link_at_creation_and_each_fulfillment_stage(): void
    {
        Notification::fake();
        config(['communications.order_email_enabled' => true]);
        $order = $this->newOrder($this->f);
        $admin = $this->administrator();
        $this->verifyPayment($admin, $this->receipt($admin, $order));
        $fulfillment = app(FulfillmentService::class);
        $fulfillment->pack($admin, $order->id, (string) Str::uuid(), 'Préparation contrôlée');
        $fulfillment->handover($admin, $order->id, (string) Str::uuid(), [
            'method' => 'delivery', 'carrier' => 'Transporteur test', 'reference' => 'SUIVI-TEST',
            'note' => 'Expédition effectuée',
        ]);

        $service = app(OrderNotifications::class);
        foreach (OrderNotificationEvent::all() as $event) {
            $service->expand($event->id);
            $service->expand($event->id);
        }
        $deliveries = OrderNotificationDelivery::where('recipient_key', 'guest')->get();
        $this->assertEqualsCanonicalizing(['created', 'confirmed', 'payment_verified', 'packed', 'handed_over'],
            $deliveries->map(fn ($delivery) => $delivery->event->kind->value)->all());
        foreach ($deliveries as $delivery) {
            $service->deliver($delivery->id);
            $service->deliver($delivery->id);
        }
        Notification::assertSentOnDemandTimes(OrderUpdateNotification::class, 5);
        foreach (Notification::sent(new AnonymousNotifiable, OrderUpdateNotification::class) as $notification) {
            $mail = $notification->toMail(new AnonymousNotifiable);
            $this->assertStringContainsString('signature=', $mail->actionUrl);
            $this->get($mail->actionUrl)->assertOk();
            if ($notification->kind === OrderEventKind::HandedOver) {
                $this->assertSame(__('notifications.receipt_subject').' '.$order->number, $mail->subject);
                $this->assertCount(1, $mail->rawAttachments);
                $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);
                $this->assertStringStartsWith('%PDF-', $mail->rawAttachments[0]['data']);
                $html = $mail->render()->toHtml();
                foreach (['Votre reçu de commande', 'Produit inventaire', '100.00 CAD', '5.00 CAD', '105.00 CAD', 'SUIVI-TEST', 'Transporteur test'] as $text) {
                    $this->assertStringContainsString($text, $html);
                }
            }
        }
    }

    public function test_pickup_receipt_is_sent_only_after_handover_and_uses_item_snapshots(): void
    {
        $order = $this->newOrder($this->f, method: 'pickup');
        $admin = $this->administrator();
        $payment = $this->receipt($admin, $order);
        $this->verifyPayment($admin, $payment);
        $this->assertStringNotContainsString('Votre reçu de commande',
            (new OrderUpdateNotification($order->id, OrderEventKind::PaymentVerified, 'mail'))->toMail(new AnonymousNotifiable)->render()->toHtml());
        app(FulfillmentService::class)->pack($admin, $order->id, (string) Str::uuid(), 'Colis prêt');
        app(FulfillmentService::class)->handover($admin, $order->id, (string) Str::uuid(), [
            'method' => 'pickup', 'reference' => 'RETRAIT-TEST', 'note' => 'Colis remis au client',
        ]);
        $this->f['product']->update(['name' => 'Nouveau nom catalogue']);

        $mail = (new OrderUpdateNotification($order->id, OrderEventKind::HandedOver, 'mail'))->toMail(new AnonymousNotifiable);
        $html = $mail->render()->toHtml();
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertStringStartsWith('%PDF-', $mail->rawAttachments[0]['data']);
        foreach (['Votre reçu de commande', 'Produit inventaire', 'RETRAIT-TEST', '105.00 CAD'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Nouveau nom catalogue', $html);
        $this->assertStringNotContainsString($payment->reference, $html);
        $this->assertStringNotContainsString($order->phone, $html);
        $this->get($mail->actionUrl)->assertOk();
    }

    public function test_xaf_receipt_includes_verified_overpayment_refund_and_exact_net(): void
    {
        $location = StockLocation::where('market_id', $this->f['cm']->id)->firstOrFail();
        $location->update(['address' => 'Adresse test CM', 'is_placeholder' => false, 'is_active' => true]);
        $inventory = app(InventoryService::class)->create($this->f['actor'], [
            'stock_location_id' => $location->id, 'product_variant_id' => $this->f['variant']->id,
            'quantity_on_hand' => 3, 'low_stock_threshold' => 0, 'reason' => 'Stock CM test', 'request_key' => (string) Str::uuid(),
        ]);
        $order = app(GuestCheckout::class)->place(bin2hex(random_bytes(32)), [
            'market_id' => $this->f['cm']->id, 'currency' => 'XAF',
            'quote' => [['inventory_id' => $inventory->id, 'quantity' => 3, 'price_minor' => 25000]],
        ], ['request_key' => (string) Str::uuid(), 'customer_name' => 'Client reçu',
            'email' => 'client@example.test', 'phone' => '+237699000000', 'method' => 'delivery',
            'street' => 'Adresse test', 'city' => 'Douala']);
        app(OrderWorkflow::class)->confirm($this->f['actor'], $order->id, 0, [
            'shipping_fee' => '2000', 'tax_amount' => '0', 'reason' => 'Appel confirmé',
        ]);
        $admin = $this->administrator();
        $this->verifyPayment($admin, $this->receipt($admin, $order, '104000'));
        $this->verifyPayment($admin, $this->receipt($admin, $order, '27000', PaymentKind::Refund));
        app(FulfillmentService::class)->pack($admin, $order->id, (string) Str::uuid(), 'Colis contrôlé');
        app(FulfillmentService::class)->handover($admin, $order->id, (string) Str::uuid(), [
            'method' => 'delivery', 'carrier' => 'Transporteur test', 'reference' => 'EXPEDITION-CM',
            'note' => 'Expédition enregistrée',
        ]);
        $mail = (new OrderUpdateNotification($order->id, OrderEventKind::HandedOver, 'mail'))->toMail(new AnonymousNotifiable);
        $html = $mail->render()->toHtml();
        foreach (['75000 XAF', '2000 XAF', '77000 XAF', '104000 XAF', '27000 XAF', 'EXPEDITION-CM'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('77000.00', $html);
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertStringStartsWith('%PDF-', $mail->rawAttachments[0]['data']);
    }

    public function test_completed_order_mail_contains_a_downloadable_pdf_attachment_without_duplicate_delivery(): void
    {
        config(['communications.order_email_enabled' => true, 'mail.default' => 'array']);
        $order = $this->newOrder($this->f);
        $admin = $this->administrator();
        $this->verifyPayment($admin, $this->receipt($admin, $order));
        app(FulfillmentService::class)->pack($admin, $order->id, (string) Str::uuid(), 'Colis contrôlé');
        app(FulfillmentService::class)->handover($admin, $order->id, (string) Str::uuid(), [
            'method' => 'delivery', 'carrier' => 'Transporteur test',
            'reference' => 'COLIS-PDF', 'note' => 'Expédition effectuée',
        ]);
        $event = OrderNotificationEvent::where('kind', OrderEventKind::HandedOver)->firstOrFail();
        $service = app(OrderNotifications::class);
        $service->expand($event->id);
        $delivery = OrderNotificationDelivery::where('order_notification_event_id', $event->id)->where('recipient_key', 'guest')->firstOrFail();
        $service->deliver($delivery->id);
        $service->deliver($delivery->id);

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('client@example.test', $message->getTo()[0]->getAddress());
        $this->assertCount(1, $message->getAttachments());
        $attachment = $message->getAttachments()[0];
        $this->assertSame('application/pdf', $attachment->getContentType());
        $this->assertSame('attachment', $attachment->getDisposition());
        $this->assertSame('recu-'.strtolower($order->number).'.pdf', $attachment->getFilename());
        $this->assertStringStartsWith('%PDF-', $attachment->getBody());
    }
}
