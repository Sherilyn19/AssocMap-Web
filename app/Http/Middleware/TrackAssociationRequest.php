<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AssociationRequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Starts before session middleware, and finishes after its database save. */
final class TrackAssociationRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->is('admin/associations', 'admin/associations/*')) {
            return $next($request);
        }
        $context = app(AssociationRequestContext::class);
        $context->start();
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $context->reference);

            return $response;
        } finally {
            try {
                $slow = $context->elapsedMs() >= (int) config('association.slow_request_ms');
                if ($slow || $status >= 500 || $context->failedStage !== null || config('association.log_successful_requests')) {
                    // Route NAME is safe; URLs, IDs, filters, SQL and submitted values are excluded.
                    // Stage durations can overlap (e.g. connection inside session_read); do not sum them.
                    Log::log($status >= 500 ? 'warning' : 'info', 'Association request timing', [
                        'reference' => $context->reference,
                        'route' => $request->route()?->getName(),
                        'method' => $request->method(), 'status' => $status,
                        'total_ms' => round($context->elapsedMs(), 2),
                        'stages_ms' => array_map(fn ($ms) => round($ms, 2), $context->stages),
                        'query_count' => $context->queryCount, 'query_ms' => round($context->queryMs, 2),
                        'failed_stage' => $context->failedStage,
                        'mutation_completed' => $context->mutationCompleted,
                        'request_budget_ms' => (int) config('association.request_timeout_ms'),
                        'php_sapi' => PHP_SAPI, 'php_execution_limit_s' => (int) ini_get('max_execution_time'),
                    ]);
                }
            } finally {
                // Prevent the next request/console operation from inheriting this request's deadline.
                $context->active = false;
            }
        }
    }
}
