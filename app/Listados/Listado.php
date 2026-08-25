<?php

namespace App\Listados;

use App\Presentacion\Formato;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El armazón que comparten todas las pantallas de listado.
 *
 * Los siete listados del documento 3 hacen lo mismo con datos distintos: leen
 * filtros de la dirección, los normalizan contra una lista blanca, arman **una
 * sola consulta** que sirve a la pantalla y a la exportación, ordenan por la
 * columna que se pulsó y paginan conservando la dirección. Todo eso vive aquí
 * una vez.
 *
 * Cada listado concreto declara lo único que es suyo:
 *
 * - `base()`, la consulta de partida —qué modelo y con qué relaciones—;
 * - `filtrar()`, sus filtros;
 * - `columnasDeOrden()`, el mapa de columna ordenable a cómo se ordena, que es
 *   además **la lista blanca**: no hay dos declaraciones que se puedan separar,
 *   porque el orden se valida contra las claves de ese mapa;
 * - `ordenPorDefecto()`, con qué se abre la pantalla;
 * - `filtrosParaLaInterfaz()` y `aplicados()`, sus filtros escritos para la
 *   pantalla.
 *
 * Lo que **no** se hereda a propósito es `fila()`: cada listado tiene sus
 * columnas y no hay nada que compartir. Sigue siendo estática en cada clase, y
 * la usan su pantalla y su exportación para que no puedan decir cosas distintas
 * de la misma fila.
 *
 * Aquí no se escribe ni un `where('tenant_id', ...)`: lo pone el trait
 * `PerteneceATenant` del modelo de cada listado.
 *
 * @template TModelo of Model
 */
abstract class Listado
{
    /**
     * Los tamaños de página del documento 3, sección 6.1.
     */
    public const TAMANOS_DE_PAGINA = [25, 50, 100];

    public const POR_PAGINA_POR_DEFECTO = 25;

    /**
     * El valor que, en un filtro de lista, no filtra nada.
     */
    public const TODOS = 'todos';

    protected function __construct(
        public readonly string $orden,
        public readonly string $direccion,
        public readonly int $porPagina,
    ) {}

    /**
     * La consulta de partida del listado, sin filtros ni orden.
     *
     * @return Builder<TModelo>
     */
    abstract protected function base(): Builder;

    /**
     * Los filtros propios del listado, aplicados sobre la consulta de partida.
     *
     * @param  Builder<TModelo>  $consulta
     */
    abstract protected function filtrar(Builder $consulta): void;

    /**
     * Las columnas por las que se puede ordenar y cómo se ordena cada una.
     *
     * Se lee como lista blanca: `?orden=salario` no está entre las claves, así
     * que no puede sacar una columna que la pantalla no muestra.
     *
     * @return array<string, callable(Builder<TModelo>, string): mixed>
     */
    abstract protected static function columnasDeOrden(): array;

    /**
     * La columna con la que se abre la pantalla.
     */
    abstract protected static function ordenPorDefecto(): string;

    /**
     * Los filtros propios, tal como los recibe la pantalla para repintar sus
     * controles con lo que ya está puesto.
     *
     * @return array<string, string|int>
     */
    abstract protected function filtrosParaLaInterfaz(): array;

    /**
     * Los filtros puestos, como etiquetas removibles encima de la tabla.
     *
     * @return list<array{clave: string, texto: string}>
     */
    abstract public function aplicados(): array;

    /**
     * La consulta con los filtros y el orden aplicados, sin paginar.
     *
     * Es la que usa la exportación, y la misma sobre la que la pantalla pide su
     * página. Que «Exportar 43 registros filtrados» diga la verdad depende de
     * que ambas armen la misma, y la única forma de garantizarlo es que solo
     * exista una.
     *
     * @return Builder<TModelo>
     */
    public function consulta(): Builder
    {
        $consulta = $this->base();

        $this->filtrar($consulta);

        return $this->ordenar($consulta);
    }

    /**
     * La página que pide la pantalla, con la dirección conservada.
     *
     * `withQueryString()` es lo que hace que al volver de la ficha los filtros
     * y la página sigan donde estaban (documento 3, sección 6.1).
     *
     * @return LengthAwarePaginator<int, TModelo>
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
     * filtros, la empresa todavía no tiene registros; con filtros, la búsqueda
     * no encontró nada y lo que se ofrece es quitarlos.
     */
    public function tieneFiltros(): bool
    {
        return $this->aplicados() !== [];
    }

    /**
     * Las props que toda pantalla de listado recibe: la página con sus filas ya
     * escritas y los tres bloques de filtros.
     *
     * Los ocho controladores de listado armaban este mismo literal a mano
     * —`filas`, `pagina`, `paginas`, `total`, `desde`, `hasta`, y debajo
     * `filtros`, `aplicados` y `tieneFiltros`—, palabra por palabra. Ocho copias
     * de la forma de una prop es la forma más fácil de que la pantalla y el
     * componente `Paginacion` dejen de entenderse en una sola de ellas.
     *
     * Se usa esparcida entre las props propias de cada pantalla:
     *
     *     return Inertia::render('Vacaciones/Index', [
     *         ...$listado->paraLaPantalla('vacaciones', ListadoDeVacaciones::fila(...)),
     *         'opciones' => [...],
     *     ]);
     *
     * `fila()` sigue sin heredarse —cada listado tiene sus columnas— y por eso
     * llega como argumento y no como método abstracto.
     *
     * @param  string  $clave  El nombre de la prop con la página, que es el del
     *                         módulo en plural: `empleados`, `vacaciones`…
     * @param  callable(TModelo): array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public function paraLaPantalla(string $clave, callable $fila): array
    {
        $pagina = $this->pagina();

        return [
            $clave => [
                'filas' => array_map($fila, $pagina->items()),
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'total' => $pagina->total(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
            ],
            'filtros' => $this->paraLaInterfaz(),
            'aplicados' => $this->aplicados(),
            'tieneFiltros' => $this->tieneFiltros(),
        ];
    }

    /**
     * Los filtros del listado más los tres que comparten todos.
     *
     * @return array<string, string|int>
     */
    public function paraLaInterfaz(): array
    {
        return [
            ...$this->filtrosParaLaInterfaz(),
            'orden' => $this->orden,
            'direccion' => $this->direccion,
            'porPagina' => $this->porPagina,
        ];
    }

