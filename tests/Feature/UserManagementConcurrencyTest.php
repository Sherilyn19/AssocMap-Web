<?php

namespace Tests\Feature;

use App\Services\AdminUserManagementService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

class UserManagementConcurrencyTest extends UserManagementDatabaseTestCase
{
    public function test_concurrent_deactivation_retries_keep_one_status_change_and_audit(): void
    {
        DB::commit();
        $worker = null;
        try {
            DB::beginTransaction();
            app(AdminUserManagementService::class)->setActive(2, false, 1);
            $worker = new Process([PHP_BINARY, base_path('tests/Support/user-race-worker.php'), 'deactivate'], base_path(), ['ASSOCMAP_USER_RACE_SCHEMA' => $this->schema]);
            $worker->setTimeout(35);
            $worker->start();
            $blocked = false;
            $deadline = microtime(true) + 10;
            // Verify actual lock waiting so the test covers overlapping requests, not just retries.
            while ($worker->isRunning() && microtime(true) < $deadline) {
                if (preg_match('/PID:(\d+)/', $worker->getOutput(), $match)) {
                    $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                    if ($blocked) {
                        break;
                    }
                }
                usleep(100000);
            }
            $this->assertTrue($blocked);
            DB::commit();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getOutput());
            $this->assertStringContainsString('RESULT:accepted', $worker->getOutput());
            $this->assertFalse(DB::table('users')->where('id', 2)->value('is_active'));
            $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'DEACTIVATE')->count());
        } finally {
            $worker?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            UserManagementFixture::drop($this->schema);
        }
    }

    public function test_concurrent_creation_with_same_email_saves_one_account_and_one_audit(): void
    {
        DB::commit();
        $worker = null;
        try {
            DB::beginTransaction();
            app(AdminUserManagementService::class)->create(['name' => 'First Account', 'email' => 'race@example.test', 'password' => UserManagementFixture::PASSWORD, 'role_id' => 3, 'association_id' => 1], 1);
            $worker = new Process([PHP_BINARY, base_path('tests/Support/user-race-worker.php'), 'create'], base_path(), ['ASSOCMAP_USER_RACE_SCHEMA' => $this->schema]);
            $worker->setTimeout(35);
            $worker->start();
            $blocked = false;
            $deadline = microtime(true) + 10;
            while ($worker->isRunning() && microtime(true) < $deadline) {
                if (preg_match('/PID:(\d+)/', $worker->getOutput(), $match)) {
                    $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                    if ($blocked) {
                        break;
                    }
                }
                usleep(100000);
            }
            $this->assertTrue($blocked, 'The competing save must wait for the first transaction.');
            DB::commit();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getOutput());
            $this->assertStringContainsString('RESULT:rejected', $worker->getOutput());
            $this->assertSame(1, DB::table('users')->where('email', 'race@example.test')->count());
            $this->assertSame(1, DB::table('audit_logs')->where('action_type', 'CREATE')->count());
        } finally {
            $worker?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            UserManagementFixture::drop($this->schema);
        }
    }

    public function test_simultaneous_admin_changes_recheck_the_count_after_waiting(): void
    {
        // Commit only the synthetic fixture so a separate process can see it.
        // Every exit path removes this exact random schema in finally.
        DB::commit();
        $worker = null;
        try {
            foreach ([['demote', 'demote'], ['demote', 'deactivate'], ['deactivate', 'demote'], ['deactivate', 'deactivate'], ['rollback', 'deactivate']] as [$first, $second]) {
                DB::table('users')->whereIn('id', [1, 2])->update(['role_id' => 1, 'is_active' => true]);
                DB::table('audit_logs')->delete();
                DB::beginTransaction();
                $service = app(AdminUserManagementService::class);
                if ($first === 'demote') {
                    $service->update(1, $this->payload(1, ['role_id' => 2]), 1);
                } else {
                    $service->setActive(1, false, 2);
                }
                $worker = new Process([PHP_BINARY, base_path('tests/Support/user-race-worker.php'), $second], base_path(), ['ASSOCMAP_USER_RACE_SCHEMA' => $this->schema]);
                $worker->setTimeout(35);
                $worker->start();
                $blocked = false;
                $deadline = microtime(true) + 10;
                while ($worker->isRunning() && microtime(true) < $deadline) {
                    if (preg_match('/PID:(\d+)/', $worker->getOutput(), $match)) {
                        $blocked = (bool) DB::selectOne('SELECT cardinality(pg_blocking_pids(?)) > 0 AS blocked', [(int) $match[1]])->blocked;
                        if ($blocked) {
                            break;
                        }
                    }
                    usleep(100000);
                }
                $this->assertTrue($blocked, "$second must wait while $first holds the shared administrator lock.");
                $first === 'rollback' ? DB::rollBack() : DB::commit();
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
                $this->assertStringContainsString($first === 'rollback' ? 'RESULT:accepted' : 'RESULT:rejected', $worker->getOutput());
                $this->assertSame(1, DB::table('users')->where('role_id', 1)->where('is_active', true)->count());
                $this->assertSame(1, DB::table('audit_logs')->count());
            }
        } finally {
            $worker?->stop();
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            UserManagementFixture::drop($this->schema);
        }
    }
}
