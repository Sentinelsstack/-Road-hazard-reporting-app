<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Login: strict per-email limit (also bounds credential stuffing).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by('login:' . ($request->input('email') ?: $request->ip()));
        });

        // Registration: per-IP to stop account spam.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by('register:' . $request->ip());
        });

        // General API budget for authenticated traffic (per user, falling
        // back to IP) so no single client can flood writes or enumeration.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(120)->by('api:' . ($user?->id ?? 'ip:' . $request->ip()));
        });
    }
}

