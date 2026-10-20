<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class MeasureNotificationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! app()->environment('local')
            || ! $request->isMethod('GET')
            // Measure these local read requests so dashboard changes can be compared.
            || ! $request->is(
                'notifications',
                'admin/dashboard',
                'officer/dashboard',
                'member/*',
                'membership/*'
            )
        ) {
            return $next($request);
        }

        $request->attributes->set('assocmap.measure_connection', true);
        $request->attributes->set('assocmap.connection_ms', 0.0);
        $request->attributes->set('assocmap.connection_open_ms', 0.0);

        $started = hrtime(true);
        $queryTimes = [];
        $collecting = true;

        // Collect durations only. Never record SQL, bindings, or user details.
        DB::listen(function (QueryExecuted $event) use (
            &$queryTimes,
            &$collecting
        ): void {
            if ($collecting) {
                $queryTimes[] = round($event->time, 2);
            }
        });

        try {
            $response = $next($request);
        } finally {
            $collecting = false;
            $request->attributes->remove('assocmap.measure_connection');
        }

        // This includes downstream middleware, including session handling.
        // It excludes Laravel startup before this middleware and network travel.
        $totalMs = (hrtime(true) - $started) / 1_000_000;

        $response->headers->set(
            'X-AssocMap-Request-Ms',
            number_format($totalMs, 2, '.', '')
        );

        $response->headers->set(
            'X-AssocMap-Query-Count',
            (string) count($queryTimes)
        );

        $response->headers->set(
            'X-AssocMap-Query-Ms',
            number_format(array_sum($queryTimes), 2, '.', '')
        );

        $response->headers->set(
            'X-AssocMap-Each-Query-Ms',
            implode(',', $queryTimes)
        );

        $response->headers->set(
            'X-AssocMap-Connection-Ms',
            number_format(
                (float) $request->attributes->get('assocmap.connection_ms', 0.0),
                2,
                '.',
                ''
            )
        );

        // Connection establishment includes driver/network authentication and any retry.
    // The remainder covers Laravel initialization and connector bookkeeping.
    $connectionMs = (float) $request->attributes->get(
        'assocmap.connection_ms',
        0.0
    );
    $openMs = (float) $request->attributes->get(
        'assocmap.connection_open_ms',
        0.0
    );

    $response->headers->set(
        'X-AssocMap-Connection-Open-Ms',
        number_format($openMs, 2, '.', '')
    );
    $response->headers->set(
        'X-AssocMap-Connection-Remaining-Ms',
        number_format(max(0.0, $connectionMs - $openMs), 2, '.', '')
    );

        return $response;
    }
}