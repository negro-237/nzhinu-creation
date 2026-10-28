<?php

namespace App\Services;

use App\Enums\OrderEventKind;
use App\Enums\StaffPermission;
use App\Jobs\DeliverOrderNotification;
use App\Jobs\ExpandOrderNotifications;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderNotificationEvent;
use App\Models\User;
use App\Notifications\OrderUpdateNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrderNotifications
{
    /** Store the outbox event inside the same transaction as the business change. */
    public function enqueue(OrderHistory $history, OrderEventKind $kind): void
    {
        $event = OrderNotificationEvent::firstOrCreate(['order_history_id' => $history->id], ['kind' => $kind]);
        ExpandOrderNotifications::dispatch($event->id)->afterCommit();
    }

    public function expand(int $eventId): void
    {
        DB::transaction(function () use ($eventId) {
            $event = OrderNotificationEvent::whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($event->expanded_at) {
                return;
            }
            $order = Order::findOrFail($event->history->order_id);
            foreach (User::permission(StaffPermission::ViewOrders->value)->orderBy('id')->get() as $user) {
                if ($user->can(StaffPermission::AccessAdmin->value)) {
                    $this->delivery($event, 'staff:'.$user->id, 'database', $user->id);
                }
            }
            if ($event->kind->customerVisible() && config('communications.order_email_enabled') && $order->email) {
                $this->delivery($event, 'guest', 'mail', null);
            }
            $event->update(['expanded_at' => now()]);
        }, attempts: 3);
    }

    private function delivery(OrderNotificationEvent $event, string $recipient, string $channel, ?int $user): void
    {
        $delivery = OrderNotificationDelivery::firstOrCreate(['order_notification_event_id' => $event->id, 'recipient_key' => $recipient, 'channel' => $channel],
            ['notification_id' => (string) Str::uuid(), 'user_id' => $user, 'status' => 'pending']);
        DeliverOrderNotification::dispatch($delivery->id)->afterCommit();
    }

    public function deliver(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $delivery = OrderNotificationDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($delivery->status !== 'pending') {
                    return;
                }
                $event = $delivery->event;
                $order = Order::findOrFail($event->history->order_id);
                $user = $delivery->user_id ? User::find($delivery->user_id) : null;
                $allowed = $delivery->channel === 'database'
                    ? $user && $user->can(StaffPermission::ViewOrders->value) && $user->can(StaffPermission::AccessAdmin->value)
                    : config('communications.order_email_enabled') && $order->email && $event->kind->customerVisible();
                if (! $allowed) {
                    $delivery->update(['status' => 'skipped']);

                    return;
                }
                $notification = new OrderUpdateNotification($order->id, $event->kind, $delivery->channel);
                $notification->id = $delivery->notification_id;
                // This job already provides durable queueing. Do not enqueue another notification job.
                if ($delivery->channel === 'database') {
                    Notification::sendNow($user, $notification);
                } else {
                    Notification::route('mail', $order->email)->notifyNow($notification);
                }
                $delivery->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => $delivery->attempts + 1, 'last_error' => null]);
            }, attempts: 1);
        } catch (\Throwable $exception) {
            // Persist only the error class, never SMTP credentials, contact details or signed links.
            DB::transaction(function () use ($id, $exception) {
                $delivery = OrderNotificationDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($delivery->status !== 'pending') {
                    return;
                }
                $attempts = $delivery->attempts + 1;
                $delivery->update(['attempts' => $attempts, 'last_error' => class_basename($exception), 'status' => $attempts >= 5 ? 'failed' : 'pending']);
            });
            throw new \RuntimeException('Échec de notification ; consulter le registre des livraisons.');
        }
    }

    /** Scheduler recovery for a crash between commit and queue dispatch. */
    public function recover(): void
    {
        OrderNotificationEvent::whereNull('expanded_at')->select('id')->chunkById(100, function ($events) {
            foreach ($events as $event) {
                ExpandOrderNotifications::dispatch($event->id);
            }
        });
        OrderNotificationDelivery::where('status', 'pending')->select('id')->chunkById(100, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                DeliverOrderNotification::dispatch($delivery->id);
            }
        });
    }
}
