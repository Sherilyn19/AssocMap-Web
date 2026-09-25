<?php

use App\Exceptions\AssociationRuleException;
use App\Services\AdminUserManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\UserManagementFixture;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
UserManagementFixture::connect((string) getenv('ASSOCMAP_USER_RACE_SCHEMA'));
config(['association.lock_timeout_ms' => 12000, 'association.statement_timeout_ms' => 15000, 'association.operation_timeout_ms' => 20000]);
echo 'PID:'.DB::selectOne('SELECT pg_backend_pid() AS pid')->pid.PHP_EOL;
flush();
try {
    $service = app(AdminUserManagementService::class);
    if (($argv[1] ?? '') === 'create') {
        $service->create(['name' => 'Race Account', 'email' => 'race@example.test', 'password' => UserManagementFixture::PASSWORD, 'role_id' => 3, 'association_id' => 1], 1);
    } elseif (($argv[1] ?? '') === 'demote') {
        $user = DB::table('users')->where('id', 2)->first();
        $service->update(2, ['name' => $user->name, 'email' => $user->email, 'role_id' => 2], 2);
    } elseif (($argv[1] ?? '') === 'deactivate') {
        $service->toggleActive(2, 1);
    } else {
        exit(2);
    }
    echo 'RESULT:accepted'.PHP_EOL;
} catch (AssociationRuleException|ValidationException $error) {
    echo 'RESULT:rejected'.PHP_EOL;
} catch (Throwable $error) {
    echo 'RESULT:error:'.get_class($error).PHP_EOL;
    exit(1);
}
