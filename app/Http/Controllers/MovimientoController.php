<?php

namespace App\Http\Controllers;

use App\Autorizacion\Modulo;
use App\Contratacion\ListadoDeEmpleados;
use App\Exports\MovimientosExport;
use App\Http\Requests\EditarMovimiento;
use App\Http\Requests\RegistrarMovimiento;
use App\Models\Movimiento;
use App\Models\Vinculacion;
use App\Movimientos\ListadoDeMovimientos;
use App\Movimientos\TipoDeMovimiento;
use App\Novedades\ConfirmacionDeBorrado;
use App\Novedades\DetalleDeNovedad;
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
            /*
             * El movimiento que se está corrigiendo o que se va a eliminar
             * (REV-03). La misma prop, el mismo camino y el mismo componente
             * que en las tres novedades del capítulo 6.
             */
            DetalleDeNovedad::PROP => DetalleDeNovedad::prop($peticion, Movimiento::class),
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
     * Corrige un movimiento ya registrado (REV-03).
     *
     * El empleado no se toca y no se puede cambiar —lo explica
     * `EditarMovimiento`—, pero su cargo y su salario sí: si esta fila dice
     * ahora otra cifra, el registro principal tiene que decir la misma. Las dos
     * escrituras van juntas en `Vinculacion::corregirMovimiento()`.
     */
    public function editar(EditarMovimiento $peticion, Movimiento $movimiento): RedirectResponse
    {
        self::soloElMasReciente($movimiento);

        $movimiento->vinculacion->corregirMovimiento(
            $movimiento,
            $peticion->tipo(),
            $peticion->cargoNuevo(),
            $peticion->salarioNuevo(),
            $peticion->fechaDeEfectividad(),
        );

        return redirect()
            ->route(Modulo::Movimientos->nombreDeRuta())
            ->with('estado', 'Movimiento actualizado. Salario y cargo corregidos.');
    }

    /**
     * Elimina el movimiento (REV-03). Es borrado lógico —la regla 4— y la
     * auditoría lo registra sola.
     *
     * **Eliminar es volver al pasado**: el empleado recupera el cargo y el
     * salario que tenía justo antes, que es exactamente la consecuencia que
     * acaba de leer en la confirmación.
     */
    public function eliminar(Movimiento $movimiento): RedirectResponse
    {
        self::soloElMasReciente($movimiento);

        $movimiento->vinculacion->deshacerMovimiento($movimiento);

        return redirect()
            ->route(Modulo::Movimientos->nombreDeRuta())
            ->with('estado', 'Movimiento eliminado. Salario y cargo volvieron a como estaban.');
    }

    /**
     * La guardia de las dos acciones (REV-03): «solo se puede deshacer el
     * último de la lista».
     *
     * Vive en el controlador y no en el modelo ni en `EditarMovimiento` porque
     * es la misma para editar y para eliminar, y no es una regla de formato del
     * formulario sino del histórico: un movimiento guarda como anteriores el
     * cargo y el salario que había al registrarlo, así que el de encima cuenta
     * con los de este. Tocar uno de en medio dejaría al siguiente diciendo que
     * venía de un salario que ya no consta en ninguna parte.
     *
     * La pantalla ya no ofrece las acciones en esas filas —`reversible` de
     * `ListadoDeMovimientos::fila()`—; esto es lo que contesta si la petición
     * llega igual.
     */
    private static function soloElMasReciente(Movimiento $movimiento): void
    {
        if (! $movimiento->esElMasReciente()) {
            abort(403, ConfirmacionDeBorrado::HAY_UNO_POSTERIOR);
        }
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
