<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LogicException;

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
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email', '')));

            // Count every attempt in a five-per-minute bucket keyed by normalized email and client IP; attempt six returns HTTP 429.
            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('verification-resend', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            if ($userId === null) {
                throw new LogicException('Verification resend rate limiting requires an authenticated user.');
            }

            // Authentication runs first, so each user receives an independent three-per-15-minute bucket.
            return Limit::perMinutes(15, 3)->by((string) $userId);
        });
    }
}
