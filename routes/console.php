<?php

use App\Jobs\DeliverOrderNotification;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\OrderNotificationDelivery;
use App\Services\OrderNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ReleaseExpiredReservations)->everyMinute()->withoutOverlapping();
Schedule::command('orders:dispatch-notifications')->everyMinute()->withoutOverlapping();

Artisan::command('orders:dispatch-notifications', function () {
    app(OrderNotifications::class)->recover();
    $this->info('Notifications en attente remises en file.');
})->purpose('Reprendre les notifications de commande après un éventuel arrêt du worker.');

Artisan::command('orders:retry-notification {id}', function () {
    $delivery = OrderNotificationDelivery::findOrFail($this->argument('id'));
    if ($delivery->status !== 'failed') {
        $this->error('Seul un envoi en échec définitif peut être relancé.');

        return 1;
    }
    $delivery->update(['status' => 'pending', 'attempts' => 0, 'last_error' => null]);
    DeliverOrderNotification::dispatch($delivery->id);
    $this->info('Notification remise en file.');
})->purpose('Relancer explicitement une notification en échec après correction de la messagerie.');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
