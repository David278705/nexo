<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Catalogos\Entidades;
use App\Catalogos\Ubicaciones;
use App\Contratacion\EstadoVinculacion;
use App\Contratacion\Genero;
use App\Contratacion\ListadoDeEmpleados;
use App\Contratacion\RegistroDeEmpleado;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoDocumento;
use App\Contratacion\TipoRiesgo;
use App\Exports\EmpleadosExport;
use App\Http\Requests\RegistrarEmpleado;
use App\Models\Vinculacion;
use Illuminate\Http\RedirectResponse;
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
     * El asistente de cuatro pasos del documento 1, secciones 5.1 a 5.3.
     *
     * Las listas viajan enteras con la pantalla y no se piden paso a paso: son
     * catálogos de unos cientos de filas, y traerlas de una vez es lo que
     * permite que el encadenamiento de departamento y ciudad sea instantáneo.
     */
    public function nuevo(): RespuestaInertia
    {
        return Inertia::render('Empleados/Nuevo', [
            'opciones' => [
                'documentos' => TipoDocumento::paraElegir(),
                'generos' => Genero::paraElegir(),
                'contratos' => TipoContrato::paraElegir(),
                'riesgos' => TipoRiesgo::paraElegir(),
                'contratoConFechaFin' => TipoContrato::Fijo->value,
                ...Entidades::paraFormulario(),
            ],
            'ubicaciones' => Ubicaciones::paraFormulario(),
        ]);
    }

    /**
     * Guarda lo que el asistente reunió en sus cuatro pasos.
     *
     * El controlador no decide nada: la validación es `RegistrarEmpleado` y la
     * escritura —buscar la persona antes de crearla y abrir la vinculación— es
     * `RegistroDeEmpleado`. Aquí solo se elige a dónde va el usuario después.
     *
     * Va al listado a propósito, y no a la ficha: quien acaba de registrar a
     * alguien casi siempre va a registrar a otro o a comprobar que el primero
     * quedó, y el listado responde a las dos cosas.
     */
    public function guardar(RegistrarEmpleado $peticion, RegistroDeEmpleado $registro): RedirectResponse
    {
        $vinculacion = $registro->ejecutar($peticion->validated());

        return redirect()
            ->route(Modulo::Empleados->nombreDeRuta())
            ->with('estado', $vinculacion->persona->nombreCompleto().' quedó registrado como empleado activo.');
    }
}
