<?php

namespace App\Http\Controllers;

use App\Contratacion\DetalleDelContrato;
use App\Contratacion\ListadoDeContratos;
use App\Contratacion\TipoContrato;
use App\Exports\ContratosExport;
use App\Models\Vinculacion;
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
        $pagina = $listado->pagina();

        return Inertia::render('Contratos/Index', [
            'contratos' => [
                'filas' => array_map(
                    fn (Vinculacion $vinculacion) => ListadoDeContratos::fila($vinculacion),
                    $pagina->items(),
                ),
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'total' => $pagina->total(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
            ],
            'filtros' => $listado->paraLaInterfaz(),
            'aplicados' => $listado->aplicados(),
            'tieneFiltros' => $listado->tieneFiltros(),
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

    public function detalle(Vinculacion $contrato): RespuestaInertia
    {
        return Inertia::render(
            'Contratos/Detalle',
            DetalleDelContrato::de($contrato)->paraLaPantalla(),
        );
    }
}
