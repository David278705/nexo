<?php

namespace App\Http\Controllers;

use App\Inicio\BloqueDeAccion;
use App\Models\User;
use App\Presentacion\Formato;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;

/**
 * El panel de inicio (documento 1, capítulo 12 · documento 3, sección 5.2).
 *
 * La pantalla más importante del sistema: responde «¿qué tengo que hacer hoy?»
 * antes de enseñar ninguna estadística. Aquí no se cuenta ni se decide nada —eso
 * es `BloqueDeAccion`—; este controlador traduce la petición en props.
 *
 * El módulo `Inicio` lo tiene todo usuario de una empresa, así que la ruta no
 * lleva middleware de módulo. Lo que sí depende de los permisos es **cada
 * tarjeta**, porque cada una lleva a un listado que sí lo tiene.
 */
class InicioController extends Controller
{
    public function __construct(private readonly BloqueDeAccion $bloque) {}

    public function index(Request $peticion): RespuestaInertia
    {
        /** @var User $usuario */
        $usuario = $peticion->user();

        return Inertia::render('Inicio', [
            /*
             * La fecha se arma en el servidor para que salga en la zona horaria
             * de la aplicación —`America/Bogota`— y no en la del navegador de
             * quien mira.
             */
            'fecha' => Formato::diaCompleto(Carbon::now()),
            'acciones' => $this->bloque->paraElUsuario($usuario),
            /*
             * La tarjeta que tarda, que hoy es la de vacaciones acumuladas.
             * Llega en una segunda vuelta que Inertia pide sola en cuanto la
             * pantalla está pintada: el panel es lo primero que se ve al entrar
             * y no puede quedarse esperando a que el motor recorra la planta.
             */
            'accionesDiferidas' => Inertia::defer(
                fn (): array => $this->bloque->diferidasParaElUsuario($usuario)
            ),
        ]);
    }
}
