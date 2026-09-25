<?php

use App\Services\AdminUserManagementService;
use App\Services\LoginAttemptLimiter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\UserManagementFixture;

// CLI-only helper for disposable browser QA. It never targets the public schema.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$file = storage_path('app/user-browser-schema.txt');
$action = $argv[1] ?? '';
if ($action !== 'create') {
    $schema = trim((string) file_get_contents($file));
    UserManagementFixture::validate($schema);
    $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($schema): void {
        $app['config']->set([
            'database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema,
            'cache.default' => 'database', 'cache.limiter' => 'database',
            'cache.stores.database.connection' => 'pgsql', 'cache.prefix' => $schema,
        ]);
    });
}
$app->make(Kernel::class)->bootstrap();
if ($action === 'create') {
    if (file_exists($file)) {
        throw new RuntimeException('Clean up the existing browser fixture before creating another.');
    }
    $schema = 'assocmap_test_users_'.bin2hex(random_bytes(8));
    UserManagementFixture::connect($schema);
    DB::transaction(fn () => UserManagementFixture::create($schema));
    file_put_contents($file, $schema);
    echo 'Synthetic browser fixture created.'.PHP_EOL;
    exit;
}
$schema = trim((string) file_get_contents($file));
UserManagementFixture::connect($schema);
if ($action === 'drop') {
    UserManagementFixture::drop($schema);
    unlink($file);
} elseif ($action === 'reset-password') {
    app(AdminUserManagementService::class)->update(3, ['name' => 'Officer', 'email' => 'officer@example.test', 'role_id' => 2, 'password' => 'Replacement-Password-2026'], 1);
} elseif ($action === 'deactivate') {
    // An unassigned synthetic account permits exercising deactivation independently of reassignment.
    app(AdminUserManagementService::class)->setActive(2, false, 1);
} elseif ($action === 'demote') {
    app(AdminUserManagementService::class)->update(2, ['name' => 'Admin Two', 'email' => 'admin2@example.test', 'role_id' => 2], 1);
} elseif ($action === 'status') {
    echo json_encode([
        'sessions' => DB::table('sessions')->count(),
        'cache_entries' => DB::table('cache')->count(),
        'audit_events' => DB::table('audit_logs')->count(),
    ]).PHP_EOL;
} elseif ($action === 'prepare-throttle') {
    // Prepare five synthetic failures, then verify the next submission in the browser.
    // This avoids confusing slow browser automation with expiry of the one-minute window.
    $request = Request::create('/login', 'POST', ['email' => 'officer@example.test'], server: ['REMOTE_ADDR' => '127.0.0.1']);
    $limiter = app(LoginAttemptLimiter::class);
    $limiter->clearFailures($request);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $limiter->recordFailure($request);
    }
} else {
    throw new InvalidArgumentException('Unknown browser fixture action.');
}
echo 'Synthetic browser fixture action completed.'.PHP_EOL;
