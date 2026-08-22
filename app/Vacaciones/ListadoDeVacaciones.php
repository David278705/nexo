<?php

namespace App\Vacaciones;

use App\Contratacion\OrdenaPorEmpleado;
use App\Listados\Listado;
use App\Models\Persona;
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
 * lo propio: el filtro de fechas, las columnas ordenables y las filas.
 *
 * **El filtro de rango de fechas es el único de esta tarea.** El documento 1,
 * 11.3 también lista «Empleado» y «Cargo» para este módulo, pero esos dos
 * filtros —igual que la búsqueda por nombre o documento que le falta a
 * incapacidades— son F4-01, que es la tarea que empareja los filtros de cada
 * módulo con el documento 1 completo.
 *
 * @extends Listado<Vacacion>
 */
class ListadoDeVacaciones extends Listado
{
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
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
        if ($this->desde !== '') {
            $consulta->whereDate('fecha_inicio', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_inicio', '<=', $this->hasta);
        }
    }

    /**
     * @return array<string, callable(Builder<Vacacion>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'nombre' => fn (Builder $consulta, string $sentido) => $consulta
                ->orderBy(self::dePersonaDeLaVinculacion('primer_apellido'), $sentido)
                ->orderBy(self::dePersonaDeLaVinculacion('primer_nombre'), $sentido),
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
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];
    }

    /**
     * El nombre de una persona a través de la vinculación de la vacación.
     *
     * @return Builder<Persona>
     */
    private static function dePersonaDeLaVinculacion(string $columna): Builder
    {
        return Persona::query()
            ->select($columna)
            ->join('vinculaciones', 'vinculaciones.persona_id', '=', 'personas.id')
            ->whereColumn('vinculaciones.id', 'vacaciones.vinculacion_id');
    }
}
