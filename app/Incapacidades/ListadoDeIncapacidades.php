<?php

namespace App\Incapacidades;

use App\Contratacion\OrdenaPorEmpleado;
use App\Listados\Listado;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Persona;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de incapacidades (documento 1, 6.3 · documento 3, 5.5) y sus
 * filtros.
 *
 * Una fila es una incapacidad, no un empleado: quien tuvo dos en el mismo mes
 * aparece dos veces, cada una con sus propias fechas.
 *
 * El armazón —la dirección, el orden, la paginación y la consulta única que
 * comparten la pantalla y la exportación— lo pone `Listado`. Aquí queda solo lo
 * propio: sus tres filtros, sus columnas ordenables y sus filas.
 *
 * **El código de diagnóstico no se filtra ni se oculta aquí.** Quien llega a
 * este listado ya pasó el middleware `modulo:incapacidades`, que es el único
 * permiso que la sección 6.3 exige para verlo.
 *
 * @extends Listado<Incapacidad>
 */
class ListadoDeIncapacidades extends Listado
{
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $origen,
        public readonly string $eps,
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
            origen: self::filtro($peticion, 'origen', OrigenIncapacidad::valores()),
            eps: self::filtro($peticion, 'eps', self::valoresDeEps()),
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

        if ($this->origen !== self::TODOS) {
            $etiquetas[] = ['clave' => 'origen', 'texto' => 'Origen: '.OrigenIncapacidad::from($this->origen)->etiqueta()];
        }

        if ($this->eps !== self::TODOS) {
            $etiquetas[] = ['clave' => 'eps', 'texto' => 'EPS: '.self::textoDe(self::opcionesDeEps(), $this->eps)];
        }

        return $etiquetas;
    }

    /**
     * Una fila de la tabla y de la exportación (documento 3, sección 5.5).
     *
     * **El diagnóstico viaja siempre.** Es la fila que arma la pantalla del
     * módulo, y a esa pantalla no llega quien no tiene el permiso: no hay un
     * segundo lector de esta fila que necesite verla recortada.
     *
     * @return array<string, string|int>
     */
    public static function fila(Incapacidad $incapacidad): array
    {
        $vinculacion = $incapacidad->vinculacion;
        $persona = $vinculacion->persona;

        return [
            'id' => (int) $incapacidad->getKey(),
            'documento' => (string) $persona->numero_documento,
            'nombre' => $persona->nombreCompleto(),
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'inicio' => Formato::fecha($incapacidad->fecha_inicio),
            'fin' => Formato::fecha($incapacidad->fecha_fin),
            'dias' => $incapacidad->dias(),
            'diagnostico' => $incapacidad->codigo_diagnostico,
            'origen' => $incapacidad->origen->etiqueta(),
            'prorroga' => $incapacidad->es_prorroga ? 'Sí' : 'No',
        ];
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function opcionesDeEps(): array
    {
        return Eps::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Eps $eps): array => ['valor' => (string) $eps->getKey(), 'texto' => (string) $eps->getAttribute('nombre')])
            ->values()
            ->all();
    }

    /**
     * @return Builder<Incapacidad>
     */
    protected function base(): Builder
    {
        return Incapacidad::query()->with(['vinculacion.persona', 'eps']);
    }

    /**
     * @param  Builder<Incapacidad>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->desde !== '') {
            $consulta->whereDate('fecha_inicio', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_inicio', '<=', $this->hasta);
        }

        if ($this->origen !== self::TODOS) {
            $consulta->where('origen', $this->origen);
        }

        if ($this->eps !== self::TODOS) {
            $consulta->where('eps_id', (int) $this->eps);
        }
    }

    /**
     * @return array<string, callable(Builder<Incapacidad>, string): mixed>
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
            'origen' => $this->origen,
            'eps' => $this->eps,
        ];
    }

    /**
     * El nombre de una persona a través de la vinculación de la incapacidad.
     *
     * Es un salto más que `OrdenaPorEmpleado`: ahí la tabla de partida ya es
     * `vinculaciones`, y aquí es `incapacidades`, así que la subconsulta pasa
     * por las dos con un `join` en vez de un `whereColumn` directo.
     *
     * @return Builder<Persona>
     */
    private static function dePersonaDeLaVinculacion(string $columna): Builder
    {
        return Persona::query()
            ->select($columna)
            ->join('vinculaciones', 'vinculaciones.persona_id', '=', 'personas.id')
            ->whereColumn('vinculaciones.id', 'incapacidades.vinculacion_id');
    }

    /**
     * @return list<string>
     */
    private static function valoresDeEps(): array
    {
        return self::valoresDe(self::opcionesDeEps());
    }
}
