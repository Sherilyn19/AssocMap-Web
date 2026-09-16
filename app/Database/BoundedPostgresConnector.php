<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

final class BoundedPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        // libpq bounds each connection attempt. Keep Laravel's SSL and credential handling.
        // A statement timeout cannot help until the connection has actually been established.
        $seconds = max(2, min(30, (int) ($config['connect_timeout'] ?? 5)));

        return parent::getDsn($config).';connect_timeout='.$seconds;
    }
}
