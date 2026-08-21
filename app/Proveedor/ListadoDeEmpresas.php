<?php

namespace App\Proveedor;

use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de empresas cliente del panel del proveedor (documento 4, 4.1).
 *
 * Es la pantalla desde la que se mira el negocio entero: ocho columnas, cuatro
 * filtros y una búsqueda por nombre o NIT. Dos de las ocho columnas todavía no
 * tienen de dónde salir y **se declaran en vez de inventarse**, que es el mismo
 * criterio con el que se construyó el inicio del panel:
 *
 * - **Pago.** «Al día, por vencer o en mora con los días de atraso» sale de
 *   `suscripciones` y `pagos`, que son F5-04. Hasta entonces la columna dice que
 *   el dato todavía no existe, y su filtro no se ofrece: un filtro que no puede
 *   distinguir nada es peor que ninguno.
 * - **Empleados frente al plan.** El número de empleados sí es real —se cuentan
 *   los vigentes de cada empresa—; lo que no existe todavía es el nivel de plan
 *   con sus cupos cerrados, así que el tope es el que se pactó con el cliente y
 *   quedó escrito en `plan_empleados_incluidos`.
 *
 * El resaltado cuando los empleados superan el plan sí está, porque el documento
 * lo pide con nombre propio: «es una conversación de venta».
 *
 * Aquí no se escribe ni un `where('tenant_id', ...)`. Contar los empleados de
 * una empresa se hace **entrando en su contexto** con `ContextoTenant`, que es
 * lo mismo que hace el recorrido de empresas del proceso diario: así el conteo
 * pasa por las dos capas de aislamiento en vez de saltárselas.
 */
class ListadoDeEmpresas
{
    public const TAMANOS_DE_PAGINA = [25, 50, 100];

    public const POR_PAGINA_POR_DEFECTO = 25;

    public const TODOS = 'todos';

    /**
     * Lo que se enseña en la columna «Pago» mientras no existan las
     * suscripciones. Un hueco declarado antes que una cifra inventada.
     */
    public const PAGO_PENDIENTE = 'Sin cobros todavía';

    /**
     * Las columnas por las que se puede ordenar. Lista blanca: cualquier otro
     * valor de la dirección se ignora.
     *
     * «Empleados» no está y no es un olvido: se cuenta empresa por empresa
     * dentro de su contexto, así que la base de datos no puede ordenar por ese
     * número sin sacarlo antes de su aislamiento.
     *
     * @var list<string>
     */
    private const ORDENAMIENTOS = ['empresa', 'plan', 'usuarios', 'ingreso', 'estado'];

    private function __construct(
        public readonly string $buscar,
        public readonly string $estado,
        public readonly string $plan,
        public readonly string $salud,
        public readonly string $orden,
        public readonly string $direccion,
        public readonly int $porPagina,
    ) {}

