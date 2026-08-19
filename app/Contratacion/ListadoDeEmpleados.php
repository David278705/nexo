<?php

namespace App\Contratacion;

use App\Models\Persona;
use App\Models\Vinculacion;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El listado de empleados (documento 3, sección 5.3) y sus filtros.
 *
 * Un empleado del listado es **una vinculación**, no una persona: dos
 * vinculaciones de la misma persona son dos filas, que es justamente lo que
 * hace que la rotación cuente vínculos y no cabezas (regla 5 del proyecto).
 *
 * Vive fuera del controlador porque lo usan dos caminos que no pueden
 * discrepar: la pantalla y la exportación. Que «Exportar 43 registros
 * filtrados» diga la verdad depende de que ambos armen la misma consulta, y la
 * única forma de garantizarlo es que solo exista una.
 *
 * Todo el filtrado, el ordenamiento y la paginación se resuelven en el
 * servidor. Aquí no se escribe ni un `where('tenant_id', ...)`: lo pone el
 * trait `PerteneceATenant` de `Vinculacion`.
 */
class ListadoDeEmpleados
{
    /**
     * Los tamaños de página del documento 3, sección 6.1.
     */
    public const TAMANOS_DE_PAGINA = [25, 50, 100];

    public const POR_PAGINA_POR_DEFECTO = 25;

    /**
     * El valor del filtro de estado que no filtra nada.
     */
    public const TODOS = 'todos';

    /**
     * Las columnas por las que se puede ordenar, con el nombre que viaja en la
     * dirección. Se lee como lista blanca: cualquier otro valor se ignora, así
     * que `?orden=salario` no puede sacar una columna que la pantalla no
     * muestra.
     */
    private const ORDENAMIENTOS = ['documento', 'nombre', 'cargo', 'contrato', 'ingreso', 'estado'];

    private function __construct(
        public readonly string $buscar,
        public readonly string $estado,
        public readonly string $contrato,
        public readonly string $cargo,
        public readonly string $ingresoDesde,
        public readonly string $ingresoHasta,
        public readonly string $orden,
        public readonly string $direccion,
        public readonly int $porPagina,
    ) {}