    /**
     * El ordenamiento al pulsar el encabezado de columna.
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    protected function ordenar(Builder $consulta): Builder
    {
        $columnas = static::columnasDeOrden();
        $ordenar = $columnas[$this->orden] ?? $columnas[static::ordenPorDefecto()];

        $ordenar($consulta, $this->direccion);

        // El desempate, **en la misma dirección que se pidió**. Sin él, dos
        // filas con el mismo cargo pueden intercambiarse de sitio entre una
        // página y la siguiente y aparecer dos veces o ninguna. Desempatar
        // siempre ascendente rompía «lo más reciente primero» en
        // `direccion=desc`: con varias filas creadas en el mismo segundo, el
        // desempate las devolvía en el orden contrario al que pedía la
        // columna. Anotado desde REV-04, corregido el 2026-08-25.
        return $consulta->orderBy('id', $this->direccion);
    }

    /**
     * La columna de orden pedida por la dirección, cotejada contra el mapa.
     */
    protected static function ordenPedido(Request $peticion): string
    {
        return self::opcion(
            $peticion,
            'orden',
            array_keys(static::columnasDeOrden()),
            static::ordenPorDefecto(),
        );
    }

    protected static function direccionPedida(Request $peticion): string
    {
        return self::opcion($peticion, 'direccion', ['asc', 'desc'], 'asc');
    }

    protected static function porPagina(Request $peticion): int
    {
        $valor = (int) $peticion->query('porPagina', 0);

        return in_array($valor, self::TAMANOS_DE_PAGINA, true) ? $valor : self::POR_PAGINA_POR_DEFECTO;
    }

    /**
     * Un filtro de lista, que además de sus valores admite «todos».
     *
     * Un valor que no encaja no es un error: simplemente no filtra, y se cae al
     * valor por defecto del filtro.
     *
     * @param  list<string>  $permitidos
     */
    protected static function filtro(
        Request $peticion,
        string $clave,
        array $permitidos,
        string $porDefecto = self::TODOS,
    ): string {
        $valor = (string) $peticion->query($clave, '');

        if ($valor === self::TODOS) {
            return self::TODOS;
        }

        return self::enLista($valor, $permitidos, $porDefecto);
    }

    /**
     * Una opción cerrada, sin «todos»: el orden y la dirección, que no pueden
     * quedar sin valor porque la consulta los necesita.
     *
     * @param  list<string>  $permitidos
     */
    protected static function opcion(Request $peticion, string $clave, array $permitidos, string $porDefecto): string
    {
        return self::enLista((string) $peticion->query($clave, ''), $permitidos, $porDefecto);
    }

    /**
     * Un texto libre de la dirección, sin espacios sobrantes.
     */
    protected static function texto(Request $peticion, string $clave): string
    {
        return trim((string) $peticion->query($clave, ''));
    }

    /**
     * Una fecha de la dirección, en `AAAA-MM-DD` o nada.
     */
    protected static function fecha(Request $peticion, string $clave): string
    {
        $valor = (string) $peticion->query($clave, '');

        // Se exige el formato exacto en vez de dejar que Carbon adivine: «15»
        // interpretado como año 2015 filtraría de verdad y el usuario nunca
        // sabría por qué le faltan registros.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->format('Y-m-d');
        } catch (InvalidFormatException) {
            return '';
        }
    }

    /**
     * La fecha de un filtro, que llega de la dirección como `AAAA-MM-DD` y se
     * enseña en la etiqueta removible como la lee quien usa el sistema.
     */
    protected static function enFormatoColombiano(string $fecha): string
    {
        return Formato::fecha(Carbon::parse($fecha));
    }

    /**
     * Los valores de una lista de opciones `valor` / `texto`, para la lista
     * blanca del filtro que las ofrece.
     *
     * @param  list<array{valor: string, texto: string}>  $opciones
     * @return list<string>
     */
    protected static function valoresDe(array $opciones): array
    {
        return array_map(fn (array $opcion): string => $opcion['valor'], $opciones);
    }

    /**
     * El texto de una de esas opciones, que es el que va a su etiqueta
     * removible: la pantalla y el servidor nombran el filtro igual.
     *
     * @param  list<array{valor: string, texto: string}>  $opciones
     */
    protected static function textoDe(array $opciones, string $valor): string
    {
        foreach ($opciones as $opcion) {
            if ($opcion['valor'] === $valor) {
                return $opcion['texto'];
            }
        }

        return $valor;
    }

    /**
     * @param  list<string>  $permitidos
     */
    private static function enLista(string $valor, array $permitidos, string $porDefecto): string
    {
        return in_array($valor, $permitidos, true) ? $valor : $porDefecto;
    }
}
