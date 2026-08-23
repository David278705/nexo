<?php

namespace App\Contratacion;

use App\Listados\Listado;
use App\Models\Vinculacion;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
 * El filtrado, el ordenamiento y la paginación se resuelven en el servidor, y
 * el armazón —normalizar la dirección, ordenar, paginar— lo pone `Listado`.
 * Aquí queda solo lo que es de este listado: sus seis filtros, sus columnas
 * ordenables y sus filas.
 *
 * @extends Listado<Vinculacion>
 */
class ListadoDeEmpleados extends Listado
{
    use FiltraPorEmpleado;
    use OrdenaPorEmpleado;

    protected function __construct(
        public readonly string $buscar,
        public readonly string $estado,
        public readonly string $contrato,
        public readonly string $cargo,
        public readonly string $ingresoDesde,
        public readonly string $ingresoHasta,
        string $orden,
        string $direccion,
        int $porPagina,
    ) {
        parent::__construct($orden, $direccion, $porPagina);
    }

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
            buscar: self::texto($peticion, 'buscar'),
            estado: self::filtro($peticion, 'estado', EstadoVinculacion::valores(), EstadoVinculacion::Activo->value),
            contrato: self::filtro($peticion, 'contrato', TipoContrato::valores()),
            cargo: self::texto($peticion, 'cargo'),
            ingresoDesde: self::fecha($peticion, 'ingresoDesde'),
            ingresoHasta: self::fecha($peticion, 'ingresoHasta'),
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
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
                'texto' => 'Ingreso desde: '.self::enFormatoColombiano($this->ingresoDesde),
            ];
        }

        if ($this->ingresoHasta !== '') {
            $etiquetas[] = [
                'clave' => 'ingresoHasta',
                'texto' => 'Ingreso hasta: '.self::enFormatoColombiano($this->ingresoHasta),
            ];
        }

        return $etiquetas;
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
            'ingreso' => Formato::fecha($vinculacion->fecha_ingreso),
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
     * @return Builder<Vinculacion>
     */
    protected function base(): Builder
    {
        return Vinculacion::query()->with('persona');
    }

    /**
     * @param  Builder<Vinculacion>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
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
            self::buscarPorNombreODocumento($consulta, $this->buscar);
        }
    }

    /**
     * @return array<string, callable(Builder<Vinculacion>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'documento' => fn (Builder $consulta, string $sentido) => $consulta
                ->orderBy(self::dePersona('numero_documento'), $sentido),
            'nombre' => self::porNombreDeLaPersona(...),
            'cargo' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('cargo', $sentido),
            'contrato' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('tipo_contrato', $sentido),
            'ingreso' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('fecha_ingreso', $sentido),
            'estado' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('estado', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'nombre';
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'buscar' => $this->buscar,
            'estado' => $this->estado,
            'contrato' => $this->contrato,
            'cargo' => $this->cargo,
            'ingresoDesde' => $this->ingresoDesde,
            'ingresoHasta' => $this->ingresoHasta,
        ];
    }
}
