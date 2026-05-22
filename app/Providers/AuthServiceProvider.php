<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Usuario;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Salida;
use App\Observers\LogObserver;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // 🔹 Configurar autenticación manualmente para el modelo Usuario
        Auth::provider('custom', function ($app, array $config) {
            return new \Illuminate\Auth\EloquentUserProvider($app['hash'], Usuario::class);
        });

        Usuario::observe(LogObserver::class);
        Cliente::observe(LogObserver::class);
        Lote::observe(LogObserver::class);
        Producto::observe(LogObserver::class);
        Salida::observe(LogObserver::class);
    }
}
