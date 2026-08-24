<?php

namespace App\Proveedor;

use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Listados\Listado;
use App\Models\AlertaEnviada;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\CorreoDeAlerta;
use App\Presentacion\Formato;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La consola de alertas del proveedor (documento 4, capítulo 7).
 *
 * Existe por una razón muy concreta: tarde o temprano un cliente va a decir
 * que nunca le llegó un aviso, y esa conversación se gana con evidencia. El
 * «hecho cuando» de la tarea es responder en menos de un minuto «¿a quién y
 * cuándo se envió esta alerta?», y para eso hay que poder verlas todas, de
 * cualquier empresa, en un solo sitio.
 *
 * ## Por qué esto no es un `Listado` más
 *
 * `alertas_enviadas` tiene el trait `PerteneceATenant` y su política de RLS,
 * como toda tabla de negocio (regla 1). La política de PostgreSQL no tiene una
 * excepción para el `owner`: sin una empresa fijada en la variable de sesión no
 * se ve ninguna fila, de ninguna empresa. Por eso una sola consulta que cruce
 * empresas no es posible —ni con el ORM ni con SQL directo— y esta clase hace
 * lo mismo que ya hace `ProcesoDiarioDeAlertas` y `ListadoDeEmpresas::empleadosDe()`:
 * recorre las empresas **una por una**, fijando el contexto en cada vuelta, y
 * combina el resultado en memoria.
 *
 * Ordenar y paginar, entonces, no pueden ser trabajo de la base de datos —no
 * hay una sola consulta a la que pedírselo— y se hacen aquí, sobre la lista ya
 * reunida. Es aceptable exactamente por lo que dice el documento 4, 16: «con
 * pocos clientes no hacen falta indicadores sofisticados». El día en que haya
 * cientos de empresas y miles de alertas por mes, esto necesita una tabla de
 * lectura que no dependa de RLS; hoy sería una complejidad que nadie necesita.
 *
 * La forma de las props (`filas`, `pagina`, `paginas`, `total`, `desde`,
 * `hasta`, `filtros`, `aplicados`, `tieneFiltros`) es la misma que arma
 * `Listado::paraLaPantalla()`, para que la pantalla y `useListado` no tengan
 * que saber que del otro lado no hay un `Eloquent\Builder`.
 */
final class ConsolaDeAlertas
{
    /**
     * Las columnas por las que se puede ordenar, y también la lista blanca.
     *
     * @var list<string>
     */
    private const COLUMNAS_DE_ORDEN = ['programada', 'enviada', 'empresa', 'tipo'];

    private const ORDEN_POR_DEFECTO = 'programada';

