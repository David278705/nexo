<?php

namespace App\Licencias;

use App\Contratacion\OrdenaPorEmpleado;
use App\Listados\Listado;
use App\Models\Licencia;
use App\Models\Persona;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de licencias, ausencias y suspensiones (documento 1, 6.5 ·
 * documento 3, 5.5) y sus filtros.
 *
 * Una fila es una novedad, no un empleado: quien tuvo dos en el año aparece
 * dos veces, cada una con sus propias fechas.
 *
 * El armazón —la dirección, el orden, la paginación y la consulta única que
 * comparten la pantalla y la exportación— lo pone `Listado`. Aquí queda solo
 * lo propio: el filtro de fechas y de tipo, las columnas ordenables y las
 * filas.
 *
 * **El filtro de tipo y el de rango de fechas son los únicos de esta tarea.**
 * El filtro de «Empleado» que también lista el documento 1, 11.3 es F4-01,
 * igual que en incapacidades y vacaciones.
 *
 * @extends Listado<Licencia>
 */
class ListadoDeLicencias extends Listado
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
            tipo: self::filtro($peticion, 'tipo', TipoDeLicencia::valores()),
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
            $etiquetas[] = ['clave' => 'tipo', 'texto' => 'Tipo: '.TipoDeLicencia::from($this->tipo)->etiqueta()];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla y de la exportación (documento 3, sección 5.5).
     *
     * @return array<string, string|int>
     */
    public static function fila(Licencia $licencia): array
    {
        $vinculacion = $licencia->vinculacion;
        $persona = $vinculacion->persona;

        return [
            'id' => (int) $licencia->getKey(),
            'documento' => (string) $persona->numero_documento,
            'nombre' => $persona->nombreCompleto(),
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'tipo' => $licencia->tipo->etiqueta(),
            'inicio' => Formato::fecha($licencia->fecha_inicio),
            'fin' => Formato::fecha($licencia->fecha_fin),
            'dias' => $licencia->dias(),
        ];
    }

    /**
     * @return Builder<Licencia>
     */
    protected function base(): Builder
    {
        return Licencia::query()->with(['vinculacion.persona']);
    }

    /**
     * @param  Builder<Licencia>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->desde !== '') {
            $consulta->whereDate('fecha_inicio', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_inicio', '<=', $this->hasta);
        }

        if ($this->tipo !== self::TODOS) {
            $consulta->where('tipo', $this->tipo);
        }
    }

    /**
     * @return array<string, callable(Builder<Licencia>, string): mixed>
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
            'tipo' => $this->tipo,
        ];
    }

    /**
     * El nombre de una persona a través de la vinculación de la licencia.
     *
     * @return Builder<Persona>
     */
    private static function dePersonaDeLaVinculacion(string $columna): Builder
    {
        return Persona::query()
            ->select($columna)
            ->join('vinculaciones', 'vinculaciones.persona_id', '=', 'personas.id')
            ->whereColumn('vinculaciones.id', 'licencias.vinculacion_id');
    }
}
