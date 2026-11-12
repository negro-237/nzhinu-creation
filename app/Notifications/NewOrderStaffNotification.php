<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderStaffNotification extends Notification
{
    public function __construct(public int $orderId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = Order::findOrFail($this->orderId);

        return (new MailMessage)
            ->subject(__('notifications.staff_created_subject').' '.$order->number)
            ->greeting(config('app.name'))
            ->line(__('notifications.staff_created'))
            ->line($order->number)
            ->action(__('notifications.staff_view'), url('/admin/orders'));
    }
}
