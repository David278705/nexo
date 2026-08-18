<?php

namespace App\Providers;

use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Facades\Gate;
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
        // La única habilidad de acceso a módulos. El middleware `modulo:`, los
        // controladores y el menú preguntan todos por aquí, así que no puede
        // haber dos respuestas distintas a la misma pregunta.
        Gate::define('modulo', function (User $usuario, Modulo $modulo): bool {
            return app(PoliticaDeModulos::class)->puedeAcceder($usuario, $modulo);
        });
    }
}
