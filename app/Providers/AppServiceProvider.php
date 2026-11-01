<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
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
        ResetPassword::createUrlUsing(function (CanResetPassword $user, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], encoding_type: PHP_QUERY_RFC3986);

            // Password reset uses a broker token in the frontend URL, not the signed verification route.
            return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.$query;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email', '')));

            // Count every attempt in a five-per-minute bucket keyed by normalized email and client IP; attempt six returns HTTP 429.
            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('forgot-password', function (Request $request): Limit {
            // Count known and unknown emails in the same five-per-minute IP bucket to limit enumeration abuse.
            return Limit::perMinute(5)->by($request->ip());
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
