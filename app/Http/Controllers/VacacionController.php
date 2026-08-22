<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Exports\VacacionesExport;
use App\Http\Requests\EditarVacacion;
use App\Http\Requests\RegistrarVacacion;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Novedades\DetalleDeNovedad;
use App\Presentacion\Formato;
use App\Vacaciones\AdvertenciaDeSaldo;
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
 * El acceso lo verifica el middleware `modulo:vacaciones`.
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
            /*
             * La franja permanente del formulario (documento 3, 5.5): el saldo
             * de hoy y, en cuanto hay un periodo con sentido, el proyectado a
             * la fecha de disfrute con la advertencia de F2-06 si no alcanza.
             */
            'saldoDeVacaciones' => Inertia::optional(fn (): ?array => self::saldoDeVacaciones($peticion)),
            /*
             * El periodo que se está editando o que se va a eliminar (F2-10).
             * La pantalla la pide al pulsar Editar o Eliminar; el listado no
             * paga nada por ofrecer las dos acciones.
             */
            DetalleDeNovedad::PROP => DetalleDeNovedad::prop($peticion, Vacacion::class),
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
     * Corrige un periodo ya registrado (documento 1, 6.2): «cualquier registro
     * de novedad puede modificarse, sin importar su antigüedad».
     *
     * El empleado no se toca —lo explica `EditarVacacion`— y el saldo no se
     * ajusta a mano: se recalcula solo la próxima vez que alguien lo pida,
     * porque no hay ninguna columna que corregir.
     */
    public function editar(EditarVacacion $peticion, Vacacion $vacacion): RedirectResponse
    {
        $vacacion->update([
            'fecha_inicio' => $peticion->validated('fecha_inicio'),
            'fecha_fin' => $peticion->validated('fecha_fin'),
        ]);

        return redirect()
            ->route(Modulo::Vacaciones->nombreDeRuta())
            ->with('estado', 'Vacaciones actualizadas.');
    }

    /**
     * Elimina el periodo (documento 1, 6.2). Es borrado lógico —la regla 4 del
     * proyecto— y la auditoría lo registra sola.
     *
     * Los días vuelven al saldo sin ninguna operación inversa: `Vacacion` usa
     * borrado lógico y `SaldoDeVacaciones` deja de verla en cuanto se elimina.
     * La consecuencia que el usuario acaba de leer la escribió
     * `ConfirmacionDeBorrado` con esa misma cuenta.
     */
    public function eliminar(Vacacion $vacacion): RedirectResponse
    {
        $vacacion->delete();

        return redirect()
            ->route(Modulo::Vacaciones->nombreDeRuta())
            ->with('estado', 'Vacaciones eliminadas.');
    }

    /**
     * Los días hábiles de las vacaciones que se están digitando, o nulo
     * mientras las dos fechas no lleguen o no tengan sentido.
     */
    private static function diasDeVacaciones(Request $peticion): ?string
    {
        $empresa = tenant_actual();
        $periodo = self::periodoDigitado($peticion);

        if ($empresa === null || $periodo === null) {
            return null;
        }

        [$desde, $hasta] = $periodo;

        return Formato::dias(MotorDeCalculo::diasHabilesVacaciones($desde, $hasta, $empresa));
    }

    /**
     * La franja permanente de saldo del formulario (documento 3, 5.5): el
     * saldo de hoy siempre, y el proyectado a la fecha de disfrute —con su
     * advertencia si no alcanza— en cuanto el periodo digitado tiene sentido.
     *
     * Nula mientras el modal no haya resuelto todavía al empleado: sin
     * vinculación no hay de quién calcular el saldo.
     *
     * @return array{saldoActual: string, saldoProyectado: string|null, advertencia: string|null}|null
     */
    private static function saldoDeVacaciones(Request $peticion): ?array
    {
        $vinculacionId = $peticion->query('vinculacion_id');

        if (! is_numeric($vinculacionId)) {
            return null;
        }

        $vinculacion = Vinculacion::query()->activas()->find((int) $vinculacionId);

        if (! $vinculacion instanceof Vinculacion) {
            return null;
        }

        $empresa = tenant_actual();
        $periodo = $empresa === null ? null : self::periodoDigitado($peticion);

        if ($periodo === null) {
            return AdvertenciaDeSaldo::paraLaPantalla($vinculacion, null, null);
        }

        [$desde, $hasta] = $periodo;

        return AdvertenciaDeSaldo::paraLaPantalla(
            $vinculacion,
            MotorDeCalculo::diasHabilesVacaciones($desde, $hasta, $empresa),
            $hasta,
        );
    }

    /**
     * Las dos fechas digitadas, convertidas y en orden, o nulo mientras no
     * lleguen las dos o no tengan sentido.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private static function periodoDigitado(Request $peticion): ?array
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

        return $hasta->lessThan($desde) ? null : [$desde, $hasta];
    }
}
