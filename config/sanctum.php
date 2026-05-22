<?php

use Laravel\Sanctum\Sanctum;

return [


    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:5173,172.20.13.196:5173')),



    'guard' => ['web'],


    'expiration' => 1440,


    // 'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),


    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
