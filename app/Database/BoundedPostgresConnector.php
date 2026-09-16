<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\AssociationRequestContext;
use Illuminate\Database\Connectors\PostgresConnector;

final class BoundedPostgresConnector extends PostgresConnector
{
    public function connect(array $config)
    {
        // Record connection time without recording the host, DSN, username or password.
        return app(AssociationRequestContext::class)->measure('connection', fn () => parent::connect($config));
    }

    protected function getDsn(array $config)
    {
        // libpq bounds each connection attempt. Keep Laravel's SSL and credential handling.
        // A statement timeout cannot help until the connection has actually been established.
        $seconds = max(2, min(30, (int) ($config['connect_timeout'] ?? 5)));

        return parent::getDsn($config).';connect_timeout='.$seconds;
    }
}
