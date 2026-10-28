<?php

use App\Enums\PaymentKind;
use App\Models\User;
use App\Services\FulfillmentService;
use App\Services\GuestCheckout;
use App\Services\InventoryService;
use App\Services\ManualPaymentService;
use App\Services\OrderNotifications;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'nzhinu_verify_')) {
    fwrite(STDERR, 'Concurrency worker requires the isolated MySQL test database.');
    exit(2);
}
$payload = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = User::findOrFail($payload['actor']);
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
echo "START\n";
flush();
try {
    if ($payload['mode'] === 'deliver_notification') {
        app(OrderNotifications::class)->deliver($payload['delivery']);
        echo json_encode(['result' => 'ok', 'id' => $payload['delivery'], 'pid' => getmypid()], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $result = match ($payload['mode']) {
        'verify_payment' => app(ManualPaymentService::class)->verify($actor, $payload['payment'], $payload['key'], ['source' => 'bank_statement', 'note' => 'Contrôle indépendant de concurrence']),
        'record_refund' => app(ManualPaymentService::class)->record($actor, $payload['order'], $payload['key'], $payload['data'], PaymentKind::Refund),
        'handover' => app(FulfillmentService::class)->handover($actor, $payload['order'], $payload['key'], $payload['data']),
        'checkout' => app(GuestCheckout::class)->place($payload['owner'], $payload['draft'], $payload['data']),
        'reserve' => app(ReservationService::class)->reserve($actor, $payload['market'], $payload['lines'], $payload['key']),
        'release' => app(ReservationService::class)->release($actor, $payload['reservation'], 'Concurrent release', 1),
        'expire' => app(ReservationService::class)->expire($payload['reservation']),
        'adjust' => app(InventoryService::class)->adjust($actor, $payload['inventory'], [
            'quantity_delta' => -1, 'reason' => 'Concurrent correction', 'request_key' => $payload['key'],
        ]),
    };
    echo json_encode(['result' => 'ok', 'id' => $result->id, 'pid' => getmypid()], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException $exception) {
    echo json_encode(['result' => 'rejected', 'errors' => array_keys($exception->errors()), 'pid' => getmypid()], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
