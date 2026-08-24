<?php

namespace App\Proveedor;

use App\Listados\Listado;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * El listado de empresas cliente del panel del proveedor (documento 4, 4.1).
 *
 * Es la pantalla desde la que se mira el negocio entero: ocho columnas, los
 * cuatro filtros del documento y una búsqueda por nombre o NIT.
 *
 * **La columna «Pago» dejó de ser un hueco en F5-04.** «Al día, por vencer o en
 * mora con los días de atraso» sale de la suscripción de cada empresa, y su
 * filtro —el cuarto que pide el documento— ya se ofrece. Una empresa sin
 * suscripción no cae en ninguno de los tres estados y su celda lo dice con
 * palabras: no se le cobra todavía.
 *
 * El tope de empleados sigue saliendo de `plan_empleados_incluidos`, que desde
 * F5-04 lo mantiene la propia suscripción: al abrirla o al cambiarle el plan, el
 * nombre y los dos cupos del catálogo se copian a la empresa en la misma
 * transacción. El resaltado cuando los empleados lo superan está porque el
 * documento lo pide con nombre propio: «es una conversación de venta».
 *
 * Aquí no se escribe ni un `where('tenant_id', ...)`. Contar los empleados de
 * una empresa se hace **entrando en su contexto** con `ContextoTenant`, que es
 * lo mismo que hace el recorrido de empresas del proceso diario: así el conteo
 * pasa por las dos capas de aislamiento en vez de saltárselas.
 *
 * El armazón es el mismo `Listado` de las pantallas del cliente, aunque el
 * modelo sea `Tenant` y no una tabla de negocio: filtrar, ordenar y paginar se
 * hacen igual en los dos lados del sistema.
 *
 * @extends Listado<Tenant>
 */
class ListadoDeEmpresas extends Listado
{
    protected function __construct(
        public readonly string $buscar,
        public readonly string $estado,
        public readonly string $plan,
        public readonly string $salud,
        public readonly string $pago,
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
            estado: self::filtro($peticion, 'estado', EstadoDeEmpresa::valores()),
            plan: self::texto($peticion, 'plan'),
            salud: self::filtro($peticion, 'salud', SaludDeUso::valores()),
            pago: self::filtro($peticion, 'pago', EstadoDeCobro::valores()),
            orden: self::ordenPedido($peticion),
            direccion: self::direccionPedida($peticion),
            porPagina: self::porPagina($peticion),
        );
    }

    /**
     * @return Builder<Tenant>
     */
    protected function base(): Builder
    {
        return Tenant::query()
            ->withCount('usuarios')
            ->with('suscripcion');
    }

    /**
     * @param  Builder<Tenant>  $consulta
     */
    protected function filtrar(Builder $consulta): void
    {
        if ($this->estado !== self::TODOS) {
            $consulta->where('estado', $this->estado);
        }

        if ($this->plan !== '') {
            $consulta->where('plan_nombre', $this->plan);
        }

        if ($this->salud !== self::TODOS) {
            SaludDeUso::from($this->salud)->filtrar($consulta, 'ultimo_acceso_en');
        }

        /*
         * El filtro de estado de pago (documento 4, 4.1). Va sobre la
         * suscripción y no sobre una columna de la empresa: quien no tenga
         * suscripción no cae en ninguno de los tres estados, que es lo correcto
         * —no se le cobra— y lo que `whereHas` hace solo.
         */
        if ($this->pago !== self::TODOS) {
            $cobro = EstadoDeCobro::from($this->pago);

            $consulta->whereHas('suscripcion', function (Builder $suscripcion) use ($cobro): void {
                /** @var Builder<Suscripcion> $suscripcion */
                $cobro->filtrar($suscripcion);
            });
        }

        if ($this->buscar !== '') {
            $termino = '%'.$this->buscar.'%';

            $consulta->where(fn (Builder $campos) => $campos
                ->where('nombre', 'ilike', $termino)
                ->orWhere('nit', 'ilike', $termino));
        }
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

        if ($this->pago !== self::TODOS) {
            $etiquetas[] = [
                'clave' => 'pago',
                'texto' => 'Pago: '.EstadoDeCobro::from($this->pago)->etiqueta(),
            ];
        }

        return $etiquetas;
    }

    /**
     * @return array<string, string|int>
     */
    protected function filtrosParaLaInterfaz(): array
    {
        return [
            'buscar' => $this->buscar,
            'estado' => $this->estado,
            'plan' => $this->plan,
            'salud' => $this->salud,
            'pago' => $this->pago,
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
        // La relación viene ya cargada por `base()`: pedirla como propiedad y no
        // con `->first()` es lo que evita una consulta por fila.
        $suscripcion = $empresa->suscripcion;

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
            'pago' => $suscripcion instanceof Suscripcion
                ? $suscripcion->textoDelCobro()
                : EstadoDeCobro::SIN_SUSCRIPCION,
            'pagoTono' => $suscripcion instanceof Suscripcion
                ? $suscripcion->cobro()->tono()
                : 'neutro',
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
     * Las columnas por las que se puede ordenar, que son además la lista
     * blanca.
     *
     * «Empleados» no está y no es un olvido: se cuenta empresa por empresa
     * dentro de su contexto, así que la base de datos no puede ordenar por ese
     * número sin sacarlo antes de su aislamiento.
     *
     * @return array<string, callable(Builder<Tenant>, string): mixed>
     */
    protected static function columnasDeOrden(): array
    {
        return [
            'empresa' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('nombre', $sentido),
            'plan' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('plan_nombre', $sentido),
            'usuarios' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('usuarios_count', $sentido),
            // Las empresas en las que no ha entrado nadie van al final en los
            // dos sentidos: son el caso que hay que mirar, no una fecha vieja.
            'ingreso' => fn (Builder $consulta, string $sentido) => $consulta
                ->orderByRaw("ultimo_acceso_en {$sentido} nulls last"),
            'estado' => fn (Builder $consulta, string $sentido) => $consulta->orderBy('estado', $sentido),
        ];
    }

    protected static function ordenPorDefecto(): string
    {
        return 'empresa';
    }

    private static function planDe(Tenant $empresa): string
    {
        $nombre = (string) $empresa->getAttribute('plan_nombre');

        return $nombre === '' ? 'Sin plan pactado' : $nombre;
    }
}
