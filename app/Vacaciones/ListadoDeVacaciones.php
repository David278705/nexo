<?php

namespace App\Vacaciones;

use App\Contratacion\FiltraPorEmpleado;
use App\Contratacion\OrdenaPorEmpleado;
use App\Listados\Listado;
use App\Models\Vacacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de vacaciones (documento 1, 6.4 · documento 3, 5.5) y su filtro.
 *
 * Una fila es un periodo de vacaciones, no un empleado: quien tomó dos
 * periodos en el año aparece dos veces, cada una con sus propias fechas.
 *
 * El armazón —la dirección, el orden, la paginación y la consulta única que
 * comparten la pantalla y la exportación— lo pone `Listado`. Aquí queda solo
 * lo propio: sus tres filtros, las columnas ordenables y las filas.
 *
 * Los tres son los del documento 1, 11.3: rango de fechas, empleado y cargo.
 * Los dos últimos los resuelve `FiltraPorEmpleado`, compartido con los otros
 * cuatro listados que cuelgan de una vinculación.
 *
 * @extends Listado<Vacacion>
 */
class ListadoDeVacaciones extends Listado
{
    use FiltraPorEmpleado;
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $buscar,
        public readonly string $desde,
        public readonly string $hasta,
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
            buscar: self::texto($peticion, 'buscar'),
            desde: self::fecha($peticion, 'desde'),
            hasta: self::fecha($peticion, 'hasta'),
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

        if ($this->buscar !== '') {
            $etiquetas[] = ['clave' => 'buscar', 'texto' => 'Empleado: '.$this->buscar];
        }

        if ($this->desde !== '') {
            $etiquetas[] = ['clave' => 'desde', 'texto' => 'Desde: '.self::enFormatoColombiano($this->desde)];
        }

        if ($this->hasta !== '') {
            $etiquetas[] = ['clave' => 'hasta', 'texto' => 'Hasta: '.self::enFormatoColombiano($this->hasta)];
        }

        if ($this->cargo !== '') {
            $etiquetas[] = ['clave' => 'cargo', 'texto' => 'Cargo: '.$this->cargo];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla y de la exportación (documento 3, sección 5.5).
     *
     * @return array<string, string|int>
     */
    public static function fila(Vacacion $vacacion): array
    {
        $vinculacion = $vacacion->vinculacion;
        $persona = $vinculacion->persona;

        return [
            'id' => (int) $vacacion->getKey(),
            'documento' => (string) $persona->numero_documento,
            'nombre' => $persona->nombreCompleto(),
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'inicio' => Formato::fecha($vacacion->fecha_inicio),
            'fin' => Formato::fecha($vacacion->fecha_fin),
            'dias' => $vacacion->dias(),
        ];
    }

    /**
     * @return Builder<Vacacion>
     */
    protected function base(): Builder
    {
        return Vacacion::query()->with(['vinculacion.persona', 'vinculacion.tenant']);
    }

    /**
     * @param  Builder<Vacacion>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->buscar !== '') {
            self::buscarAlEmpleadoDeLaNovedad($consulta, $this->buscar);
        }

        if ($this->desde !== '') {
            $consulta->whereDate('fecha_inicio', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_inicio', '<=', $this->hasta);
        }

        if ($this->cargo !== '') {
            self::filtrarPorCargoDeLaNovedad($consulta, $this->cargo);
        }
    }

    /**
     * @return array<string, callable(Builder<Vacacion>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'nombre' => fn (Builder $consulta, string $sentido) => self::porNombreDeLaPersonaDeLaNovedad($consulta, $sentido, 'vacaciones'),
            'inicio' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_inicio', $sentido),
            'fin' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_fin', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'inicio';
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'buscar' => $this->buscar,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'cargo' => $this->cargo,
        ];
    }
}
