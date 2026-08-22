<?php

namespace App\Providers;

use App\Auditoria\ObservadorDeAuditoria;
use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Contratacion\ObservadorDeVinculacion;
use App\Dominio\Alertas\CatalogoDeReglas;
use App\Dominio\Alertas\Reglas\CercaniaAlTope;
use App\Dominio\Alertas\Reglas\EfectoDelSilencio;
use App\Dominio\Alertas\Reglas\PlazoDeRenovacion;
use App\Dominio\Alertas\Reglas\PruebaFijoDosDias;
use App\Dominio\Alertas\Reglas\PruebaFijoVispera;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoQuinceDias;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoUnMes;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoVispera;
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
        // se llena al arrancar y que el proceso diario recorre. Cada tarea de
        // la fase 3 suma aquí las suyas con una línea y no toca el proceso;
        // faltan las dos del término fijo que dependen de decisiones abiertas
        // —última renovación corta y cercanía al tope— y los resúmenes (F3-05).
        $this->app->singleton(CatalogoDeReglas::class, function (): CatalogoDeReglas {
            $catalogo = new CatalogoDeReglas;

            // Las cinco del periodo de prueba (documento 1, 9.2 y 9.3).
            $catalogo->registrar(new PruebaIndefinidoUnMes);
            $catalogo->registrar(new PruebaIndefinidoQuinceDias);
            $catalogo->registrar(new PruebaIndefinidoVispera);
            $catalogo->registrar(new PruebaFijoDosDias);
            $catalogo->registrar(new PruebaFijoVispera);

            // Las del término fijo (documento 1, 9.4). Falta «última renovación
            // corta», bloqueada por una pregunta abierta en `ESTADO.md`.
            $catalogo->registrar(new PlazoDeRenovacion);
            $catalogo->registrar(new EfectoDelSilencio);
            $catalogo->registrar(new CercaniaAlTope);

            return $catalogo;
        });
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
