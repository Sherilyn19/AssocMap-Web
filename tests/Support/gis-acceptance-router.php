<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Serve the actual application with isolated storage for manual acceptance only.
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    exit;
}
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath($root.'/public/'.$path);
if ($asset && str_starts_with($asset, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($asset) && pathinfo($asset, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$state = json_decode(file_get_contents($root.'/storage/app/gis-acceptance.json'), true);
$schema = $state['schema'] ?? '';
if (! preg_match('/^assocmap_gis_acceptance_[a-f0-9]{16}$/D', $schema)) {
    exit;
}
config(['app.debug' => false, 'database.connections.pgsql.search_path' => $schema, 'database.default' => 'pgsql',
    'session.driver' => 'file', 'session.cookie' => 'gis_acceptance_session', 'cache.default' => 'array',
    'gis.storage_path' => storage_path('app/gis-acceptance-work')]);
DB::purge('pgsql');
app(Vite::class)->useHotFile(storage_path('app/gis-acceptance-no-hot'));
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
