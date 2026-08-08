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
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(MemberApplication::class, MemberApplicationPolicy::class);
    }
}
