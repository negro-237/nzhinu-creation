<?php

use Illuminate\Contracts\Console\Kernel;

// Never use the project's MySQL database for browser fixtures.
$expected = str_replace('\\', '/', dirname(__DIR__).'/storage/framework/testing/e2e.sqlite');
if (getenv('E2E_TESTING') !== '1' || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $expected) {
    throw new RuntimeException('E2E requires the dedicated testing SQLite file.');
}
if (! is_dir(dirname($expected))) {
    mkdir(dirname($expected), 0755, true);
}
if (! file_exists($expected)) {
    touch($expected);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== $expected || ! app()->environment('testing')) {
    throw new RuntimeException('E2E database isolation check failed. Clear the config cache before running browser tests.');
}
