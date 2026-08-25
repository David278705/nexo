<?php

namespace App\Movimientos;

use App\Contratacion\FiltraPorEmpleado;
use App\Contratacion\OrdenaPorEmpleado;
use App\Listados\Listado;
use App\Models\Movimiento;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de aumentos y promociones (documento 1, 8.1 · documento 3, 5.6) y
 * sus filtros.
 *
 * Una fila es un movimiento, no un empleado: a quien le subieron el salario dos
 * veces en el año aparece dos veces, cada una con su cargo y su salario
 * anteriores.
 *
 * El armazón —la dirección, el orden, la paginación y la consulta única que
 * comparten la pantalla y la exportación— lo pone `Listado`. Aquí queda solo lo
 * propio: sus tres filtros, las columnas ordenables y las filas.
 *
 * Los tres son los del documento 1, 11.3: rango de fecha de efectividad, tipo
 * de movimiento y cargo. El del cargo lo resuelve `FiltraPorEmpleado`, y es el
 * **cargo vigente** del empleado: filtrar por «Coordinadora de Operaciones»
 * trae los movimientos de quien hoy tiene ese cargo, incluida la promoción que
 * se lo dio.
 *
 * @extends Listado<Movimiento>
 */
class ListadoDeMovimientos extends Listado
{
    use FiltraPorEmpleado;
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $tipo,
        public readonly string $cargo,
        string $orden,
        string $direccion,
        int $porPagina,
    ) {
        parent::__construct($orden, $direccion, $porPagina);
    }

    public static function desde(Request $peticion): self
    {
        return new self(
            desde: self::fecha($peticion, 'desde'),
            hasta: self::fecha($peticion, 'hasta'),
            tipo: self::filtro($peticion, 'tipo', TipoDeMovimiento::valores()),
            cargo: self::texto($peticion, 'cargo'),
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * @return list<array{clave: string, texto: string}>
     */
    public function aplicados(): array
    {
        $etiquetas = [];

        if ($this->desde !== '') {
            $etiquetas[] = ['clave' => 'desde', 'texto' => 'Desde: '.self::enFormatoColombiano($this->desde)];
        }

        if ($this->hasta !== '') {
            $etiquetas[] = ['clave' => 'hasta', 'texto' => 'Hasta: '.self::enFormatoColombiano($this->hasta)];
        }

        if ($this->tipo !== self::TODOS) {
            $etiquetas[] = ['clave' => 'tipo', 'texto' => 'Tipo: '.TipoDeMovimiento::from($this->tipo)->etiqueta()];
        }

        if ($this->cargo !== '') {
            $etiquetas[] = ['clave' => 'cargo', 'texto' => 'Cargo: '.$this->cargo];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla y de la exportación (documento 3, sección 5.6).
     *
     * `cargo` es el cargo que el empleado tiene hoy, y es el único dato que la
     * tabla no enseña: está aquí porque toda exportación lleva documento,
     * nombre completo y cargo (documento 1, 11.2), y porque el cargo vigente no
     * es ninguno de los dos del movimiento en cuanto hay un movimiento
     * posterior.
     *
     * `reversible` dice si esta fila es el último movimiento de su empleado, que
     * es la única que se puede corregir o eliminar (REV-03). Va en la fila y no
     * se pregunta al pulsar porque es lo que decide si la tabla ofrece las dos
     * acciones: ofrecerlas para retirarlas después sería prometer algo que no
     * se puede hacer. Sale de la relación que `base()` ya trae cargada, así que
     * no cuesta una consulta por fila.
     *
     * @return array<string, string|int|bool>
     */
    public static function fila(Movimiento $movimiento): array
    {
        $vinculacion = $movimiento->vinculacion;
        $persona = $vinculacion->persona;

        return [
            'id' => (int) $movimiento->getKey(),
            'documento' => (string) $persona->numero_documento,
            'nombre' => $persona->nombreCompleto(),
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'tipo' => $movimiento->tipo->etiqueta(),
            'cargoAnterior' => $movimiento->cargo_anterior,
            'cargoNuevo' => $movimiento->cargo_nuevo,
            'salarioAnterior' => Formato::dinero($movimiento->getAttribute('salario_anterior')),
            'salarioNuevo' => Formato::dinero($movimiento->getAttribute('salario_nuevo')),
            'efectivo' => Formato::fecha($movimiento->fecha_efectividad),
            'reversible' => $movimiento->esElMasReciente(),
        ];
    }

    /**
     * @return Builder<Movimiento>
     */
    protected function base(): Builder
    {
        // `vinculacion.movimientos` es de REV-03: de ahí sale `reversible` sin
        // una consulta por fila. Son los movimientos de los empleados que ya
        // están en la página, no los de la empresa entera.
        return Movimiento::query()->with(['vinculacion.persona', 'vinculacion.movimientos']);
    }

    /**
     * @param  Builder<Movimiento>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->desde !== '') {
            $consulta->whereDate('fecha_efectividad', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_efectividad', '<=', $this->hasta);
        }

        if ($this->tipo !== self::TODOS) {
            $consulta->where('tipo', $this->tipo);
        }

        if ($this->cargo !== '') {
            self::filtrarPorCargoDeLaNovedad($consulta, $this->cargo);
        }
    }

    /**
     * @return array<string, callable(Builder<Movimiento>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'nombre' => fn (Builder $consulta, string $sentido) => self::porNombreDeLaPersonaDeLaNovedad($consulta, $sentido, 'movimientos'),
            'efectivo' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_efectividad', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'efectivo';
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'tipo' => $this->tipo,
            'cargo' => $this->cargo,
        ];
    }
}
