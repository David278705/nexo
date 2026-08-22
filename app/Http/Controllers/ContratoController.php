<?php

namespace App\Http\Controllers;

use App\Contratacion\DetalleDelContrato;
use App\Contratacion\ListadoDeContratos;
use App\Contratacion\TipoContrato;
use App\Exports\ContratosExport;
use App\Http\Requests\CambiarATerminoIndefinido;
use App\Http\Requests\RenovarContrato;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El listado y el detalle de contratos (documento 3, sección 5.4).
 *
 * Aquí no se filtra, no se cuenta y no se formatea: eso es `ListadoDeContratos`
 * y `DetalleDelContrato`. Este controlador traduce la petición en props y la
 * exportación en archivo.
 *
 * Un contrato **es** una vinculación, así que el identificador de la dirección
 * es el mismo de la ficha del empleado. El enrutador lo resuelve por el modelo,
 * que pasa por el global scope del trait: el contrato de otra empresa responde
 * 404 sin que aquí se escriba una comprobación.
 */
class ContratoController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeContratos::desde($peticion);

        return Inertia::render('Contratos/Index', [
            ...$listado->paraLaPantalla(
                'contratos',
                fn (Vinculacion $vinculacion): array => ListadoDeContratos::fila($vinculacion),
            ),
            'opciones' => [
                'vencimientos' => ListadoDeContratos::RANGOS_DE_VENCIMIENTO,
                'contratos' => TipoContrato::paraElegir(),
                'prorrogas' => ListadoDeContratos::RANGOS_DE_PRORROGAS,
                'tamanos' => ListadoDeContratos::TAMANOS_DE_PAGINA,
            ],
            'conteos' => [
                'vencidos' => ListadoDeContratos::vencidosSinGestionar(),
                'porVencer' => ListadoDeContratos::porVencer((int) ListadoDeContratos::VENCE_POR_DEFECTO),
                'dias' => (int) ListadoDeContratos::VENCE_POR_DEFECTO,
            ],
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla, no con todo.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeContratos::desde($peticion);

        return Excel::download(
            new ContratosExport($listado),
            'contratos-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * El detalle, y con él el estado del modal de renovación.
     *
     * `tipo` y `fecha_fin` llegan cuando el modal está abierto y el usuario
     * acaba de cambiar uno de los dos: la pantalla vuelve a pedir solo la prop
     * `renovacion` y el servidor devuelve la proyección recalculada. Es lo que
     * evita que el modal cuente días en el navegador.
     */
    public function detalle(Request $peticion, Vinculacion $contrato): RespuestaInertia
    {
        return Inertia::render(
            'Contratos/Detalle',
            DetalleDelContrato::de($contrato)->paraLaPantalla([
                'tipo' => $peticion->query('tipo'),
                'fecha_fin' => $peticion->query('fecha_fin'),
            ]),
        );
    }

    /**
     * Registra la prórroga (documento 1, 5.5).
     *
     * Aquí no se decide nada: quién puede renovar y con qué fecha lo comprueba
     * `RenovarContrato`, y el tramo lo escribe `Vinculacion::renovar()`, que
     * mueve el historial y lo vigente en la misma transacción.
     */
    public function renovar(RenovarContrato $peticion, Vinculacion $contrato): RedirectResponse
    {
        $periodo = $contrato->renovar($peticion->prorroga(), $peticion->hasta());

        return redirect()
            ->route('contratos.detalle', $contrato)
            ->with('estado', 'Se registró la '.mb_strtolower($periodo->evento->etiqueta(), 'UTF-8')
                .'. El contrato vence ahora el '.Formato::fecha($peticion->hasta()).'.');
    }

    /**
     * Convierte el contrato a término indefinido (documento 1, 5.5).
     *
     * Es una confirmación y no un formulario: no llega ningún dato, así que aquí
     * no se lee ninguno. Que la acción exista en ese contrato lo comprueba
     * `CambiarATerminoIndefinido`, y el tramo lo escribe
     * `Vinculacion::cambiarATerminoIndefinido()`.
     */
    public function cambiarAIndefinido(
        CambiarATerminoIndefinido $peticion,
        Vinculacion $contrato,
    ): RedirectResponse {
        $periodo = $contrato->cambiarATerminoIndefinido();

        return redirect()
            ->route('contratos.detalle', $contrato)
            ->with('estado', 'El contrato quedó a término indefinido desde el '
                .Formato::fecha($periodo->fecha_inicio).'. La fecha de ingreso no cambió.');
    }
}
