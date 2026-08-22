<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Exports\LicenciasExport;
use App\Http\Requests\EditarLicencia;
use App\Http\Requests\RegistrarLicencia;
use App\Licencias\ListadoDeLicencias;
use App\Licencias\TipoDeLicencia;
use App\Models\Licencia;
use App\Novedades\DetalleDeNovedad;
use App\Presentacion\Formato;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El tercer módulo de novedades (documento 1, 6.5): licencia no remunerada,
 * ausencia y suspensión son un solo tipo con un selector de tres opciones.
 *
 * El acceso lo verifica el middleware `modulo:licencias`.
 */
class LicenciaController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeLicencias::desde($peticion);
        $pagina = $listado->pagina();

        return Inertia::render('Licencias/Index', [
            'licencias' => [
                'filas' => array_map(
                    fn (Licencia $licencia) => ListadoDeLicencias::fila($licencia),
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
                'tipos' => TipoDeLicencia::paraElegir(),
                'tamanos' => ListadoDeLicencias::TAMANOS_DE_PAGINA,
            ],
            /*
             * Los días corridos de la novedad que se está registrando,
             * calculados por el motor y ya escritos como «6 días». El modal
             * los pide de nuevo con una recarga parcial cada vez que cambia
             * una fecha; aquí no se resta ningún día en el navegador.
             */
            'diasDeLicencia' => Inertia::optional(fn (): ?string => self::diasDeLicencia($peticion)),
            /*
             * La novedad que se está editando o que se va a eliminar (F2-10).
             * La pantalla la pide al pulsar Editar o Eliminar.
             */
            DetalleDeNovedad::PROP => DetalleDeNovedad::prop($peticion, Licencia::class),
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeLicencias::desde($peticion);

        return Excel::download(
            new LicenciasExport($listado),
            'licencias-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Registra la licencia, ausencia o suspensión (documento 1, 6.5).
     *
     * El empleado y las reglas de formato las comprueba `RegistrarLicencia`.
     * El efecto sobre la acumulación de vacaciones no escribe nada aparte:
     * `MotorDeCalculo::acumulacionMes()` la resta la próxima vez que alguien
     * pida el saldo, si la empresa tiene activo el parámetro.
     */
    public function guardar(RegistrarLicencia $peticion): RedirectResponse
    {
        $vinculacion = $peticion->vinculacion();

        $vinculacion->licencias()->create([
            'tipo' => $peticion->validated('tipo'),
            'fecha_inicio' => $peticion->validated('fecha_inicio'),
            'fecha_fin' => $peticion->validated('fecha_fin'),
        ]);

        return redirect()
            ->route(Modulo::Licencias->nombreDeRuta())
            ->with('estado', 'Novedad registrada.');
    }

    /**
     * Corrige una novedad ya registrada (documento 1, 6.2): «cualquier
     * registro de novedad puede modificarse, sin importar su antigüedad».
     *
     * El tipo se puede cambiar y las fechas también; la acumulación de
     * vacaciones se recalcula sola porque la reducción no está guardada en
     * ninguna columna. El empleado no se toca: lo explica `EditarLicencia`.
     */
    public function editar(EditarLicencia $peticion, Licencia $licencia): RedirectResponse
    {
        $licencia->update([
            'tipo' => $peticion->validated('tipo'),
            'fecha_inicio' => $peticion->validated('fecha_inicio'),
            'fecha_fin' => $peticion->validated('fecha_fin'),
        ]);

        return redirect()
            ->route(Modulo::Licencias->nombreDeRuta())
            ->with('estado', 'Novedad actualizada.');
    }

    /**
     * Elimina la novedad (documento 1, 6.2). Es borrado lógico —la regla 4 del
     * proyecto— y la auditoría lo registra sola.
     *
     * La acumulación que la suspensión había reducido vuelve sin ninguna
     * operación inversa: `Licencia` usa borrado lógico y el motor deja de
     * verla en cuanto se elimina.
     */
    public function eliminar(Licencia $licencia): RedirectResponse
    {
        $licencia->delete();

        return redirect()
            ->route(Modulo::Licencias->nombreDeRuta())
            ->with('estado', 'Novedad eliminada.');
    }

    /**
     * Los días corridos de la novedad que se está digitando, o nulo mientras
     * las dos fechas no lleguen o no tengan sentido.
     */
    private static function diasDeLicencia(Request $peticion): ?string
    {
        $inicio = $peticion->query('fecha_inicio');
        $fin = $peticion->query('fecha_fin');

        if (! is_string($inicio) || ! is_string($fin) || $inicio === '' || $fin === '') {
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

        return Formato::dias(MotorDeCalculo::diasCorridos($desde, $hasta));
    }
}
