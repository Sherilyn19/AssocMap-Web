<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Manual browser checks use the real routes against a disposable schema, never public records.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (PHP_SAPI !== 'cli' || ! app()->environment(['local', 'testing'])) {
    exit(2);
}
$statePath = storage_path('app/gis-acceptance.json');
$action = $argv[1] ?? '';
try {
    if ($action === 'setup') {
        if (file_exists($statePath)) {
            throw new RuntimeException('An acceptance schema already exists. Clean it up first.');
        }
        $schema = 'assocmap_gis_acceptance_'.bin2hex(random_bytes(8));
        DB::transaction(function () use ($schema): void {
            DB::statement('CREATE SCHEMA "'.$schema.'"');
            DB::statement('SET LOCAL search_path TO "'.$schema.'"');
            foreach (glob(database_path('migrations/*.php')) as $migration) {
                (require $migration)->up();
            }
            foreach (['System Administrator', 'Field Officer', 'Association Member'] as $role) {
                DB::table('roles')->insert(['role_name' => $role]);
            }
            DB::table('users')->insert(['name' => 'GIS Acceptance Administrator', 'email' => 'gis-admin@example.test', 'password' => Hash::make('GisCheck!2026'), 'role_id' => 1, 'is_active' => true]);
            DB::table('users')->insert(['name' => 'GIS Acceptance Officer', 'email' => 'gis-officer@example.test', 'password' => Hash::make('GisCheck!2026'), 'role_id' => 2, 'is_active' => true]);
            DB::table('area_units')->insert(['name' => 'Cebu North']);
            DB::table('sub_units')->insert(['name' => 'Coastal Barangay', 'area_unit_id' => 1]);
            DB::table('program_components')->insert(['name' => 'Capture Fisheries']);
            DB::table('statuses')->insert(['status_name' => 'Active']);
            foreach ([false, true] as $archived) {
                DB::table('associations')->insert(['name' => $archived ? 'Archived Coastal Association' : 'Coastal Association', 'area_unit_id' => 1, 'sub_unit_id' => 1, 'program_component_id' => 1, 'status_id' => 1, 'field_officer_id' => 2, 'is_archived' => $archived,
                    'address' => 'Acceptance fixture', 'date_joined' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('gis_locations')->insert(['association_id' => 1, 'location_name' => 'Landing area', 'latitude' => '11.274500000001', 'longitude' => '124.0524']);
            DB::table('gis_locations')->insert(['association_id' => 2, 'location_name' => 'Archived landing area', 'latitude' => 11.27, 'longitude' => 124.05]);
        });
        file_put_contents($statePath, json_encode(['schema' => $schema], JSON_THROW_ON_ERROR));
        echo 'Acceptance schema ready. Synthetic login: gis-admin@example.test / GisCheck!2026'.PHP_EOL;
    } elseif (in_array($action, ['inspect', 'upgrade', 'cleanup'], true)) {
        $schema = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR)['schema'];
        if (! preg_match('/^assocmap_gis_acceptance_[a-f0-9]{16}$/D', $schema)) {
            throw new RuntimeException('Invalid acceptance schema.');
        }
        DB::statement('SET search_path TO "'.$schema.'"');
        if ($action === 'upgrade') {
            DB::transaction(function (): void {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('gis_locations', 'project_id')) {
                    (require database_path('migrations/2026_09_29_000001_add_gis_project_and_archive.php'))->up();
                }
                DB::table('projects')->updateOrInsert(['association_id' => 1, 'title' => 'Coastal livelihood project'], ['commodity_type' => 'Milkfish', 'is_archived' => false]);
                DB::table('users')->updateOrInsert(['email' => 'gis-member@example.test'], ['name' => 'GIS Acceptance Member', 'password' => Hash::make('GisCheck!2026'), 'role_id' => 3, 'association_id' => 1, 'is_active' => true]);
            });
            echo 'Acceptance schema upgraded with project and member fixtures.'.PHP_EOL;
        } elseif ($action === 'cleanup') {
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
            unlink($statePath);
            echo 'Acceptance schema removed.'.PHP_EOL;
        } else {
            echo json_encode(['locations' => DB::table('gis_locations')->select('id', 'location_name', 'latitude', 'longitude', 'is_published')->get(),
                'audits' => DB::table('audit_logs')->select('action_type', 'module', 'record_id')->orderBy('id')->get()], JSON_PRETTY_PRINT).PHP_EOL;
        }
    } else {
        throw new RuntimeException('Use setup, upgrade, inspect, or cleanup.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Acceptance operation failed: '.$error::class.' ('.$error->getCode().').'.PHP_EOL);
    if ($error instanceof QueryException && isset($error->errorInfo[2])) {
        fwrite(STDERR, $error->errorInfo[2].PHP_EOL);
    }
    exit(1);
}