    /**
     * Lee los filtros de la petición y los deja normalizados.
     *
     * Nada de lo que llega por la dirección se usa sin pasar por aquí: los
     * enumerados se cotejan contra sus casos, el tamaño de página contra los
     * tres permitidos y las fechas contra el formato. Un valor que no encaja no
     * es un error, simplemente no filtra.
     */
    public static function desde(Request $peticion): self
    {
        return new self(
            buscar: trim((string) $peticion->query('buscar', '')),
            estado: self::opcion($peticion, 'estado', EstadoVinculacion::valores(), EstadoVinculacion::Activo->value),
            contrato: self::opcion($peticion, 'contrato', TipoContrato::valores(), self::TODOS),
            cargo: trim((string) $peticion->query('cargo', '')),
            ingresoDesde: self::fecha($peticion, 'ingresoDesde'),
            ingresoHasta: self::fecha($peticion, 'ingresoHasta'),
            orden: self::opcion($peticion, 'orden', self::ORDENAMIENTOS, 'nombre'),
            direccion: self::opcion($peticion, 'direccion', ['asc', 'desc'], 'asc'),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * La consulta con los filtros aplicados, sin paginar.
     *
     * Es la que usa la exportación, y la misma sobre la que la pantalla pide su
     * página.
     *
     * @return Builder<Vinculacion>
     */
    public function consulta(): Builder
    {
        $consulta = Vinculacion::query()->with('persona');

        if ($this->estado !== self::TODOS) {
            $consulta->where('estado', $this->estado);
        }

        if ($this->contrato !== self::TODOS) {
            $consulta->where('tipo_contrato', $this->contrato);
        }

        if ($this->cargo !== '') {
            $consulta->where('cargo', $this->cargo);
        }

        if ($this->ingresoDesde !== '') {
            $consulta->whereDate('fecha_ingreso', '>=', $this->ingresoDesde);
        }

        if ($this->ingresoHasta !== '') {
            $consulta->whereDate('fecha_ingreso', '<=', $this->ingresoHasta);
        }

        if ($this->buscar !== '') {
            $this->buscarPorNombreODocumento($consulta);
        }

        return $this->ordenar($consulta);
    }

    /**
     * La página que pide la pantalla, con la dirección conservada.
     *
     * `withQueryString()` es lo que hace que al volver de la ficha los filtros
     * y la página sigan donde estaban (documento 3, sección 6.1).
     *
     * @return LengthAwarePaginator<int, Vinculacion>
     */
    public function pagina(): LengthAwarePaginator
    {
        return $this->consulta()->paginate($this->porPagina)->withQueryString();
    }

    /**
     * Cuántos registros trae la exportación. Es la cifra que el botón enseña
     * antes de pulsarlo: «Exportar 43 registros filtrados».
     */
    public function total(): int
    {
        return $this->consulta()->count();
    }

    /**
     * Si hay algún filtro puesto.
     *
     * Distingue las dos pantallas vacías del documento 3, sección 6.3: sin
     * filtros, la empresa todavía no tiene empleados; con filtros, la búsqueda
     * no encontró nada y lo que se ofrece es quitarlos.
     */
    public function tieneFiltros(): bool
    {
        return $this->aplicados() !== [];
    }

    /**
     * Los filtros puestos, como etiquetas removibles encima de la tabla.
     *
     * El estado sale también cuando vale «activo», que es el valor por defecto:
     * el listado no muestra a los inactivos y eso tiene que verse, o parece que
     * faltan empleados.
     *
     * @return list<array{clave: string, texto: string}>
     */
    public function aplicados(): array
    {
        $etiquetas = [];

        if ($this->buscar !== '') {
            $etiquetas[] = ['clave' => 'buscar', 'texto' => 'Búsqueda: '.$this->buscar];
        }

        if ($this->estado !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'estado',
                'texto' => 'Estado: '.EstadoVinculacion::from($this->estado)->etiqueta(),
            ];
        }

        if ($this->contrato !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'contrato',
                'texto' => 'Contrato: '.TipoContrato::from($this->contrato)->etiqueta(),
            ];
        }

        if ($this->cargo !== '') {
            $etiquetas[] = ['clave' => 'cargo', 'texto' => 'Cargo: '.$this->cargo];
        }

        if ($this->ingresoDesde !== '') {
            $etiquetas[] = [
                'clave' => 'ingresoDesde',
                'texto' => 'Ingreso desde: '.$this->enFormatoColombiano($this->ingresoDesde),
            ];
        }

        if ($this->ingresoHasta !== '') {
            $etiquetas[] = [
                'clave' => 'ingresoHasta',
                'texto' => 'Ingreso hasta: '.$this->enFormatoColombiano($this->ingresoHasta),
            ];
        }

        return $etiquetas;
    }

    /**
     * Los filtros tal como los recibe la pantalla, para repintar los controles
     * con lo que ya está puesto.
     *
     * @return array<string, string|int>
     */
    public function paraLaInterfaz(): array
    {
        return [
            'buscar' => $this->buscar,
            'estado' => $this->estado,
            'contrato' => $this->contrato,
            'cargo' => $this->cargo,
            'ingresoDesde' => $this->ingresoDesde,
            'ingresoHasta' => $this->ingresoHasta,
            'orden' => $this->orden,
            'direccion' => $this->direccion,
            'porPagina' => $this->porPagina,
        ];
    }

    /**
     * Una fila de la tabla y de la exportación.
     *
     * Las dos salen de aquí para que no puedan decir cosas distintas del mismo
     * empleado. La columna de saldo de vacaciones del prototipo no está: se
     * calcula, y el motor de cálculo es F2-02.
     *
     * @return array<string, string|int|null>
     */
    public static function fila(Vinculacion $vinculacion): array
    {
        $persona = $vinculacion->persona;
        $estado = $vinculacion->getAttribute('estado');

        return [
            'id' => $vinculacion->getKey(),
            'documento' => $persona->numero_documento,
            'nombre' => $persona->nombreCompleto(),
            'cargo' => (string) $vinculacion->getAttribute('cargo'),
            'contrato' => $vinculacion->tipo_contrato->etiqueta(),
            // En formato colombiano, que es como lo lee quien usa el sistema.
            'ingreso' => $vinculacion->fecha_ingreso->format('d/m/Y'),
            'estado' => $estado instanceof EstadoVinculacion ? $estado->etiqueta() : '',
            'activo' => $vinculacion->estaActiva() ? 1 : 0,
        ];
    }

    /**
     * Los cargos que existen hoy en la empresa, para el selector del filtro.
     *
     * No dependen de los demás filtros a propósito: una lista de cargos que
     * cambia según lo ya filtrado impide llegar al cargo que se busca.
     *
     * @return list<string>
     */
    public static function cargos(): array
    {
        return Vinculacion::query()
            ->select('cargo')
            ->distinct()
            ->orderBy('cargo')
            ->pluck('cargo')
            ->map(fn (mixed $cargo): string => (string) $cargo)
            ->all();
    }

