<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Limit password guessing and attempts that rotate through different email addresses. */
final class LoginAttemptLimiter
{
    public function retryAfter(Request $request): int
    {
        $seconds = 0;
        foreach ([$this->accountKey($request) => 5, $this->ipKey($request) => 30] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        return $seconds;
    }

    public function recordRequest(Request $request): void
    {
        // Count malformed and successful submissions too, so rotating accounts cannot bypass this limit.
        RateLimiter::hit($this->ipKey($request), 60);
    }

    public function recordFailure(Request $request): void
    {
        RateLimiter::hit($this->accountKey($request), 60);
    }

    public function clearFailures(Request $request): void
    {
        RateLimiter::clear($this->accountKey($request));
    }

    private function accountKey(Request $request): string
    {
        $email = $request->input('email');
        $email = is_string($email) ? mb_strtolower(trim($email)) : '';

        return 'login:account:'.hash('sha256', $email.'|'.$request->ip());
    }

    private function ipKey(Request $request): string
    {
        return 'login:ip:'.hash('sha256', (string) $request->ip());
    }
}
