<?php

// Run only against the random test schema supplied by GisConcurrencyTest.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = getenv('ASSOCMAP_GIS_RACE_SCHEMA');
if (! is_string($schema) || ! preg_match('/^assocmap_test_membership_[a-f0-9]{16}$/D', $schema)) exit(2);
config(['database.default' => 'pgsql', 'database.connections.pgsql.search_path' => $schema,
    'association.lock_timeout_ms' => 15000, 'association.statement_timeout_ms' => 20000, 'association.operation_timeout_ms' => 30000]);
Illuminate\Support\Facades\DB::purge('pgsql');
echo 'PID:'.Illuminate\Support\Facades\DB::selectOne('SELECT pg_backend_pid() AS id')->id.PHP_EOL;
flush();
try {
    $data = ['association_id' => 1, 'location_name' => 'Second edit', 'latitude' => 11, 'longitude' => 124, 'revision' => getenv('ASSOCMAP_GIS_RACE_REVISION')];
    match ($argv[1]) {
        'archive' => app(App\Services\AssociationManagementService::class)->archive(App\Models\Association::findOrFail(1), 1),
        'create' => app(App\Services\GisManagementService::class)->create($data, 1),
        'update' => app(App\Services\GisManagementService::class)->update(1, $data, 1),
        default => throw new RuntimeException('Invalid test operation.'),
    };
    echo 'RESULT:saved';
} catch (Illuminate\Validation\ValidationException $error) {
    echo 'RESULT:rejected';
} catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
    if ($error->getStatusCode() !== 409) exit(3);
    echo 'RESULT:rejected';
} catch (Throwable $error) {
    echo 'RESULT:failed '.$error::class;
    exit(4);
}
