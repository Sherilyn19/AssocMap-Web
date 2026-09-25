<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Tests\Support\UserManagementFixture;

// Start only with PHP's local server: php -S 127.0.0.1:8126 -t public tests/Support/user-browser-router.php
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$public = realpath(dirname(__DIR__, 2).'/public');
$asset = realpath($public.'/'.ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
if ($asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset) && pathinfo($asset, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$schema = trim((string) file_get_contents(storage_path('app/user-browser-schema.txt')));
UserManagementFixture::validate($schema);
// Configure isolation before providers create the cache and rate-limiter services.
// Changing the connection after bootstrap leaves those services holding the old connection.
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($schema): void {
    $app['config']->set([
        'app.env' => 'testing', 'app.debug' => false,
        'database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema,
        'session.driver' => 'database', 'session.connection' => 'pgsql', 'session.cookie' => 'assocmap_user_qa',
        'session.domain' => null, 'session.secure' => false,
        'cache.default' => 'database', 'cache.limiter' => 'database',
        'cache.stores.database.connection' => 'pgsql', 'cache.prefix' => $schema,
    ]);
});
$app->make(Kernel::class)->bootstrap();
app(Vite::class)->useHotFile(storage_path('app/user-browser-no-hot'));
$app->handleRequest(Request::capture());
