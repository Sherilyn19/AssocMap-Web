<?php

use App\Exceptions\AssociationRuleException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AreaRaceOperation;

// This process can only target the randomly named synthetic schema from the parent test.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$schema = getenv('ASSOCMAP_AREA_RACE_SCHEMA');
if (! is_string($schema) || ! preg_match('/^assocmap_test_membership_[a-f0-9]{16}$/D', $schema)) {
    exit(2);
}
config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema]);
DB::purge('pgsql');
config(['association.lock_timeout_ms' => 12000, 'association.statement_timeout_ms' => 15000, 'association.operation_timeout_ms' => 20000]);
DB::statement("SET lock_timeout = '12s'");
DB::statement("SET statement_timeout = '15s'");
echo 'PID:'.DB::selectOne('SELECT pg_backend_pid() AS pid')->pid.PHP_EOL;
flush();
try {
    AreaRaceOperation::run($argv[1]);
    echo 'RESULT:accepted'.PHP_EOL;
} catch (ValidationException|AssociationRuleException $exception) {
    echo 'RESULT:rejected'.PHP_EOL;
} catch (Throwable $exception) {
    // Failure classification is enough; credentials and connection diagnostics stay private.
    echo 'RESULT:error:'.get_class($exception).PHP_EOL;
    exit(1);
}
