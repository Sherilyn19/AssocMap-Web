<?php

return [
    // Cooperative wall-clock budget: checked at database boundaries; not a process kill timer.
    // In-flight statements, network stalls and response delivery need server-level safeguards too.
    'request_timeout_ms' => (int) env('ASSOCIATION_REQUEST_TIMEOUT_MS', 20000),
    'slow_request_ms' => (int) env('ASSOCIATION_SLOW_REQUEST_MS', 3000),
    'log_successful_requests' => (bool) env('ASSOCIATION_LOG_SUCCESSFUL_REQUESTS', false),
    // Milliseconds. These are operation budgets, not a promise about network response time.
    'statement_timeout_ms' => (int) env('ASSOCIATION_STATEMENT_TIMEOUT_MS', 5000),
    'lock_timeout_ms' => (int) env('ASSOCIATION_LOCK_TIMEOUT_MS', 1500),
    'operation_timeout_ms' => (int) env('ASSOCIATION_OPERATION_TIMEOUT_MS', 12000),
];
