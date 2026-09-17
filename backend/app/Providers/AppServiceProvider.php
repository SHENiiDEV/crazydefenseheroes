<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by((string) $request->ip()));

        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(60)
            ->by((string) $request->route('provider').':'.$request->ip()));
    }
}
