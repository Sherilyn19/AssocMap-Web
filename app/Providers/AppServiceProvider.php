<?php

namespace App\Providers;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Policies\MemberApplicationPolicy;
use App\Policies\MemberPolicy;
use Illuminate\Support\Facades\Gate;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared account limits stop trying a different application ID to bypass throttling.
        \Illuminate\Support\Facades\RateLimiter::for('membership-review', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('review:'.$request->session()->get('auth_user.id')),
            \Illuminate\Cache\RateLimiting\Limit::perHour(30)->by('review-hour:'.$request->session()->get('auth_user.id')),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('membership-submit', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('submit:'.$request->session()->get('auth_user.id'))
        );
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(MemberApplication::class, MemberApplicationPolicy::class);
    }
}
