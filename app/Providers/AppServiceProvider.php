<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Usuario;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Salida;
use App\Observers\LogObserver;

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


    public function boot()
    {
        Usuario::observe(LogObserver::class);
        Cliente::observe(LogObserver::class);
        Lote::observe(LogObserver::class);
        Producto::observe(LogObserver::class);
        Salida::observe(LogObserver::class);
    }
}
