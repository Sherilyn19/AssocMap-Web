<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\AssociationRequestContext;
use Illuminate\Database\Connectors\PostgresConnector;

final class BoundedPostgresConnector extends PostgresConnector
{
    public function connect(array $config)
    {
        $request = app()->bound('request') ? app('request') : null;

        $measure = app()->environment('local')
            && $request instanceof \Illuminate\Http\Request
            && $request->attributes->get('assocmap.measure_connection') === true;

        $started = $measure ? hrtime(true) : null;

        try {
            // Preserve the existing connection handling and deadline checks.
            return app(AssociationRequestContext::class)->measure(
                'connection',
                fn () => parent::connect($config)
            );
        } finally {
            if ($measure) {
                $elapsedMs = (hrtime(true) - $started) / 1_000_000;

                $request->attributes->set(
                    'assocmap.connection_ms',
                    (float) $request->attributes->get(
                        'assocmap.connection_ms',
                        0.0
                    ) + $elapsedMs
                );
            }
        }
    }

    public function createConnection($dsn, array $config, array $options)
{
    $request = app()->bound('request') ? app('request') : null;

    $measure = app()->environment('local')
        && $request instanceof \Illuminate\Http\Request
        && $request->attributes->get('assocmap.measure_connection') === true;

    if (! $measure) {
        return parent::createConnection($dsn, $config, $options);
    }

    $started = hrtime(true);

    try {
        // Delegate connection creation and retry handling to Laravel unchanged.
        // Never expose the DSN, credentials, or connection options.
        return parent::createConnection($dsn, $config, $options);
    } finally {
        $request->attributes->set(
            'assocmap.connection_open_ms',
            (float) $request->attributes->get(
                'assocmap.connection_open_ms',
                0.0
            ) + (hrtime(true) - $started) / 1_000_000
        );
    }
}

    protected function getDsn(array $config)
    {
        // libpq bounds each connection attempt. Keep Laravel's SSL and credential handling.
        // A statement timeout cannot help until the connection has actually been established.
        $seconds = max(2, min(30, (int) ($config['connect_timeout'] ?? 5)));

        return parent::getDsn($config).';connect_timeout='.$seconds;
    }
}
