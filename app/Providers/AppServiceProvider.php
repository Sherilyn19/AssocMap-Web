<?php

namespace App\Providers;

use App\Database\BoundedPostgresConnector;
use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Policies\AssociationPolicy;
use App\Policies\MemberApplicationPolicy;
use App\Policies\MemberPolicy;
use App\Services\AssociationDatabase;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AssociationDatabase::class);
        $this->app->bind('db.connector.pgsql', BoundedPostgresConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared account limits stop trying a different application ID to bypass throttling.
        RateLimiter::for('membership-review', fn ($request) => [
            Limit::perMinute(5)->by('review:'.$request->session()->get('auth_user.id')),
            Limit::perHour(30)->by('review-hour:'.$request->session()->get('auth_user.id')),
        ]);
        RateLimiter::for('membership-submit', fn ($request) => Limit::perMinute(10)->by('submit:'.$request->session()->get('auth_user.id'))
        );
        Gate::policy(Association::class, AssociationPolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(MemberApplication::class, MemberApplicationPolicy::class);
    }
}
