<?php

namespace App\Providers;

use App\Auditoria\ObservadorDeAuditoria;
use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Contratacion\ObservadorDeVinculacion;
use App\Dominio\Alertas\CatalogoDeReglas;
use App\Models\User;
use App\Models\Vinculacion;
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

        // El catálogo de reglas de alerta, también singleton: es una lista que
        // se llena al arrancar y que el proceso diario recorre. Hoy está vacía
        // —F3-01 construye la infraestructura, las once reglas llegan en F3-03,
        // F3-04 y F3-05— y cada regla nueva se suma aquí con una línea:
        //
        //     $this->app->afterResolving(CatalogoDeReglas::class, fn ($catalogo) =>
        //         $catalogo->registrar(new ReglaDelPeriodoDePrueba));
        $this->app->singleton(CatalogoDeReglas::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La auditoría se engancha aquí, una sola vez y para todos los modelos.
        // No hay nada que agregar por cada tabla nueva: si se guarda en la base
        // de datos, queda registrado.
        ObservadorDeAuditoria::registrar();

        // Toda vinculación nace con su periodo inicial, venga del formulario,
        // de la carga inicial o de una prueba. El historial del contrato no
        // puede empezar en la primera prórroga.
        Vinculacion::observe(ObservadorDeVinculacion::class);

        // La única habilidad de acceso a módulos. El middleware `modulo:`, los
        // controladores y el menú preguntan todos por aquí, así que no puede
        // haber dos respuestas distintas a la misma pregunta.
        Gate::define('modulo', function (User $usuario, Modulo $modulo): bool {
            return app(PoliticaDeModulos::class)->puedeAcceder($usuario, $modulo);
        });
    }
}
