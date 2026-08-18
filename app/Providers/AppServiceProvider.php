<?php

namespace App\Providers;

use App\Multiempresa\ContextoTenant;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton: la empresa se resuelve una sola vez por petición y todo
        // el sistema lee de la misma instancia.
        $this->app->singleton(ContextoTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
