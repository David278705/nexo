<?php

namespace App\Http\Controllers;

use App\Contratacion\EstadoVinculacion;
use App\Contratacion\ListadoDeEmpleados;
use App\Contratacion\TipoContrato;
use App\Exports\EmpleadosExport;
use App\Models\Vinculacion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El listado de empleados del documento 3, sección 5.3.
 *
 * Aquí no se filtra ni se cuenta nada: eso es `ListadoDeEmpleados`. Este
 * controlador solo traduce la petición en props y la exportación en archivo.
 *
 * El acceso lo verifica el middleware `modulo:empleados` en la ruta, así que
 * escribir la dirección a mano sin el módulo asignado devuelve 403 igual que si
 * la pantalla no existiera.
 */
class EmpleadoController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeEmpleados::desde($peticion);
        $pagina = $listado->pagina();

        return Inertia::render('Empleados/Index', [
            'empleados' => [
                'filas' => array_map(
                    fn (Vinculacion $vinculacion) => ListadoDeEmpleados::fila($vinculacion),
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
                'estados' => EstadoVinculacion::paraElegir(),
                'contratos' => TipoContrato::paraElegir(),
                'cargos' => ListadoDeEmpleados::cargos(),
                'tamanos' => ListadoDeEmpleados::TAMANOS_DE_PAGINA,
            ],
            'conteos' => ListadoDeEmpleados::conteos(),
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla, no con todo.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeEmpleados::desde($peticion);

        return Excel::download(
            new EmpleadosExport($listado),
            'empleados-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * La ficha del empleado, que llega completa en F1-07.
     *
     * Existe desde ya porque la fila completa de la tabla lleva a ella y un
     * listado cuyas filas no llevan a ninguna parte no se puede probar. Resuelve
     * la vinculación de verdad, así que la ficha de otra empresa responde 404
     * desde hoy.
     */
    public function ficha(Vinculacion $empleado): RespuestaInertia
    {
        return Inertia::render('SeccionPendiente', [
            'titulo' => 'Ficha de '.$empleado->persona->nombreCompleto(),
        ]);
    }

    /**
     * El registro de empleado, que es el asistente de cuatro pasos de F1-04.
     *
     * Mismo motivo que la ficha: el botón «Nuevo empleado» del encabezado y el
     * del estado vacío tienen que llevar a algún sitio.
     */
    public function nuevo(): RespuestaInertia
    {
        return Inertia::render('SeccionPendiente', ['titulo' => 'Nuevo empleado']);
    }
}
