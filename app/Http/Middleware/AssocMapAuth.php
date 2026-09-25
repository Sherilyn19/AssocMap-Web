<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminUserManagementService;
use App\Services\AssociationDatabase;
use App\Support\AssociationRequestContext;
use App\Support\SessionCredentials;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ============================================================
 * AssocMapAuth Middleware
 * app/Http/Middleware/AssocMapAuth.php
 * ============================================================
 * Guards all dashboard routes. Runs before the controller.
 *
 * Checks:
 *  1. Session has 'auth_user' (user is logged in)
 *  2. The user's role matches the route's required role
 *     (prevents a Field Officer accessing the Admin dashboard)
 *
 * If either check fails:
 *  - Redirects to /login with an appropriate flash message
 *
 * SRP: this middleware only handles route-level access control.
 * ============================================================
 */
class AssocMapAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  string|null  $requiredRole  Role name that may access this route.
     *                                     Passed as a middleware parameter:
     *                                     e.g. assocmap.auth:System Administrator
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        // ── Check 1: Is the user logged in? ──────────────────
        if (! session()->has('auth_user')) {
            return redirect()->route('login')
                ->with('error', 'Please log in to access this page.');
        }

        // Use the session to identify the user and the database to check current permissions.
        // Role changes and account deactivation take effect without waiting for logout.
        try {
            $loadActor = fn () => User::with('role')->find($request->session()->get('auth_user.id'));
            // Authentication queries happen before the controller's own database scope.
            $actor = app(AssociationRequestContext::class)->active
                ? app(AssociationDatabase::class)->run($loadActor, stage: 'authentication')
                : $loadActor();
        } catch (Throwable $exception) {
            if ($request->is('admin/associations', 'admin/associations/*')) {
                throw $exception;
            }
            report($exception);
            abort(503, 'Account access could not be verified. Please try again shortly.');
        }

        // Recheck supported roles on every protected request. This also clears older
        // sessions after a role is renamed or replaced and prevents a login redirect loop.
        if (! $actor || ! $actor->is_active || ! $actor->role
            || ! in_array($actor->role->role_name, AdminUserManagementService::ROLES, true)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account is unavailable. Contact an administrator.');
        }

        if (! SessionCredentials::matches($request, $actor)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your sign-in has expired. Please log in again.');
        }

        $request->attributes->set('assocmap.actor', $actor);
        $request->session()->put('auth_user', [
            'id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email,
            'role_id' => $actor->role_id, 'role_name' => $actor->role->role_name,
            'association_id' => $actor->association_id,
            'credential_fingerprint' => $request->session()->get('auth_user.credential_fingerprint'),
        ]);

        // ── Check 2: Does the role match? ─────────────────────
        // Only enforce if a required role was specified on the route.
        if ($requiredRole !== null) {
            $userRole = session('auth_user.role_name');

            if ($userRole !== $requiredRole) {
                /*
                 * Role mismatch: the user is logged in but trying to
                 * access a dashboard that does not belong to their role.
                 * Redirect them to their own dashboard instead of login.
                 */
                return $this->redirectToOwnDashboard($userRole);
            }
        }

        // Auth passed — continue to the controller
        return $next($request);
    }

    /**
     * Redirect the user to their own role's dashboard.
     * Used when they try to access another role's route.
     */
    private function redirectToOwnDashboard(string $roleName): Response
    {
        $route = match ($roleName) {
            'System Administrator' => 'dashboard.admin',
            'Field Officer' => 'dashboard.officer',
            'Association Member' => 'dashboard.member',
            default => 'login',
        };

        return redirect()->route($route)
            ->with('error', 'You do not have permission to access that page.');
    }
}
