<?php

/*
 * ============================================================
 * bootstrap/app.php
 * ============================================================
 * Laravel 12 application bootstrap.
 * Registers the AssocMapAuth middleware alias so routes can use:
 *   ->middleware('assocmap.auth:Role Name')
 * ============================================================
 */

use App\Http\Middleware\AssocMapAuth;
use App\Http\Middleware\TrackAssociationRequest;
use App\Support\AssociationErrors;
use App\Support\MonitoringErrors;
use App\Support\GisErrors;
use App\Support\TrainingManagementErrors;
use App\Support\UserManagementErrors;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Start the Association clock before database-backed session loading.
        $middleware->prepend(TrackAssociationRequest::class);
        // Preserve intentional passphrase spaces, as with an ordinary password.
        $middleware->trimStrings(except: ['review_passphrase', 'review_passphrase_confirmation']);
        /*
         * Register AssocMapAuth as a named middleware alias.
         * This allows routes to reference it as 'assocmap.auth'
         * with an optional role parameter after the colon.
         *
         * Example usage in routes/web.php:
         *   ->middleware('assocmap.auth:System Administrator')
         */
        $middleware->alias([
            'assocmap.auth' => AssocMapAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Covers failures before controller entry, including route binding and Form Requests.
        $exceptions->report(function (Throwable $error) {
            if (GisErrors::handles(request(), $error)) {
                return false;
            }
            if (request()->is('admin/audit-logs') && ($error instanceof QueryException || $error instanceof PDOException)) {
                logger()->error('Audit history unavailable.', ['exception_type' => $error::class]);
                return false;
            }
            if (MonitoringErrors::handles(request(), $error)) {
                return false;
            }
            if (TrainingManagementErrors::handles(request(), $error)) {
                return false;
            }
            if (UserManagementErrors::handles(request(), $error)) {
                return false;
            }
            if (AssociationErrors::handles(request(), $error)) {
                return false;
            }
        });
        $exceptions->render(function (Throwable $error, Request $request) {
            if ($request->is('member/*') && $error instanceof \Illuminate\Validation\ValidationException && $request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($request->url())->withErrors($error->errors())
                    ->with('error', 'Choose valid filters and try again.');
            }
            if (($request->is('member/*') || ($request->is('membership', 'membership/*') && $request->hasSession() && $request->session()->get('auth_user.role_name') === 'Association Member'))
                && ($error instanceof QueryException || $error instanceof PDOException)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Association records are temporarily unavailable. Please try again.'], 503)
                    : response()->view('association-member-user.unavailable', [], 503);
            }
            // Invalid GET filters must return to a clean register, not redirect in a loop
            // to the same invalid URL. JSON callers keep Laravel's normal 422 response.
            if ($error instanceof \Illuminate\Validation\ValidationException && $request->isMethod('GET') && ! $request->expectsJson()) {
                $registers = [
                    'officer.associations.index' => 'officer.associations.index',
                    'officer.projects.index' => 'officer.projects.index',
                    'officer.trainings.index' => 'officer.trainings.index',
                    'officer.reports.index' => 'officer.reports.index',
                    'officer.reports.export' => 'officer.reports.index',
                    'reports.index' => 'reports.index',
                    'reports.export' => 'reports.index',
                    'monitoring.index' => 'monitoring.index',
                ];
                $register = $registers[$request->route()?->getName()] ?? null;
                if ($register !== null) {
                    return redirect()->route($register)->withErrors($error->errors())
                        ->with('error', 'The filters could not be applied. Choose valid filters and try again.');
                }
            }
            if ($error instanceof \Illuminate\Auth\Access\AuthorizationException
                || $error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && in_array($error->getStatusCode(), [403, 404, 405], true))) {
                app(\App\Services\AuditLogService::class)->denied($request);
            }
            if ($request->is('officer/*') && ($error instanceof QueryException || $error instanceof PDOException)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Officer records are temporarily unavailable. Please try again.'], 503)
                    : response()->view('errors.field-officer-user.field-officer-user-unavailable', [], 503);
            }
            if (GisErrors::handles($request, $error)) {
                return GisErrors::render($error, $request);
            }
            if ($request->is('admin/audit-logs') && ($error instanceof QueryException || $error instanceof PDOException)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Audit Logs temporarily unavailable. Please try again.'], 503)
                    : response()->view('errors.admin-user.admin-user-audit-logs-unavailable', [], 503);
            }
            if (MonitoringErrors::handles($request, $error)) {
                return MonitoringErrors::render($error, $request);
            }
            if (TrainingManagementErrors::handles($request, $error)) {
                return TrainingManagementErrors::render($error, $request);
            }
            // Form Request validation and middleware run before the controller's try/catch.
            // Keep the same safe account-error responses for failures at those earlier stages.
            if (UserManagementErrors::handles($request, $error)) {
                return UserManagementErrors::render($error, $request);
            }
            // Area validation queries run before the controller's try/catch.
            // Keep database diagnostics private even when APP_DEBUG is enabled.
            if ($request->is('admin/areas', 'admin/areas/*') && ($error instanceof QueryException || $error instanceof PDOException)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Area Management is temporarily unavailable. Please try again.'], 503);
                }
                if (! $request->isMethod('GET') && $request->hasSession()) {
                    $barangay = $request->is('admin/areas/barangays', 'admin/areas/barangays/*');

                    return back()->withInput([
                        ...$request->only(['name', 'address', 'area_unit_id']),
                        '_area_form' => $barangay ? 'barangay' : 'municipality',
                        '_area_id' => $request->route($barangay ? 'subUnit' : 'areaUnit'),
                    ])
                        ->with('error', 'The request could not be confirmed. Check the record before retrying.');
                }

                return response()->view('admin-user.admin-area-management.unavailable', [], 503);
            }
            if (AssociationErrors::handles($request, $error)) {
                return AssociationErrors::render($error, $request);
            }

            return null;
        });
        // Validation redirects must never copy private review secrets into session old input.
        $exceptions->dontFlash(['review_passphrase', 'review_passphrase_confirmation']);
    })
    ->create();