    public static function desde(Request $peticion): self
    {
        return new self(
            buscar: trim((string) $peticion->query('buscar', '')),
            estado: self::opcion($peticion, 'estado', EstadoDeEmpresa::valores()),
            plan: trim((string) $peticion->query('plan', '')),
            salud: self::opcion($peticion, 'salud', SaludDeUso::valores()),
            orden: self::opcion($peticion, 'orden', self::ORDENAMIENTOS, 'empresa'),
            direccion: self::opcion($peticion, 'direccion', ['asc', 'desc'], 'asc'),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * @return Builder<Tenant>
     */
    public function consulta(): Builder
    {
        $consulta = Tenant::query()->withCount('usuarios');

        if ($this->estado !== self::TODOS) {
            $consulta->where('estado', $this->estado);
        }

        if ($this->plan !== '') {
            $consulta->where('plan_nombre', $this->plan);
        }

        if ($this->salud !== self::TODOS) {
            SaludDeUso::from($this->salud)->filtrar($consulta, 'ultimo_acceso_en');
        }

        if ($this->buscar !== '') {
            $termino = '%'.$this->buscar.'%';

            $consulta->where(fn (Builder $campos) => $campos
                ->where('nombre', 'ilike', $termino)
                ->orWhere('nit', 'ilike', $termino));
        }

        return $this->ordenar($consulta);
    }

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function pagina(): LengthAwarePaginator
    {
        return $this->consulta()->paginate($this->porPagina)->withQueryString();
    }

    public function tieneFiltros(): bool
    {
        return $this->aplicados() !== [];
    }

    /**
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
                'texto' => 'Estado: '.EstadoDeEmpresa::from($this->estado)->etiqueta(),
            ];
        }

        if ($this->plan !== '') {
            $etiquetas[] = ['clave' => 'plan', 'texto' => 'Plan: '.$this->plan];
        }

        if ($this->salud !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'salud',
                'texto' => 'Uso: '.SaludDeUso::from($this->salud)->etiqueta(),
            ];
        }

        return $etiquetas;
    }

    /**
     * @return array<string, string|int>
     */
    public function paraLaInterfaz(): array
    {
        return [
            'buscar' => $this->buscar,
            'estado' => $this->estado,
            'plan' => $this->plan,
            'salud' => $this->salud,
            'orden' => $this->orden,
            'direccion' => $this->direccion,
            'porPagina' => $this->porPagina,
        ];
    }

    /**
     * Una fila del listado.
     *
     * El plan se lee «Pequeña · 50 empleados · 2 usuarios», con las tres cosas
     * que el documento pide en esa columna: nivel contratado, empleados y
     * usuarios incluidos.
     *
     * @return array<string, string|int|bool|null>
     */
    public static function fila(Tenant $empresa): array
    {
        $empleados = self::empleadosDe($empresa);
        $incluidos = (int) $empresa->getAttribute('plan_empleados_incluidos');
        $usuarios = (int) $empresa->getAttribute('usuarios_count');
        $ultimoIngreso = $empresa->getAttribute('ultimo_acceso_en');
        $salud = SaludDeUso::segunElUltimoIngreso($ultimoIngreso);
        $estado = $empresa->estado;

        return [
            'id' => $empresa->getKey(),
            'empresa' => (string) $empresa->getAttribute('nombre'),
            'nit' => (string) $empresa->getAttribute('nit'),
            'plan' => self::planDe($empresa),
            'empleados' => $empleados.' de '.$incluidos,
            // El resaltado de «es una conversación de venta». Lo decide el
            // servidor y no la pantalla: es la misma comparación que mañana
            // dispara el aviso de exceso de plan.
            'excedePlan' => $incluidos > 0 && $empleados > $incluidos,
            'usuarios' => $usuarios.' de '.(int) $empresa->getAttribute('plan_usuarios_incluidos'),
            'uso' => $salud->etiqueta(),
            'usoTono' => $salud->tono(),
            'ingreso' => SaludDeUso::textoDelUltimoIngreso($ultimoIngreso),
            'pago' => self::PAGO_PENDIENTE,
            'estado' => $estado->etiqueta(),
            'estadoTono' => $estado->tono(),
        ];
    }

    /**
     * El encabezado del prototipo: «12 activas · 1 suspendida · 2 en prueba».
     *
     * Sin filtros a propósito: responde a cuántos clientes hay, no a cuántos se
     * están viendo.
     *
     * @return list<array{estado: string, cuantas: int, texto: string}>
     */
    public static function conteos(): array
    {
        return array_map(function (EstadoDeEmpresa $estado): array {
            $cuantas = Tenant::query()->where('estado', $estado->value)->count();

            return [
                'estado' => $estado->etiqueta(),
                'cuantas' => $cuantas,
                'texto' => $estado->contadas($cuantas),
            ];
        }, EstadoDeEmpresa::cases());
    }

    /**
     * Los planes que hay pactados hoy, para el selector del filtro.
     *
     * Salen de lo escrito en las empresas y no de una tabla `planes`, que es
     * F5-04: mientras el plan sea el nombre que se le dijo al cliente, la lista
     * de niveles es la lista de nombres que se han usado.
     *
     * @return list<string>
     */
    public static function planes(): array
    {
        return Tenant::query()
            ->whereNotNull('plan_nombre')
            ->distinct()
            ->orderBy('plan_nombre')
            ->pluck('plan_nombre')
            ->map(fn (mixed $plan): string => (string) $plan)
            ->all();
    }

    /**
     * Cuántos empleados vigentes tiene una empresa.
     *
     * Se cuenta y no se lee de `tenants.empleados_cargados`: ese contador lo
     * refresca el proceso diario, que es F3-01, y un contador que nadie
     * actualiza todavía diría que todas las empresas tienen cero. Contar dentro
     * del contexto de la empresa es exacto y respeta las dos capas de
     * aislamiento.
     */
    public static function empleadosDe(Tenant $empresa): int
    {
        return app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn (): int => Vinculacion::query()->activas()->count(),
        );
    }

    /**
     * @param  Builder<Tenant>  $consulta
     * @return Builder<Tenant>
     */
    private function ordenar(Builder $consulta): Builder
    {
        $sentido = $this->direccion;

        match ($this->orden) {
            'plan' => $consulta->orderBy('plan_nombre', $sentido),
            'usuarios' => $consulta->orderBy('usuarios_count', $sentido),
            // Las empresas en las que no ha entrado nadie van al final en los
            // dos sentidos: son el caso que hay que mirar, no una fecha vieja.
            'ingreso' => $consulta->orderByRaw("ultimo_acceso_en {$sentido} nulls last"),
            'estado' => $consulta->orderBy('estado', $sentido),
            default => $consulta->orderBy('nombre', $sentido),
        };

        return $consulta->orderBy('id', 'asc');
    }

    /**
     * @param  list<string>  $permitidos
     */
    private static function opcion(
        Request $peticion,
        string $clave,
        array $permitidos,
        string $porDefecto = self::TODOS,
    ): string {
        $valor = (string) $peticion->query($clave, '');

        return in_array($valor, $permitidos, true) ? $valor : $porDefecto;
    }

    private static function porPagina(Request $peticion): int
    {
        $valor = (int) $peticion->query('porPagina', 0);

        return in_array($valor, self::TAMANOS_DE_PAGINA, true) ? $valor : self::POR_PAGINA_POR_DEFECTO;
    }

    private static function planDe(Tenant $empresa): string
    {
        $nombre = (string) $empresa->getAttribute('plan_nombre');

        return $nombre === '' ? 'Sin plan pactado' : $nombre;
    }
}
