<?php

namespace App\Http\Controllers;

use App\Contratacion\DetalleDelContrato;
use App\Contratacion\ListadoDeContratos;
use App\Contratacion\TipoContrato;
use App\Contratacion\VigenciaDelContrato;
use App\Exports\ContratosExport;
use App\Http\Requests\CambiarATerminoIndefinido;
use App\Http\Requests\CorregirFechaEstimada;
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
                'estados' => VigenciaDelContrato::ESTADOS,
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
     * El detalle, y con él el estado de los modales de renovación y de cambio a
     * término indefinido.
     *
     * `tipo` y `fecha_fin` llegan cuando el modal de renovación está abierto y
     * el usuario acaba de cambiar uno de los dos; `fecha` cuando es el de
     * cambio a término indefinido el que está abierto y se acaba de editar la
     * fecha propuesta. En los dos casos la pantalla vuelve a pedir solo su prop
     * y el servidor devuelve el cálculo recalculado: es lo que evita que el
     * modal cuente un día por su cuenta.
     */
    public function detalle(Request $peticion, Vinculacion $contrato): RespuestaInertia
    {
        $tipo = $peticion->query('tipo');
        $fechaFin = $peticion->query('fecha_fin');
        $fecha = $peticion->query('fecha');

        return Inertia::render(
            'Contratos/Detalle',
            DetalleDelContrato::de($contrato)->paraLaPantalla(
                [
                    'tipo' => is_string($tipo) ? $tipo : null,
                    'fecha_fin' => is_string($fechaFin) ? $fechaFin : null,
                ],
                is_string($fecha) ? $fecha : null,
            ),
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
     * El único dato que llega es la fecha del cambio (HD-11), ya validada por
     * `CambiarATerminoIndefinido` contra el rango que la propia pantalla
     * enseña. El tramo lo escribe `Vinculacion::cambiarATerminoIndefinido()`.
     */
    public function cambiarAIndefinido(
        CambiarATerminoIndefinido $peticion,
        Vinculacion $contrato,
    ): RedirectResponse {
        $periodo = $contrato->cambiarATerminoIndefinido($peticion->fecha());

        return redirect()
            ->route('contratos.detalle', $contrato)
            ->with('estado', 'El contrato quedó a término indefinido desde el '
                .Formato::fecha($periodo->fecha_inicio).'. La fecha de ingreso no cambió.');
    }

    /**
     * Corrige la fecha de finalización estimada de una obra o labor (`PROD-23`).
     * El periodo de prueba sugerido se recalcula solo: no se guarda en ninguna
     * parte. La auditoría deja la fecha anterior y la nueva.
     */
    public function corregirFechaEstimada(CorregirFechaEstimada $peticion, Vinculacion $contrato): RedirectResponse
    {
        $fecha = $peticion->validated('fecha_fin_estimada');

        $contrato->forceFill(['fecha_fin_estimada' => $fecha === '' ? null : $fecha])->save();

        return redirect()
            ->route('contratos.detalle', $contrato)
            ->with('estado', $fecha === null || $fecha === ''
                ? 'Se quitó la fecha estimada: este contrato ya no tiene periodo de prueba sugerido.'
                : 'Se corrigió la fecha estimada al '.Formato::fecha(Carbon::parse($fecha))
                    .'. El periodo de prueba sugerido se recalculó con ella.');
    }
}
