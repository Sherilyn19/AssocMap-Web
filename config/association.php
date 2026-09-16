<?php

return [
    // Milliseconds. These are operation budgets, not a promise about network response time.
    'statement_timeout_ms' => (int) env('ASSOCIATION_STATEMENT_TIMEOUT_MS', 5000),
    'lock_timeout_ms' => (int) env('ASSOCIATION_LOCK_TIMEOUT_MS', 1500),
    'operation_timeout_ms' => (int) env('ASSOCIATION_OPERATION_TIMEOUT_MS', 12000),
];
