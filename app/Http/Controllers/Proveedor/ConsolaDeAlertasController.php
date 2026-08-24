<?php

namespace App\Http\Controllers\Proveedor;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarAlerta;
use App\Listados\Listado;
use App\Models\AlertaEnviada;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\ConsolaDeAlertas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La consola de alertas del proveedor (documento 4, capítulo 7).
 *
 * Se llama distinto del `AlertaController` del cliente a propósito: no son la
 * misma pantalla con otro filtro, son dos formas distintas de leer la misma
 * tabla —una de una sola empresa con el ORM, esta de todas las empresas
 * recorriéndolas una por una— y `App\Proveedor\ConsolaDeAlertas` explica por
 * qué esa diferencia no es cosmética.
 */
class ConsolaDeAlertasController extends Controller
{
    public function index(Request $peticion): Response
    {
        $consola = ConsolaDeAlertas::desde($peticion);

        return Inertia::render('Proveedor/Alertas', [
            ...$consola->paraLaPantalla(),
            'entregabilidad' => ConsolaDeAlertas::entregabilidadDelMes(),
            'opciones' => [
                'empresas' => ConsolaDeAlertas::empresasParaElegir(),
                'tipos' => ConsolaDeAlertas::tiposParaElegir(),
                'estados' => ConsolaDeAlertas::estadosParaElegir(),
                'tamanos' => Listado::TAMANOS_DE_PAGINA,
            ],
        ]);
    }

    /**
     * El reenvío puntual del capítulo 7.
     *
     * La empresa y la alerta viajan por separado en la dirección, como en
     * `EnviarAlerta`: fuera de su contexto, el global scope de la alerta no
     * encuentra nada que enlazar de forma implícita.
     */
    public function reenviar(Tenant $empresa, int $alerta): RedirectResponse
    {
        $registro = app(ContextoTenant::class)->paraTenant($empresa, function () use ($alerta): AlertaEnviada {
            $registro = AlertaEnviada::query()->findOrFail($alerta);

            abort_unless($registro->puedeReenviarse(), 422, 'Esta alerta ya está pendiente de envío.');

            $registro->prepararParaReenvio();

            return $registro;
        });

        EnviarAlerta::dispatch($registro->getKey(), $empresa->getKey());

        return back()->with('estado', 'Se puso en cola el reenvío de la alerta.');
    }
}
