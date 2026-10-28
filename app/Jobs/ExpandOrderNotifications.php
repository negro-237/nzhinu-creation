<?php

namespace App\Jobs;

use App\Services\OrderNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpandOrderNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(OrderNotifications $service): void
    {
        $service->expand($this->eventId);
    }
}
