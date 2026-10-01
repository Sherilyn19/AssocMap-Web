<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Association;
use App\Services\AssociationManagementService;
use App\Services\GisIndexService;
use App\Services\GisManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\GisDatabaseTestCase;

final class GisConcurrencyTest extends GisDatabaseTestCase
{
    public function test_officer_revocation_while_waiting_for_parent_prevents_a_gis_write(): void
    {
        DB::unprepared('ALTER TABLE gis_locations ADD COLUMN location_name varchar, ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp');
        $schema = DB::selectOne('SELECT current_schema() AS name')->name;
        DB::commit();
        $worker = null;
        try {
            DB::statement('SET search_path TO "'.$schema.'"');
            DB::beginTransaction();
            DB::table('associations')->where('id', 1)->lockForUpdate()->first();
            DB::table('users')->where('id', 2)->update(['is_active' => false]);
            $worker = new Process([PHP_BINARY, base_path('tests/Support/gis-race-worker.php'), 'create'], base_path(), [
                'ASSOCMAP_GIS_RACE_SCHEMA' => $schema, 'ASSOCMAP_GIS_RACE_OFFICER' => '1',
            ]);
            $worker->setTimeout(45);
            $worker->start();
            $blocked = false;
            $deadline = microtime(true) + 20;
            while ($worker->isRunning() && microtime(true) < $deadline) {
                if (preg_match('/PID:(\d+)/', $worker->getOutput(), $match)) {
                    $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                    if ($blocked) {
                        break;
                    }
                }
                usleep(100000);
            }
            $this->assertTrue($blocked, 'The GIS request must wait for the concurrent transaction.');
            DB::commit();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getOutput());
            $this->assertStringContainsString('RESULT:rejected', $worker->getOutput());
            $this->assertSame(1, DB::table('gis_locations')->count());
            $this->assertSame(0, DB::table('audit_logs')->where('module', 'GIS')->count());
        } finally {
            $worker?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }

    public function test_saves_and_archival_wait_and_recheck_after_the_first_commit(): void
    {
        $schema = DB::selectOne('SELECT current_schema() AS name')->name;
        $this->assertMatchesRegularExpression('/^assocmap_test_membership_[a-f0-9]{16}$/D', $schema);
        DB::unprepared("ALTER TABLE gis_locations ADD COLUMN location_name varchar(255), ADD COLUMN latitude numeric, ADD COLUMN longitude numeric, ADD COLUMN created_at timestamp;
            UPDATE gis_locations SET location_name='Initial site', latitude=10, longitude=123, updated_at=now();");
        // Only the random fixture schema is committed so the second connection can see it.
        DB::commit();
        $worker = null;
        try {
            DB::statement('SET search_path TO "'.$schema.'"');
            foreach ([['update', 'update', 'rejected'], ['archive', 'update', 'rejected'], ['update', 'archive', 'saved'], ['archive', 'create', 'rejected'], ['create', 'archive', 'saved'],
                ['publish', 'update', 'rejected'], ['update', 'publish', 'rejected'], ['archive', 'publish', 'rejected'], ['publish', 'archive', 'saved'],
                ['publish', 'publish', 'saved'], ['publish', 'unpublish', 'rejected'], ['create', 'create', 'saved']] as [$first, $second, $expected]) {
                DB::table('associations')->where('id', 1)->update(['is_archived' => false]);
                DB::table('gis_locations')->where('id', 1)->update(['location_name' => 'Initial site', 'is_published' => ! in_array('publish', [$first, $second], true)]);
                $countBefore = DB::table('gis_locations')->count();
                $auditBefore = DB::table('audit_logs')->where('module', 'GIS')->count();
                $revision = app(GisIndexService::class)->overview()['records']->firstWhere('id', 1)['revision'];
                $data = ['association_id' => 1, 'location_name' => 'First edit', 'latitude' => 10, 'longitude' => 123, 'revision' => $revision, 'submission_token' => (string) Str::uuid()];
                DB::beginTransaction();
                match ($first) {
                    'archive' => app(AssociationManagementService::class)->archive(Association::findOrFail(1), 1),
                    'create' => app(GisManagementService::class)->create($data, 1),
                    'update' => app(GisManagementService::class)->update(1, $data, 1),
                    'publish' => app(GisManagementService::class)->publication(1, $revision, true, 1),
                };
                $worker = new Process([PHP_BINARY, base_path('tests/Support/gis-race-worker.php'), $second], base_path(), [
                    'ASSOCMAP_GIS_RACE_SCHEMA' => $schema, 'ASSOCMAP_GIS_RACE_REVISION' => $revision,
                    'ASSOCMAP_GIS_RACE_DUPLICATE' => $first === 'create' && $second === 'create' ? json_encode($data) : '',
                ]);
                $worker->setTimeout(45);
                $worker->start();
                $blocked = false;
                $deadline = microtime(true) + 20;
                while ($worker->isRunning() && microtime(true) < $deadline) {
                    if (preg_match('/PID:(\d+)/', $worker->getOutput(), $match)) {
                        $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                        if ($blocked) {
                            break;
                        }
                    }
                    usleep(100000);
                }
                $this->assertTrue($blocked, "$second must wait for $first");
                DB::commit();
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getOutput());
                $this->assertStringContainsString('RESULT:'.$expected, $worker->getOutput());
                if ($first === 'update' && $second === 'update') {
                    $this->assertSame('First edit', DB::table('gis_locations')->where('id', 1)->value('location_name'));
                } elseif (in_array('archive', [$first, $second], true)) {
                    $this->assertTrue((bool) DB::table('associations')->where('id', 1)->value('is_archived'));
                    $this->assertSame(0, DB::table('gis_locations')->where('association_id', 1)->where('is_published', true)->count());
                } elseif ($first === 'create') {
                    $this->assertSame($countBefore + 1, DB::table('gis_locations')->count());
                    $this->assertSame($auditBefore + 1, DB::table('audit_logs')->where('module', 'GIS')->count());
                } elseif ($first === 'publish') {
                    $this->assertTrue((bool) DB::table('gis_locations')->where('id', 1)->value('is_published'));
                    $this->assertSame($auditBefore + 1, DB::table('audit_logs')->where('module', 'GIS')->count());
                }
            }
        } finally {
            $worker?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }
}
