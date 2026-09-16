<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AssociationDatabase;
use App\Support\AssociationRequestContext;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Middleware\SubstituteBindings;

final class BoundAssociationBindings extends SubstituteBindings
{
    public function handle($request, Closure $next)
    {
        if (! app(AssociationRequestContext::class)->active) {
            return parent::handle($request, $next);
        }
        $route = $request->route();
        // The register/create routes have no bindings; avoid opening an empty database scope.
        if ($route->parameters() === []) {
            return parent::handle($request, $next);
        }
        try {
            app(AssociationDatabase::class)->run(function () use ($route) {
                $this->router->substituteBindings($route);
                $this->router->substituteImplicitBindings($route);
            }, stage: 'route_binding');
        } catch (ModelNotFoundException $error) {
            // Preserve Laravel's missing-model callback and normal 404 behavior.
            if ($route->getMissing()) {
                return $route->getMissing()($request, $error);
            }
            throw $error;
        }

        // Close this read transaction BEFORE entering the controller or saving the session.
        return $next($request);
    }
}
