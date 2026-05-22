<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;


class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * Estos middleware se ejecutan en cada solicitud a la aplicación.
     */
    protected $middleware = [
        \App\Http\Middleware\Cors::class, // Middleware correcto
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];


    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
        ],

    ];

    /**
     * The application's middleware aliases.
     */
    protected $middlewareAliases = [
        'auth'           => \Illuminate\Auth\Middleware\Authenticate::class,
        'role'           => \App\Http\Middleware\RoleMiddleware::class,
        'auth.basic'     => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings'       => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers'  => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'            => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'          => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed'         => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle'       => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'       => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'sanctum'        => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ];
}
