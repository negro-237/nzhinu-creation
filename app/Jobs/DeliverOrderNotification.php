<?php

namespace App\Jobs;

use App\Services\OrderNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(OrderNotifications $service): void
    {
        $service->deliver($this->deliveryId);
    }
}
