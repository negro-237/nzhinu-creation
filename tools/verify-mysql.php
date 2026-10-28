<?php

// Run only with a local MySQL account allowed to create disposable databases.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.mysql'));
$connection['url'] = null;
$database = 'nzhinu_verify_'.bin2hex(random_bytes(8));
$connection['database'] = null;
config(['database.connections.verify_server' => $connection]);
$server = DB::connection('verify_server');
$created = false;
$exitCode = 1;

try {
    $server->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $connection['database'] = $database;
    config(['database.default' => 'verify', 'database.connections.verify' => $connection]);

    foreach (['migrate', 'db:seed', 'db:seed', 'migrate:rollback', 'migrate'] as $command) {
        $result = Artisan::call($command, ['--force' => true, '--no-interaction' => true]);
        echo Artisan::output();
        if ($result !== 0) {
            throw new RuntimeException("Échec : $command");
        }
    }

    $arguments = [PHP_BINARY, 'artisan', 'test', '--compact'];
    if (isset($argv[1])) {
        $arguments[] = '--filter='.$argv[1];
    }
    $process = new Process($arguments, dirname(__DIR__), [
        'APP_ENV' => 'testing',
        'VERIFY_MYSQL' => '1',
        'DB_CONNECTION' => 'mysql',
        'DB_URL' => '',
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => $database,
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
    ]);
    $process->setTimeout(600);
    $exitCode = $process->run(fn ($type, $output) => print ($output));
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
} finally {
    DB::disconnect('verify');
    if ($created) {
        $server->statement("DROP DATABASE `$database`");
        echo "Base temporaire supprimée.\n";
    }
}

exit($exitCode);
