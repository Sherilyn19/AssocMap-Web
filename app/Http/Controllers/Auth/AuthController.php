<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssocMapAuth;
use App\Services\AdminUserManagementService;
use App\Services\AuthService;
use App\Services\LoginAttemptLimiter;
use App\Support\SessionCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * ============================================================
 * AuthController
 * app/Http/Controllers/Auth/AuthController.php
 * ============================================================
 * Handles user authentication: login form display, credential
 * validation, session creation, role-based redirect, and logout.
 *
 * Uses AuthService for all database operations (PDO + JOINs).
 * Controller stays thin — no business logic here (SRP).
 * ============================================================
 */
class AuthController extends Controller
{
    /**
     * AuthService handles all PDO queries.
     * Injected via constructor (Dependency Injection).
     */
    public function __construct(
        private readonly AuthService $authService,
        private readonly LoginAttemptLimiter $limiter
    ) {}

    /**
     * Show the login form.
     * Redirect to dashboard if user already has an active session.
     */
    public function showLogin(Request $request, AssocMapAuth $access): View|RedirectResponse
    {
        // If already logged in, redirect to their dashboard
        if (session()->has('auth_user')) {
            // Recheck stale sessions here as well as on protected pages.
            return $access->handle($request, fn () => $this->redirectToDashboard(session('auth_user.role_name')));
        }

        return view('auth.login');
    }

    /**
     * Handle the login form submission.
     *
     * Flow:
     *  1. Validate input format (Laravel validation)
     *  2. Look up user via PDO JOIN (users + roles)
     *  3. Verify bcrypt password
     *  4. Check is_active flag
     *  5. Write session
     *  6. Write audit log entry
     *  7. Redirect to role-appropriate dashboard
     */
    public function login(Request $request): RedirectResponse
    {
        $retryAfter = $this->limiter->retryAfter($request);
        if ($retryAfter > 0) {
            return redirect()->route('login')
                ->withInput(is_string($request->input('email')) ? $request->only('email') : [])
                ->with('error', "Too many login attempts. Please try again in {$retryAfter} seconds.")
                ->withHeaders(['Retry-After' => $retryAfter]);
        }
        $this->limiter->recordRequest($request);

        // ── Step 1: Validate input format ────────────────────
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        // ── Step 2 & 3 & 4: Fetch user + verify via PDO ──────
        try {
            $user = $this->authService->findUserWithRole($validated['email']);
        } catch (RuntimeException $error) {
            // AuthService records lookup failures. Keep database details off the login form
            // and do not create a session or count an outage as an incorrect password.
            return redirect()->route('login')->withInput($request->only('email'))
                ->with('error', 'Sign-in is temporarily unavailable. Please try again.');
        }

        if (! $user || ! password_verify($validated['password'], $user['password'])) {
            $this->limiter->recordFailure($request);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid email or password. Please try again.');
        }

        if (! $user['is_active']) {
            $this->limiter->recordFailure($request);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Your account has been deactivated. Please contact the System Administrator.');
        }

        // A valid password does not authorize a role the application does not support.
        // Reject it before creating a session or writing a successful login audit event.
        if (! in_array($user['role_name'], AdminUserManagementService::ROLES, true)) {
            $this->limiter->recordFailure($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withInput($request->only('email'))
                ->with('error', 'Your account role is unavailable. Contact the System Administrator.');
        }

        // ── Step 5: Store minimal user data in session ────────
        // Never store password in session.
        $this->limiter->clearFailures($request);
        session()->regenerate(true); // Remove the old session ID after successful authentication.

        session([
            'auth_user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role_id' => $user['role_id'],
                'role_name' => $user['role_name'],
                'credential_fingerprint' => SessionCredentials::fingerprint($user['id'], $user['password']),
            ],
        ]);

        // ── Step 6: Write login event to audit_logs ───────────
        $this->authService->writeAuditLog(
            userId     : $user['id'],
            actionType : 'LOGIN',
            module     : 'Auth',
            details    : 'User logged in successfully'
        );

        // ── Step 7: Redirect to role dashboard ────────────────
        return $this->redirectToDashboard($user['role_name']);
    }

    /**
     * Log the user out.
     * Clears session, writes audit log, redirects to login.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Write logout event before clearing session
        if (session()->has('auth_user')) {
            $this->authService->writeAuditLog(
                userId     : session('auth_user.id'),
                actionType : 'LOGOUT',
                module     : 'Auth',
                details    : 'User logged out'
            );
        }

        // Invalidate session and regenerate CSRF token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }

    /**
     * Map role name to the correct dashboard route.
     * Keeps role-routing logic in one place (DRY).
     *
     * @param  string  $roleName  Role name from the roles table
     */
    private function redirectToDashboard(string $roleName): RedirectResponse
    {
        return match ($roleName) {
            'System Administrator' => redirect()->route('dashboard.admin'),
            'Field Officer' => redirect()->route('dashboard.officer'),
            'Association Member' => redirect()->route('dashboard.member'),
            // Fallback: unknown role hits login with error
            default => redirect()->route('login')
                ->with('error', 'Unrecognized user role. Contact your administrator.'),
        };
    }
}
