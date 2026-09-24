<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\AreaRaceOperation;
use Tests\Support\AssociationDatabaseTestCase;

class AreaConcurrencyTest extends AssociationDatabaseTestCase
{
    public function test_archive_and_dependent_writes_serialize_in_both_orders(): void
    {
        if (getenv('ASSOCMAP_AREA_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Opt in to the two-connection synthetic schema race test.');
        }
        $schema = DB::selectOne('SELECT current_schema() AS name')->name;
        $this->assertMatchesRegularExpression('/^assocmap_test_membership_[a-f0-9]{16}$/D', $schema);
        DB::unprepared("ALTER TABLE area_units ADD COLUMN province varchar DEFAULT 'Cebu', ADD COLUMN address text, ADD COLUMN created_at timestamp DEFAULT now(), ADD COLUMN updated_at timestamp DEFAULT now();
            ALTER TABLE sub_units ADD COLUMN created_at timestamp DEFAULT now(), ADD COLUMN updated_at timestamp DEFAULT now();
            INSERT INTO area_units (id,name) VALUES (3,'Race municipality');
            INSERT INTO sub_units (id,area_unit_id,name,is_archived) VALUES (3,3,'Archived race barangay',true);
            SELECT setval(pg_get_serial_sequence('sub_units','id'),3);");
        // Other connections cannot see uncommitted fixtures. Commit only this random
        // test schema and remove it in finally; public records are never addressed.
        DB::commit();
        $process = null;
        try {
            foreach ([
                ['create_child', 'archive_parent'], ['restore_child', 'archive_parent'], ['move_child', 'archive_parent'],
                ['create_association', 'archive_child'], ['update_association', 'archive_child'], ['restore_association', 'archive_restore_child'],
            ] as [$dependent, $archive]) {
                foreach ([[$dependent, $archive], [$archive, $dependent]] as [$first, $second]) {
                    DB::statement("SET search_path TO \"$schema\"");
                    DB::table('associations')->where('id', '>', 2)->delete();
                    DB::table('associations')->update(['area_unit_id' => 1, 'sub_unit_id' => 1, 'is_archived' => $dependent === 'restore_association']);
                    DB::table('sub_units')->where('id', '>', 3)->delete();
                    DB::table('sub_units')->where('id', 2)->update(['area_unit_id' => 2]);
                    DB::table('sub_units')->update(['is_archived' => false]);
                    DB::table('sub_units')->where('id', 3)->update(['is_archived' => true]);
                    DB::table('area_units')->update(['is_archived' => false]);
                    DB::beginTransaction();
                    AreaRaceOperation::run($first);
                    $process = new Process([PHP_BINARY, base_path('tests/Support/area-race-worker.php'), $second], base_path(), ['ASSOCMAP_AREA_RACE_SCHEMA' => $schema]);
                    $process->setTimeout(35);
                    $process->start();
                    $blocked = false;
                    $deadline = microtime(true) + 10;
                    while ($process->isRunning() && microtime(true) < $deadline) {
                        if (preg_match('/PID:(\d+)/', $process->getOutput(), $match)) {
                            $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                            if ($blocked) {
                                break;
                            }
                        }
                        usleep(100000);
                    }
                    $this->assertTrue($blocked, "$second must wait for $first");
                    DB::commit();
                    $process->wait();
                    $this->assertSame(0, $process->getExitCode(), $process->getOutput());
                    $this->assertStringContainsString('RESULT:rejected', $process->getOutput(), "$second must recheck after $first commits");
                    $this->assertSame(0, DB::table('sub_units as s')->join('area_units as a', 'a.id', '=', 's.area_unit_id')->where('s.is_archived', false)->where('a.is_archived', true)->count());
                    $this->assertSame(0, DB::table('associations as r')->join('sub_units as s', 's.id', '=', 'r.sub_unit_id')->where('r.is_archived', false)->where('s.is_archived', true)->count());
                }
            }
        } finally {
            $process?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // The exact random schema was validated before any destructive cleanup.
            DB::statement("DROP SCHEMA \"$schema\" CASCADE");
            DB::statement('SET search_path TO public');
        }
    }
}
