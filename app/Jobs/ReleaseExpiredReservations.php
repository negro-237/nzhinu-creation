<?php

namespace App\Jobs;

use App\Enums\ReservationStatus;
use App\Models\InventoryReservation;
use App\Services\ReservationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(ReservationService $service): void
    {
        InventoryReservation::where('status', ReservationStatus::Active)->where('expires_at', '<=', now())
            ->select('id')->chunkById(100, function ($reservations) use ($service) {
                foreach ($reservations as $reservation) {
                    $service->expire($reservation->id);
                }
            });
    }
}
