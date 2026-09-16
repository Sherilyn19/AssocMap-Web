<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AssociationDeadlineException;
use Closure;
use Illuminate\Support\Facades\DB;

/** Short database scopes: a failed write and its audit must roll back together. */
final class AssociationDatabase
{
    private ?float $deadline = null;

    private \WeakMap $observed;

    public function __construct()
    {
        $this->observed = new \WeakMap;
    }

    public function run(Closure $operation): mixed
    {
        if ($this->deadline !== null) {
            return $operation();
        }
        $connection = DB::connection();
        if (! isset($this->observed[$connection])) {
            // One callback per connection, inactive outside this service's operation scope.
            $connection->beforeExecuting(function (): void {
                if ($this->deadline !== null && microtime(true) >= $this->deadline) {
                    throw new AssociationDeadlineException('Association operation budget exceeded.');
                }
            });
            $this->observed[$connection] = true;
        }
        $this->deadline = microtime(true) + max(100, (int) config('association.operation_timeout_ms')) / 1000;
        $nested = $connection->transactionLevel() > 0;
        try {
            // No automatic retry: after connection loss a commit outcome can be unknown.
            return $connection->transaction(function () use ($connection, $operation, $nested) {
                $previous = null;
                if ($connection->getDriverName() === 'pgsql') {
                    if ($nested) {
                        $previous = $connection->selectOne("SELECT current_setting('statement_timeout') AS statement, current_setting('lock_timeout') AS lock");
                    }
                    $connection->selectOne("SELECT set_config('statement_timeout', ?, true), set_config('lock_timeout', ?, true)", [
                        max(1, (int) config('association.statement_timeout_ms')).'ms',
                        max(1, (int) config('association.lock_timeout_ms')).'ms',
                    ]);
                }
                $result = $operation();
                if (microtime(true) >= $this->deadline) {
                    throw new AssociationDeadlineException('Association operation budget exceeded.');
                }
                if ($previous) {
                    $connection->selectOne("SELECT set_config('statement_timeout', ?, true), set_config('lock_timeout', ?, true)", [$previous->statement, $previous->lock]);
                }

                // SET LOCAL expires at commit/rollback: pooled connections retain no new settings.
                return $result;
            }, 1);
        } finally {
            $this->deadline = null;
        }
    }
}
