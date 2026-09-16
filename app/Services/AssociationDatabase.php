<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AssociationDeadlineException;
use App\Support\AssociationRequestContext;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/** Each short scope owns its rollback. A business write and its audit remain atomic. */
final class AssociationDatabase
{
    private \WeakMap $deadlines;

    private \WeakMap $observed;

    public function __construct()
    {
        $this->deadlines = new \WeakMap;
        $this->observed = new \WeakMap;
    }

    public function run(Closure $operation, ?Connection $connection = null, string $stage = 'operation'): mixed
    {
        $context = app(AssociationRequestContext::class);
        $connection ??= DB::connection();
        if (isset($this->deadlines[$connection])) {
            return $operation(); // Nested service methods share the existing scope and rollback.
        }

        return $context->measure($stage, fn () => $this->withinTransaction($operation, $connection));
    }

    private function assertWithinBudget(Connection $connection): void
    {
        app(AssociationRequestContext::class)->assertWithinBudget();
        if (hrtime(true) >= $this->deadlines[$connection]) {
            throw new AssociationDeadlineException('Association operation budget exceeded.');
        }
    }

    private function withinTransaction(Closure $operation, Connection $connection): mixed
    {
        $context = app(AssociationRequestContext::class);
        $budget = max(1, (int) config('association.operation_timeout_ms'));
        if ($context->active) {
            $context->assertWithinBudget();
            $budget = min($budget, $context->remainingMs());
        }
        if (! isset($this->observed[$connection])) {
            $connection->beforeExecuting(function ($query, $bindings, $connection): void {
                if (isset($this->deadlines[$connection])) {
                    $this->assertWithinBudget($connection);
                }
            });
            $this->observed[$connection] = true;
        }
        $this->deadlines[$connection] = hrtime(true) + $budget * 1_000_000;
        $nested = $connection->transactionLevel() > 0;
        try {
            // A lost connection may leave COMMIT uncertain, so never automatically replay a save.
            return $connection->transaction(function () use ($connection, $operation, $nested, $budget) {
                $previous = null;
                if ($connection->getDriverName() === 'pgsql') {
                    if ($nested) {
                        $previous = $connection->selectOne("SELECT current_setting('statement_timeout') AS statement, current_setting('lock_timeout') AS lock");
                    }
                    $connection->selectOne("SELECT set_config('statement_timeout', ?, true), set_config('lock_timeout', ?, true)", [
                        max(1, min($budget, (int) config('association.statement_timeout_ms'))).'ms',
                        max(1, min($budget, (int) config('association.lock_timeout_ms'))).'ms',
                    ]);
                }
                $result = $operation();
                $this->assertWithinBudget($connection); // Expiry before commit rolls back business changes.
                if ($previous) {
                    $connection->selectOne("SELECT set_config('statement_timeout', ?, true), set_config('lock_timeout', ?, true)", [$previous->statement, $previous->lock]);
                }

                // SET LOCAL expires at commit/rollback; it does not leak into a pooled connection.
                return $result;
            }, 1);
        } finally {
            unset($this->deadlines[$connection]);
        }
    }
}
