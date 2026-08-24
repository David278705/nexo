<?php

namespace App\Providers;

use App\Auditoria\ObservadorDeAuditoria;
use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Contratacion\ObservadorDeVinculacion;
use App\Dominio\Alertas\CatalogoDeReglas;
use App\Dominio\Alertas\Reglas\AcumulacionDeQuinceDias;
use App\Dominio\Alertas\Reglas\AnioSinDisfruteMinimo;
use App\Dominio\Alertas\Reglas\CercaniaAlTope;
use App\Dominio\Alertas\Reglas\ContratosVencidosSinGestionar;
use App\Dominio\Alertas\Reglas\EfectoDelSilencio;
use App\Dominio\Alertas\Reglas\ObraOLaborActivos;
use App\Dominio\Alertas\Reglas\PlazoDeRenovacion;
use App\Dominio\Alertas\Reglas\PruebaFijoDosDias;
use App\Dominio\Alertas\Reglas\PruebaFijoVispera;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoQuinceDias;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoUnMes;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoVispera;
use App\Dominio\Alertas\Reglas\UltimaRenovacionCorta;
use App\Models\User;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\AccesoAlEntorno;
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

        // La visita del proveedor al entorno de un cliente (documento 4, 4.4),
        // también singleton y por lo mismo: la preguntan `ResolverTenant`, la
        // política de módulos, los dos middleware de la visita y las props
        // compartidas, y la fila se busca una sola vez por petición.
        $this->app->singleton(AccesoAlEntorno::class);

        // El catálogo de reglas de alerta, también singleton: es una lista que
        // se llena al arrancar y que el proceso diario recorre. Cada tarea de
        // la fase 3 suma aquí las suyas con una línea y no toca el proceso.
        // Están las trece del capítulo 9 del documento 1.
        $this->app->singleton(CatalogoDeReglas::class, function (): CatalogoDeReglas {
            $catalogo = new CatalogoDeReglas;

            // Las cinco del periodo de prueba (documento 1, 9.2 y 9.3).
            $catalogo->registrar(new PruebaIndefinidoUnMes);
            $catalogo->registrar(new PruebaIndefinidoQuinceDias);
            $catalogo->registrar(new PruebaIndefinidoVispera);
            $catalogo->registrar(new PruebaFijoDosDias);
            $catalogo->registrar(new PruebaFijoVispera);

            // Las cuatro del término fijo (documento 1, 9.4).
            $catalogo->registrar(new PlazoDeRenovacion);
            $catalogo->registrar(new EfectoDelSilencio);
            $catalogo->registrar(new UltimaRenovacionCorta);
            $catalogo->registrar(new CercaniaAlTope);

            // Los tres resúmenes mensuales (documento 1, 9.4 y 9.5).
            $catalogo->registrar(new ContratosVencidosSinGestionar);
            $catalogo->registrar(new AcumulacionDeQuinceDias);
            $catalogo->registrar(new AnioSinDisfruteMinimo);

            // El bimestral, el único que no sale todos los meses (9.6).
            $catalogo->registrar(new ObraOLaborActivos);

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
