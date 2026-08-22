<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Exports\VacacionesExport;
use App\Http\Requests\RegistrarVacacion;
use App\Models\Vacacion;
use App\Presentacion\Formato;
use App\Vacaciones\ListadoDeVacaciones;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El segundo módulo de novedades (documento 1, 6.4), y el primero que
 * descuenta del saldo.
 *
 * El acceso lo verifica el middleware `modulo:vacaciones`. La advertencia de
 * saldo insuficiente es F2-06 y no vive aquí.
 */
class VacacionController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeVacaciones::desde($peticion);
        $pagina = $listado->pagina();

        return Inertia::render('Vacaciones/Index', [
            'vacaciones' => [
                'filas' => array_map(
                    fn (Vacacion $vacacion) => ListadoDeVacaciones::fila($vacacion),
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
                'tamanos' => ListadoDeVacaciones::TAMANOS_DE_PAGINA,
            ],
            /*
             * Los días hábiles de las vacaciones que se están registrando,
             * calculados por el motor y ya escritos como «3 días». El modal
             * los pide de nuevo con una recarga parcial cada vez que cambia
             * una fecha; aquí no se resta ningún día en el navegador.
             */
            'diasDeVacaciones' => Inertia::optional(fn (): ?string => self::diasDeVacaciones($peticion)),
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeVacaciones::desde($peticion);

        return Excel::download(
            new VacacionesExport($listado),
            'vacaciones-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Registra el periodo de vacaciones (documento 1, 6.4).
     *
     * El empleado y las reglas de formato las comprueba `RegistrarVacacion`.
     * El descuento del saldo no escribe nada aparte: `SaldoDeVacaciones::al()`
     * resta esta fila la próxima vez que alguien pida el saldo, así que
     * guardarla es todo lo que hace falta.
     */
    public function guardar(RegistrarVacacion $peticion): RedirectResponse
    {
        $vinculacion = $peticion->vinculacion();

        $vinculacion->vacaciones()->create([
            'fecha_inicio' => $peticion->validated('fecha_inicio'),
            'fecha_fin' => $peticion->validated('fecha_fin'),
        ]);

        return redirect()
            ->route(Modulo::Vacaciones->nombreDeRuta())
            ->with('estado', 'Vacaciones registradas.');
    }

    /**
     * Los días hábiles de las vacaciones que se están digitando, o nulo
     * mientras las dos fechas no lleguen o no tengan sentido.
     */
    private static function diasDeVacaciones(Request $peticion): ?string
    {
        $inicio = $peticion->query('fecha_inicio');
        $fin = $peticion->query('fecha_fin');

        if (! is_string($inicio) || ! is_string($fin) || $inicio === '' || $fin === '') {
            return null;
        }

        $empresa = tenant_actual();

        if ($empresa === null) {
            return null;
        }

        try {
            $desde = Carbon::parse($inicio);
            $hasta = Carbon::parse($fin);
        } catch (Exception) {
            return null;
        }

        if ($hasta->lessThan($desde)) {
            return null;
        }

        return Formato::dias(MotorDeCalculo::diasHabilesVacaciones($desde, $hasta, $empresa));
    }
}
