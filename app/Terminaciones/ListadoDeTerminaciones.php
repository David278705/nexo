<?php

namespace App\Terminaciones;

use App\Contratacion\OrdenaPorEmpleado;
use App\Listados\Listado;
use App\Models\Terminacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de terminaciones (documento 1, capítulo 7 · documento 3, 5.6) y
 * sus tres filtros, que son los que pide el documento 1, 11.3: rango de fecha
 * de salida, tipo de salida y motivo de la baja.
 *
 * Una fila es una salida, no una persona: quien entró, salió y volvió a entrar
 * aparece tantas veces como vínculos haya cerrado.
 *
 * El armazón —la dirección, el orden, la paginación y la consulta única que
 * comparten la pantalla y la exportación— lo pone `Listado`. Aquí queda solo lo
 * propio.
 *
 * @extends Listado<Terminacion>
 */
class ListadoDeTerminaciones extends Listado
{
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $tipo,
        public readonly string $motivo,
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
            tipo: self::filtro($peticion, 'tipo', TipoDeSalida::valores()),
            motivo: self::filtro($peticion, 'motivo', TipoDeSalida::todosLosMotivos()),
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
            $etiquetas[] = ['clave' => 'tipo', 'texto' => 'Tipo: '.TipoDeSalida::from($this->tipo)->etiqueta()];
        }

        if ($this->motivo !== self::TODOS) {
            $etiquetas[] = ['clave' => 'motivo', 'texto' => 'Motivo: '.$this->motivo];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla y de la exportación (documento 3, 5.6, y las siete
     * columnas del prototipo).
     *
     * `rotacion` no sale de ninguna columna: la deduce el tipo de salida
     * (documento 1, 7.4). `tono` viaja con ella para que el color de la
     * etiqueta lo decida el mismo sitio que el texto y no puedan separarse.
     *
     * @return array<string, string|int>
     */
    public static function fila(Terminacion $terminacion): array
    {
        $vinculacion = $terminacion->vinculacion;
        $persona = $vinculacion->persona;
        $categoria = $terminacion->categoriaDeRotacion();

        return [
            'id' => (int) $terminacion->getKey(),
            'documento' => (string) $persona->numero_documento,
            'nombre' => $persona->nombreCompleto(),
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'salida' => Formato::fecha($terminacion->fecha_salida),
            'tipo' => $terminacion->tipo_salida->etiqueta(),
            'motivo' => $terminacion->motivo,
            'rotacion' => $categoria->nombreCorto(),
            'tono' => $categoria->tono(),
            'observaciones' => (string) ($terminacion->observaciones ?? ''),
        ];
    }

    /**
     * @return Builder<Terminacion>
     */
    protected function base(): Builder
    {
        return Terminacion::query()->with(['vinculacion.persona']);
    }

    /**
     * @param  Builder<Terminacion>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->desde !== '') {
            $consulta->whereDate('fecha_salida', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_salida', '<=', $this->hasta);
        }

        if ($this->tipo !== self::TODOS) {
            $consulta->where('tipo_salida', $this->tipo);
        }

        if ($this->motivo !== self::TODOS) {
            $consulta->where('motivo', $this->motivo);
        }
    }

    /**
     * @return array<string, callable(Builder<Terminacion>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'nombre' => fn (Builder $consulta, string $sentido) => self::porNombreDeLaPersonaDeLaNovedad($consulta, $sentido, 'terminaciones'),
            'salida' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_salida', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'salida';
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
            'motivo' => $this->motivo,
        ];
    }
}
