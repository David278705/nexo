<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Exports\TerminacionesExport;
use App\Http\Requests\RegistrarTerminacion;
use App\Models\Terminacion;
use App\Models\Vinculacion;
use App\Terminaciones\AvisoDeTerminacion;
use App\Terminaciones\ListadoDeTerminaciones;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Terminaciones (documento 1, capítulo 7).
 *
 * Usa el flujo compartido de registro de novedades (F2-03) y, como
 * movimientos, **escribe en dos sitios**: la terminación y el estado del
 * empleado. Las dos van juntas en `Vinculacion::registrarTerminacion()`.
 *
 * El acceso lo verifica el middleware `modulo:terminaciones`.
 */
class TerminacionController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeTerminaciones::desde($peticion);

        return Inertia::render('Terminaciones/Index', [
            ...$listado->paraLaPantalla(
                'terminaciones',
                fn (Terminacion $terminacion): array => ListadoDeTerminaciones::fila($terminacion),
            ),
            'opciones' => [
                'tipos' => TipoDeSalida::paraElegir(),
                /*
                 * La lista dependiente del documento 1, 7.3. Viaja entera y no
                 * por recarga parcial a propósito: son 35 frases fijas que no
                 * dependen de ningún dato de la empresa, así que pedirlas al
                 * servidor cada vez que se cambia el tipo sería una vuelta de
                 * red por algo que ya se sabe. Lo que **sí** decide el servidor
                 * es cuál vale: `RegistrarTerminacion` comprueba que el motivo
                 * guardado sea de ese tipo.
                 */
                'motivosPorTipo' => TipoDeSalida::motivosPorTipo(),
                'motivos' => TipoDeSalida::todosLosMotivos(),
                'tamanos' => ListadoDeTerminaciones::TAMANOS_DE_PAGINA,
            ],
            /*
             * La confirmación con la consecuencia (documento 3, 5.6), con el
             * nombre del empleado que el modal acaba de resolver. Se pide con
             * una recarga parcial, igual que la situación actual del salario en
             * movimientos.
             */
            'avisoDeTerminacion' => Inertia::optional(fn (): ?string => self::aviso($peticion)),
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeTerminaciones::desde($peticion);

        return Excel::download(
            new TerminacionesExport($listado),
            'terminaciones-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Registra la salida del empleado (documento 1, 7.1).
     *
     * El empleado, el tipo y el motivo los comprueba `RegistrarTerminacion`.
     * La terminación y el paso a inactivo van dentro de una sola transacción en
     * `Vinculacion::registrarTerminacion()`: no puede quedar una salida
     * registrada sobre alguien a quien el sistema le siga mandando alertas.
     */
    public function guardar(RegistrarTerminacion $peticion): RedirectResponse
    {
        $peticion->vinculacion()->registrarTerminacion(
            $peticion->tipoDeSalida(),
            $peticion->motivo(),
            $peticion->fechaDeSalida(),
            $peticion->observaciones(),
        );

        return redirect()
            ->route(Modulo::Terminaciones->nombreDeRuta())
            ->with('estado', 'Terminación registrada. El empleado pasó a inactivo.');
    }

    /**
     * La frase de confirmación para el empleado digitado, o nulo mientras el
     * modal no haya resuelto todavía a nadie.
     */
    private static function aviso(Request $peticion): ?string
    {
        $vinculacionId = $peticion->query('vinculacion_id');

        if (! is_numeric($vinculacionId)) {
            return null;
        }

        $vinculacion = Vinculacion::query()->activas()->find((int) $vinculacionId);

        if (! $vinculacion instanceof Vinculacion) {
            return null;
        }

        return AvisoDeTerminacion::para($vinculacion);
    }
}
