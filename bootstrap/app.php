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
use App\Support\AssociationErrors;
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
            if (AssociationErrors::handles(request(), $error)) {
                return false;
            }
        });
        $exceptions->render(function (Throwable $error, Request $request) {
            if (AssociationErrors::handles($request, $error)) {
                return AssociationErrors::render($error, $request);
            }

            return null;
        });
        // Validation redirects must never copy private review secrets into session old input.
        $exceptions->dontFlash(['review_passphrase', 'review_passphrase_confirmation']);
    })
    ->create();
