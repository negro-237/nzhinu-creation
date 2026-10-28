<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$path = str_replace('\\', '/', (string) getenv('DB_DATABASE'));
$prefix = str_replace('\\', '/', dirname(__DIR__).'/storage/framework/testing/');
if (getenv('MVP_PRODUCTION_SMOKE') !== '1' || getenv('APP_ENV') !== 'production' || getenv('DB_CONNECTION') !== 'sqlite'
    || ! str_starts_with($path, $prefix) || ! preg_match('~/production-smoke-[a-f0-9]{16}/database\.sqlite\z~', $path)) {
    throw new RuntimeException('This probe requires the isolated production smoke database.');
}
require __DIR__.'/../vendor/autoload.php';
spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'Tests\\')) {
        throw new RuntimeException('Production must not autoload the test namespace.');
    }
}, true, true);
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (! app()->environment('production') || ! app()->configurationIsCached() || ! app()->routesAreCached()
    || str_replace('\\', '/', config('database.connections.sqlite.database')) !== $path) {
    throw new RuntimeException('Cached production configuration isolation failed.');
}
$kernel = $app->make(Kernel::class);
$results = [];
foreach (['/up', '/', '/boutique', '/admin/login'] as $uri) {
    $request = Request::create('https://localhost'.$uri);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 200 || $response->headers->get('X-Content-Type-Options') !== 'nosniff'
        || $response->headers->get('Strict-Transport-Security') !== 'max-age=31536000') {
        throw new RuntimeException('Production HTTP smoke failed at '.$uri.' ('.$response->getStatusCode().').');
    }
    foreach ($response->headers->getCookies() as $cookie) {
        if (! $cookie->isSecure()) {
            throw new RuntimeException('Insecure production cookie.');
        }
    }
    $results[$uri] = 200;
    $kernel->terminate($request, $response);
}
$request = Request::create('https://untrusted.invalid/');
$response = $kernel->handle($request);
if ($response->getStatusCode() !== 400) {
    throw new RuntimeException('Untrusted host was not rejected.');
}
$kernel->terminate($request, $response);
echo json_encode(['production' => true, 'config_cache' => true, 'route_cache' => true, 'test_namespace' => 'forbidden',
    'https_headers_and_cookies' => true, 'routes' => $results, 'untrusted_host' => 400], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
