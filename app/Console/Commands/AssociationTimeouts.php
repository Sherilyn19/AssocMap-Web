<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Read-only deployment diagnostic; never prints credentials or edits the server's configuration. */
final class AssociationTimeouts extends Command
{
    protected $signature = 'association:timeouts';

    protected $description = 'Show Association timeout budgets and deployment checks';

    public function handle(): int
    {
        $this->table(['Setting', 'Effective value'], [
            ['Request budget (ms)', config('association.request_timeout_ms')],
            ['Operation budget (ms)', config('association.operation_timeout_ms')],
            ['Statement timeout (ms)', config('association.statement_timeout_ms')],
            ['Lock timeout (ms)', config('association.lock_timeout_ms')],
            ['PostgreSQL connect timeout per attempt (s)', max(2, min(30, (int) config('database.connections.pgsql.connect_timeout', 5)))],
            ['Session driver', config('session.driver')],
            ['This process PHP SAPI', PHP_SAPI],
            ['This process max_execution_time (s; 0 = unlimited)', ini_get('max_execution_time')],
        ]);
        $request = (int) config('association.request_timeout_ms');
        $statement = (int) config('association.statement_timeout_ms');
        $operation = (int) config('association.operation_timeout_ms');
        $lock = (int) config('association.lock_timeout_ms');
        if ($lock < 1 || $statement < $lock || $operation < $statement || $request < $operation) {
            $this->error('Use positive budgets ordered lock <= statement <= operation <= request.');

            return self::FAILURE;
        }
        $this->warn('CLI values do not verify the web PHP process or reverse proxy configuration.');
        $this->line('Web request timing logs include the actual PHP SAPI and execution limit.');
        $this->line('For the default 20-second budget: review web PHP 40s, FPM request_terminate_timeout 45s, and upstream proxy timeout 50s.');
        $this->line('These are deployment starting values, not settings applied by this command. Reassess them if budgets change.');
        $this->line('PHP execution limits are platform-dependent; the application budget cannot interrupt blocked network I/O.');

        return self::SUCCESS;
    }
}
