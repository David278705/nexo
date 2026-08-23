<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Contratacion\ListadoDeEmpleados;
use App\Exports\MovimientosExport;
use App\Http\Requests\RegistrarMovimiento;
use App\Models\Movimiento;
use App\Models\Vinculacion;
use App\Movimientos\ListadoDeMovimientos;
use App\Movimientos\TipoDeMovimiento;
use App\Presentacion\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as RespuestaInertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Aumentos y promociones (documento 1, 8.1).
 *
 * Usa el mismo flujo compartido de registro que las tres novedades del
 * capítulo 6 —`ModalDeNovedad`, F2-03—, pero se diferencia de ellas en una
 * cosa que se ve en `guardar()`: **este módulo sí escribe en dos sitios**, el
 * histórico y el registro principal del empleado. Van juntos y en una sola
 * transacción, y eso lo resuelve `Vinculacion::registrarMovimiento()`.
 *
 * El acceso lo verifica el middleware `modulo:movimientos`.
 */
class MovimientoController extends Controller
{
    public function index(Request $peticion): RespuestaInertia
    {
        $listado = ListadoDeMovimientos::desde($peticion);

        return Inertia::render('Movimientos/Index', [
            ...$listado->paraLaPantalla(
                'movimientos',
                fn (Movimiento $movimiento): array => ListadoDeMovimientos::fila($movimiento),
            ),
            'opciones' => [
                'tipos' => TipoDeMovimiento::paraElegir(),
                'tiposDelFormulario' => TipoDeMovimiento::paraElFormulario(),
                // Los cargos que existen hoy en la empresa, para el filtro de
                // «Cargo» (documento 1, 11.3). Es la misma lista del listado de
                // empleados: los cargos son de la vinculación, no del módulo.
                'cargos' => ListadoDeEmpleados::cargos(),
                'tamanos' => ListadoDeMovimientos::TAMANOS_DE_PAGINA,
            ],
            /*
             * El cargo y el salario vigentes del empleado que el modal acaba de
             * resolver (documento 1, 8.1: «se muestra el salario vigente»). Van
             * de solo lectura, y el nuevo cargo del formulario arranca en el
             * actual, que es lo que pide el documento 3, 5.6. Se piden con una
             * recarga parcial, como el resto del flujo: aquí no hay ninguna
             * consulta de `axios`.
             */
            'situacionActual' => Inertia::optional(fn (): ?array => self::situacionActual($peticion)),
        ]);
    }

    /**
     * El archivo con lo que está filtrado en pantalla.
     */
    public function exportar(Request $peticion): BinaryFileResponse
    {
        $listado = ListadoDeMovimientos::desde($peticion);

        return Excel::download(
            new MovimientosExport($listado),
            'movimientos-'.Carbon::now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Registra el aumento o la promoción (documento 1, 8.1).
     *
     * El empleado y las reglas de formato las comprueba `RegistrarMovimiento`.
     * Las dos escrituras —el histórico y el registro principal— van dentro de
     * una sola transacción en `Vinculacion::registrarMovimiento()`, para que no
     * pueda quedar un movimiento guardado sobre un empleado cuyo salario no
     * cambió.
     */
    public function guardar(RegistrarMovimiento $peticion): RedirectResponse
    {
        $peticion->vinculacion()->registrarMovimiento(
            $peticion->tipo(),
            $peticion->cargoNuevo(),
            $peticion->salarioNuevo(),
            $peticion->fechaDeEfectividad(),
        );

        return redirect()
            ->route(Modulo::Movimientos->nombreDeRuta())
            ->with('estado', 'Movimiento registrado. Salario y cargo actualizados.');
    }

    /**
     * El cargo y el salario que rigen hoy para el empleado digitado, o nulo
     * mientras el modal no haya resuelto todavía a nadie.
     *
     * @return array{cargo: string, salario: string}|null
     */
    private static function situacionActual(Request $peticion): ?array
    {
        $vinculacionId = $peticion->query('vinculacion_id');

        if (! is_numeric($vinculacionId)) {
            return null;
        }

        $vinculacion = Vinculacion::query()->activas()->find((int) $vinculacionId);

        if (! $vinculacion instanceof Vinculacion) {
            return null;
        }

        return [
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'salario' => Formato::dinero($vinculacion->getAttribute('salario')),
        ];
    }
}
