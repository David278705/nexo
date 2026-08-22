<?php

namespace App\Movimientos;

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
 * propio: el filtro de fechas y de tipo, las columnas ordenables y las filas.
 *
 * **El filtro de «Cargo» que también lista el documento 1, 11.3 es F4-01**,
 * igual que el de «Empleado» en los tres módulos de novedades: se hará para
 * todos a la vez, y adelantarlo en uno solo deja los otros con un filtro menos.
 *
 * @extends Listado<Movimiento>
 */
class ListadoDeMovimientos extends Listado
{
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $tipo,
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
     * @return array<string, string|int>
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
        ];
    }

    /**
     * @return Builder<Movimiento>
     */
    protected function base(): Builder
    {
        return Movimiento::query()->with(['vinculacion.persona']);
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
        ];
    }
}
