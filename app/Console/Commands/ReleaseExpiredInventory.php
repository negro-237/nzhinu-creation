<?php

namespace App\Console\Commands;

use App\Jobs\ReleaseExpiredReservations;
use Illuminate\Console\Command;

class ReleaseExpiredInventory extends Command
{
    protected $signature = 'inventory:release-expired {--sync : Exécuter immédiatement sans file d’attente}';

    protected $description = 'Libérer les réservations arrivées à expiration';

    public function handle(): int
    {
        $this->option('sync') ? ReleaseExpiredReservations::dispatchSync() : ReleaseExpiredReservations::dispatch();
        $this->info('Traitement des expirations lancé.');

        return self::SUCCESS;
    }
}