    /**
     * Cuántos empleados activos e inactivos tiene la empresa, sin filtrar.
     *
     * Es el «34 activos · 9 inactivos» del encabezado del prototipo. Va sin
     * filtros porque responde a «cuánta gente tengo», no a «cuánta estoy
     * viendo».
     *
     * @return array{activos: int, inactivos: int}
     */
    public static function conteos(): array
    {
        $activos = Vinculacion::query()->activas()->count();

        return [
            'activos' => $activos,
            'inactivos' => Vinculacion::query()->count() - $activos,
        ];
    }

    /**
     * Busca por nombre o por documento en una sola caja.
     *
     * El nombre se compara concatenado para que «Juan Mejía» encuentre a quien
     * tiene el apellido en otra columna, y con `ilike` para que las mayúsculas
     * no importen. Va dentro de `whereHas`, así que la persona también pasa por
     * el filtro de empresa de su propio trait.
     *
     * @param  Builder<Vinculacion>  $consulta
     */
    private function buscarPorNombreODocumento(Builder $consulta): void
    {
        $termino = '%'.$this->buscar.'%';

        $consulta->whereHas('persona', function (Builder $persona) use ($termino): void {
            $persona->where(function (Builder $campos) use ($termino): void {
                $campos
                    ->whereRaw(
                        "concat_ws(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) ilike ?",
                        [$termino]
                    )
                    ->orWhere('numero_documento', 'ilike', $termino);
            });
        });
    }

    /**
     * El ordenamiento al pulsar el encabezado de columna.
     *
     * Documento y nombre viven en `personas`, así que se ordenan por subconsulta
     * en vez de por `join`: un `join` duplicaría filas si mañana la relación
     * dejara de ser una a una y, sobre todo, obligaría a calificar cada columna
     * con su tabla en el resto de la consulta.
     *
     * @param  Builder<Vinculacion>  $consulta
     * @return Builder<Vinculacion>
     */
    private function ordenar(Builder $consulta): Builder
    {
        $sentido = $this->direccion;

        match ($this->orden) {
            'documento' => $consulta->orderBy($this->dePersona('numero_documento'), $sentido),
            'nombre' => $consulta
                ->orderBy($this->dePersona('primer_apellido'), $sentido)
                ->orderBy($this->dePersona('primer_nombre'), $sentido),
            'cargo' => $consulta->orderBy('cargo', $sentido),
            'contrato' => $consulta->orderBy('tipo_contrato', $sentido),
            'estado' => $consulta->orderBy('estado', $sentido),
            default => $consulta->orderBy('fecha_ingreso', $sentido),
        };

        // El desempate. Sin él, dos empleados con el mismo cargo pueden
        // intercambiarse de sitio entre una página y la siguiente y aparecer
        // dos veces o ninguna.
        return $consulta->orderBy('id', 'asc');
    }

    /**
     * @return Builder<Persona>
     */
    private function dePersona(string $columna): Builder
    {
        return Persona::query()
            ->select($columna)
            ->whereColumn('personas.id', 'vinculaciones.persona_id');
    }

    /**
     * @param  list<string>  $permitidos
     */
    private static function opcion(Request $peticion, string $clave, array $permitidos, string $porDefecto): string
    {
        $valor = (string) $peticion->query($clave, '');

        if ($valor === self::TODOS) {
            return self::TODOS;
        }

        return in_array($valor, $permitidos, true) ? $valor : $porDefecto;
    }

    /**
     * Una fecha de la dirección, en `AAAA-MM-DD` o nada.
     */
    private static function fecha(Request $peticion, string $clave): string
    {
        $valor = (string) $peticion->query($clave, '');

        // Se exige el formato exacto en vez de dejar que Carbon adivine: «15»
        // interpretado como año 2015 filtraría de verdad y el usuario nunca
        // sabría por qué le faltan empleados.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->format('Y-m-d');
        } catch (InvalidFormatException) {
            return '';
        }
    }

    private static function porPagina(Request $peticion): int
    {
        $valor = (int) $peticion->query('porPagina', 0);

        return in_array($valor, self::TAMANOS_DE_PAGINA, true) ? $valor : self::POR_PAGINA_POR_DEFECTO;
    }

    private function enFormatoColombiano(string $fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }
}
