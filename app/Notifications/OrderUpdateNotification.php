<?php

namespace App\Notifications;

use App\Enums\OrderEventKind;
use App\Models\Order;
use App\Services\OrderAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public OrderEventKind $kind, public string $channel)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toDatabase(object $notifiable): array
    {
        $order = Order::findOrFail($this->orderId);

        return [...FilamentNotification::make()->title(e($order->number))->body(e($this->kind->message()))->info()
            ->actions([Action::make('orders')->label('Consulter les commandes')->url(url('/admin/orders'))])->getDatabaseMessage(), 'order_id' => $order->id, 'event' => $this->kind->value];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = Order::findOrFail($this->orderId);

        return (new MailMessage)->subject(__('notifications.subject').' '.$order->number)->greeting(config('app.name'))
            ->line($this->kind->message())->action(__('notifications.view'), app(OrderAccess::class)->link($order))
            ->line(__('notifications.private_link'))->line(__('notifications.no_payment_link'));
    }
}
