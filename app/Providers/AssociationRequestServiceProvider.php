<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\BoundAssociationBindings;
use App\Session\AssociationDatabaseSessionHandler;
use App\Support\AssociationRequestContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AssociationRequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AssociationRequestContext::class);
        // Keep Laravel's existing middleware order; change only how Association bindings read.
        $this->app->bind(SubstituteBindings::class, BoundAssociationBindings::class);
    }

    public function boot(): void
    {
        $this->app->make('session')->extend('database', function ($app) {
            return new AssociationDatabaseSessionHandler(
                $app->make('db')->connection(config('session.connection')),
                config('session.table'), config('session.lifetime'), $app,
            );
        });
        DB::listen(function (QueryExecuted $event): void {
            // Resolve on each event, so long-running workers do not capture an old request.
            $context = app(AssociationRequestContext::class);
            if ($context->active) {
                $context->queryCount++;
                $context->queryMs += $event->time;
            }
        });
    }
}