    private function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly string $empresa,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly string $orden,
        public readonly string $direccion,
        public readonly int $porPagina,
        private readonly int $pagina,
    ) {}

    public static function desde(Request $peticion): self
    {
        return new self(
            desde: self::fecha($peticion, 'desde'),
            hasta: self::fecha($peticion, 'hasta'),
            empresa: self::empresaFiltrada($peticion),
            tipo: self::filtro($peticion, 'tipo', array_map(
                fn (TipoDeAlerta $tipo): string => $tipo->value,
                TipoDeAlerta::cases(),
            )),
            estado: self::filtro($peticion, 'estado', array_map(
                fn (EstadoDeAlerta $estado): string => $estado->value,
                EstadoDeAlerta::cases(),
            )),
            orden: in_array((string) $peticion->query('orden'), self::COLUMNAS_DE_ORDEN, true)
                ? (string) $peticion->query('orden')
                : self::ORDEN_POR_DEFECTO,
            // Al revés que los listados del cliente: aquí se abre por lo más
            // reciente, que es lo que se consulta (igual que `ListadoDeAlertas`).
            direccion: in_array((string) $peticion->query('direccion'), ['asc', 'desc'], true)
                ? (string) $peticion->query('direccion')
                : 'desc',
            porPagina: in_array((int) $peticion->query('porPagina', 0), Listado::TAMANOS_DE_PAGINA, true)
                ? (int) $peticion->query('porPagina')
                : Listado::POR_PAGINA_POR_DEFECTO,
            pagina: max(1, (int) $peticion->query('page', 1)),
        );
    }

    /**
     * Todas las props de la pantalla, en la misma forma que `Listado::paraLaPantalla()`.
     *
     * @return array<string, mixed>
     */
    public function paraLaPantalla(): array
    {
        $todas = $this->filasOrdenadas();
        $total = count($todas);
        $paginas = max(1, (int) ceil($total / $this->porPagina));
        $paginaActual = min($this->pagina, $paginas);
        $inicio = ($paginaActual - 1) * $this->porPagina;
        $pagina = array_slice($todas, $inicio, $this->porPagina);

        return [
            'alertas' => [
                'filas' => array_map(
                    fn (array $item): array => self::fila($item['alerta'], $item['empresa']),
                    $pagina,
                ),
                'pagina' => $paginaActual,
                'paginas' => $paginas,
                'total' => $total,
                'desde' => $total === 0 ? 0 : $inicio + 1,
                'hasta' => $inicio + count($pagina),
            ],
            'filtros' => $this->filtrosParaLaInterfaz(),
            'aplicados' => $this->aplicados(),
            'tieneFiltros' => $this->aplicados() !== [],
        ];
    }

    /**
     * Una fila de la tabla, con la empresa a la que pertenece.
     *
     * @return array<string, mixed>
     */
    public static function fila(AlertaEnviada $alerta, Tenant $empresa): array
    {
        $persona = $alerta->vinculacion?->persona;

        return [
            'id' => (int) $alerta->getKey(),
            'empresaId' => $empresa->getKey(),
            'empresa' => (string) $empresa->getAttribute('nombre'),
            'programada' => Formato::fecha($alerta->fecha_programada),
            'enviada' => Formato::fechaYHora($alerta->enviada_en),
            'tipo' => $alerta->tipo->etiqueta(),
            'empleado' => $persona?->nombreCompleto() ?? 'Toda la empresa',
            'destinatarios' => $alerta->destinatarios,
            'estado' => $alerta->estado->etiqueta(),
            'tono' => $alerta->estado->tono(),
            'intentos' => $alerta->intentos,
            'asunto' => $alerta->asunto,
            'parrafos' => CorreoDeAlerta::parrafosDe($alerta->cuerpo),
            'motivo' => (string) ($alerta->motivo_del_fallo ?? ''),
            'puedeReenviarse' => $alerta->puedeReenviarse(),
        ];
    }

    /**
     * Las empresas para el selector del filtro.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function empresasParaElegir(): array
    {
        return Tenant::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Tenant $empresa): array => [
                'valor' => (string) $empresa->getKey(),
                'texto' => (string) $empresa->getAttribute('nombre'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function tiposParaElegir(): array
    {
        return array_map(
            fn (TipoDeAlerta $tipo): array => ['valor' => $tipo->value, 'texto' => $tipo->etiqueta()],
            TipoDeAlerta::cases(),
        );
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    public static function estadosParaElegir(): array
    {
        return array_map(
            fn (EstadoDeAlerta $estado): array => ['valor' => $estado->value, 'texto' => $estado->etiqueta()],
            EstadoDeAlerta::cases(),
        );
    }

    /**
     * La vista de entregabilidad del capítulo 7: el porcentaje de correos
     * entregados en el mes en curso, de todas las empresas.
     *
     * **No es entregados frente a rebotados**, que es lo que pide el
     * documento, sino entregados frente a fallidos. «Rebotada» es un estado
     * que hoy nadie escribe: no hay proveedor de correo transaccional conectado
     * (F3-06b) y sin él nunca llega el aviso de rebote a la aplicación. La
     * cifra real que sí se puede dar —lo que el propio proveedor de correo
     * rechazó tras los tres intentos— es esta, y la pantalla lo dice con esas
     * palabras en vez de fingir una tasa de rebote que nadie mide.
     *
     * @return array{enviadas: int, fallidas: int, porcentaje: string|null}
     */
    public static function entregabilidadDelMes(): array
    {
        $desde = Carbon::now()->startOfMonth();
        $hasta = Carbon::now()->endOfMonth();
        $enviadas = 0;
        $fallidas = 0;

        foreach (Tenant::query()->orderBy('id')->get() as $empresa) {
            app(ContextoTenant::class)->paraTenant(
                $empresa,
                function () use ($desde, $hasta, &$enviadas, &$fallidas): void {
                    $enviadas += AlertaEnviada::query()
                        ->where('estado', EstadoDeAlerta::Enviada->value)
                        ->whereBetween('fecha_programada', [$desde, $hasta])
                        ->count();

                    $fallidas += AlertaEnviada::query()
                        ->where('estado', EstadoDeAlerta::Fallida->value)
                        ->whereBetween('fecha_programada', [$desde, $hasta])
                        ->count();
                }
            );
        }

        $resueltas = $enviadas + $fallidas;

        return [
            'enviadas' => $enviadas,
            'fallidas' => $fallidas,
            'porcentaje' => $resueltas === 0 ? null : Formato::porcentaje(($enviadas / $resueltas) * 100),
        ];
    }

    /**
     * Todas las filas que cumplen los filtros, de todas las empresas que toca
     * consultar, ya ordenadas.
     *
     * @return list<array{alerta: AlertaEnviada, empresa: Tenant}>
     */
    private function filasOrdenadas(): array
    {
        $filas = [];

        foreach ($this->tenantsAConsultar() as $empresa) {
            app(ContextoTenant::class)->paraTenant($empresa, function () use ($empresa, &$filas): void {
                $consulta = AlertaEnviada::query()->with('vinculacion.persona');

                $this->filtrar($consulta);

                foreach ($consulta->get() as $alerta) {
                    $filas[] = ['alerta' => $alerta, 'empresa' => $empresa];
                }
            });
        }

        usort($filas, fn (array $a, array $b): int => $this->comparar($a, $b));

        return $filas;
    }

    /**
     * Las empresas que hay que recorrer: solo una si el filtro la pidió, todas
     * en el orden contrario si no.
     *
     * @return Collection<int, Tenant>
     */
    private function tenantsAConsultar(): Collection
    {
        if ($this->empresa !== Listado::TODOS) {
            return Tenant::query()->where('id', (int) $this->empresa)->get();
        }

        return Tenant::query()->orderBy('id')->get();
    }

    /**
     * @param  Builder<AlertaEnviada>  $consulta
     */
    private function filtrar(Builder $consulta): void
    {
        if ($this->desde !== '') {
            $consulta->whereDate('fecha_programada', '>=', $this->desde);
        }

        if ($this->hasta !== '') {
            $consulta->whereDate('fecha_programada', '<=', $this->hasta);
        }

        if ($this->tipo !== Listado::TODOS) {
            $consulta->where('tipo', $this->tipo);
        }

        if ($this->estado !== Listado::TODOS) {
            $consulta->where('estado', $this->estado);
        }
    }

    /**
     * @param  array{alerta: AlertaEnviada, empresa: Tenant}  $a
     * @param  array{alerta: AlertaEnviada, empresa: Tenant}  $b
     */
    private function comparar(array $a, array $b): int
    {
        $valorDe = function (array $item): int|string {
            $enviadaEn = $item['alerta']->enviada_en;

            return match ($this->orden) {
                'enviada' => $enviadaEn instanceof Carbon ? $enviadaEn->timestamp : 0,
                'empresa' => (string) $item['empresa']->getAttribute('nombre'),
                'tipo' => $item['alerta']->tipo->etiqueta(),
                default => $item['alerta']->fecha_programada->timestamp,
            };
        };

        $comparacion = $valorDe($a) <=> $valorDe($b);

        // El desempate: sin él, dos alertas del mismo día pueden cambiar de
        // orden entre una página y la siguiente.
        if ($comparacion === 0) {
            $comparacion = $a['alerta']->getKey() <=> $b['alerta']->getKey();
        }

        return $this->direccion === 'desc' ? -$comparacion : $comparacion;
    }

    /**
     * @return list<array{clave: string, texto: string}>
     */
    private function aplicados(): array
    {
        $etiquetas = [];

        if ($this->desde !== '') {
            $etiquetas[] = ['clave' => 'desde', 'texto' => 'Desde: '.Formato::fecha(Carbon::parse($this->desde))];
        }

        if ($this->hasta !== '') {
            $etiquetas[] = ['clave' => 'hasta', 'texto' => 'Hasta: '.Formato::fecha(Carbon::parse($this->hasta))];
        }

        if ($this->empresa !== Listado::TODOS) {
            $nombre = Tenant::query()->find((int) $this->empresa)?->getAttribute('nombre');
            $etiquetas[] = ['clave' => 'empresa', 'texto' => 'Empresa: '.($nombre ?? $this->empresa)];
        }

        if ($this->tipo !== Listado::TODOS) {
            $etiquetas[] = ['clave' => 'tipo', 'texto' => 'Alerta: '.TipoDeAlerta::from($this->tipo)->etiqueta()];
        }

        if ($this->estado !== Listado::TODOS) {
            $etiquetas[] = ['clave' => 'estado', 'texto' => 'Estado: '.EstadoDeAlerta::from($this->estado)->etiqueta()];
        }

        return $etiquetas;
    }

    /**
     * @return array<string, string|int>
     */
    private function filtrosParaLaInterfaz(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'empresa' => $this->empresa,
            'tipo' => $this->tipo,
            'estado' => $this->estado,
            'orden' => $this->orden,
            'direccion' => $this->direccion,
            'porPagina' => $this->porPagina,
        ];
    }

    /**
     * Un filtro de lista, con «todos» como valor de reposo.
     *
     * Es el mismo criterio de `Listado::filtro()`, copiado aquí y no heredado:
     * esta clase no extiende `Listado` porque no hay ningún `Eloquent\Builder`
     * al que pedirle nada.
     *
     * @param  list<string>  $permitidos
     */
    private static function filtro(Request $peticion, string $clave, array $permitidos): string
    {
        $valor = (string) $peticion->query($clave, '');

        if ($valor === Listado::TODOS) {
            return Listado::TODOS;
        }

        return in_array($valor, $permitidos, true) ? $valor : Listado::TODOS;
    }

    /**
     * El filtro de empresa: un identificador numérico, o «todas».
     */
    private static function empresaFiltrada(Request $peticion): string
    {
        $valor = (string) $peticion->query('empresa', '');

        return $valor !== '' && ctype_digit($valor) ? $valor : Listado::TODOS;
    }

    /**
     * Una fecha de la dirección, en `AAAA-MM-DD` o nada. Copiado de
     * `Listado::fecha()` por el mismo motivo que `filtro()`.
     */
    private static function fecha(Request $peticion, string $clave): string
    {
        $valor = (string) $peticion->query($clave, '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->format('Y-m-d');
        } catch (InvalidFormatException) {
            return '';
        }
    }
}
