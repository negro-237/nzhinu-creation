<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$root = dirname(__DIR__);
$testRoot = $root.'/storage/framework/testing';
if (! is_dir($testRoot)) {
    mkdir($testRoot, 0755, true);
}
$directory = $testRoot.'/production-smoke-'.bin2hex(random_bytes(8));
mkdir($directory, 0755);
mkdir($directory.'/views', 0755);
touch($directory.'/database.sqlite');
$cacheDirectory = 'storage/framework/testing/'.basename($directory);
$environment = [
    'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://localhost',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'MVP_PRODUCTION_SMOKE' => '1', 'E2E_TESTING' => '0',
    'APP_CONFIG_CACHE' => $cacheDirectory.'/config.php', 'APP_ROUTES_CACHE' => $cacheDirectory.'/routes.php',
    'APP_EVENTS_CACHE' => $cacheDirectory.'/events.php', 'APP_PACKAGES_CACHE' => $cacheDirectory.'/packages.php', 'APP_SERVICES_CACHE' => $cacheDirectory.'/services.php',
    'VIEW_COMPILED_PATH' => $directory.'/views', 'DB_CONNECTION' => 'sqlite', 'DB_URL' => '', 'DB_DATABASE' => $directory.'/database.sqlite',
    'SESSION_DRIVER' => 'database', 'SESSION_SECURE_COOKIE' => 'true', 'SESSION_ENCRYPT' => 'true',
    'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'database', 'ORDER_EMAIL_ENABLED' => 'false', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
];
$exit = 1;
try {
    foreach (['migrate', 'db:seed', 'config:cache', 'route:cache', 'view:cache', 'event:cache'] as $command) {
        $arguments = [PHP_BINARY, 'artisan', $command, '--no-interaction'];
        if (in_array($command, ['migrate', 'db:seed'], true)) {
            $arguments[] = '--force';
        }
        $process = new Process($arguments, $root, $environment);
        $process->setTimeout(180)->mustRun();
        echo $command." : OK\n";
    }
    $probe = new Process([PHP_BINARY, 'tools/production-probe.php'], $root, $environment);
    $probe->setTimeout(120)->mustRun();
    echo $probe->getOutput();
    $exit = 0;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
} finally {
    // Windows: verify the resolved directory stays inside the dedicated test root before recursive cleanup.
    $resolved = realpath($directory);
    $parent = realpath($testRoot);
    if ($resolved && $parent && str_starts_with(strtolower($resolved), strtolower($parent).DIRECTORY_SEPARATOR)
        && preg_match('/\Aproduction-smoke-[a-f0-9]{16}\z/', basename($resolved))) {
        (new Filesystem)->deleteDirectory($resolved);
        echo "Environnement de recette production supprimé.\n";
    }
}
exit($exit);
