<?php

namespace App\Proveedor;

use App\Autenticacion\Invitaciones;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Catalogo;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Eps;
use App\Models\Festivo;
use App\Models\FondoPension;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * Los catálogos del sistema, desde el panel del proveedor (documento 4,
 * capítulo 8).
 *
 * La regla del capítulo es de una línea: **estos datos cambian con el tiempo y
 * no pueden vivir en el código**. Agregar una EPS que acaba de nacer, corregir
 * el nombre de un municipio o cargar los festivos del año que viene no puede
 * exigir un despliegue, porque eso convierte una corrección de cinco minutos en
 * una versión nueva del producto.
 *
 * Cinco pestañas, como el prototipo, y cada una con un grado de escritura
 * distinto **a propósito**:
 *
 * | Pestaña | Qué se puede hacer |
 * |---|---|
 * | Constantes legales | Solo leer. Los valores viven en `config/reglas_laborales.php` porque así lo exige la regla del motor de cálculo; ver `ConstantesLegales`. |
 * | Festivos | Agregar, corregir el nombre y quitar, por año. Con el aviso del año siguiente sin cargar. |
 * | EPS y entidades | Alta, edición y desactivación de las cinco listas. |
 * | Ciudades | Alta, edición y desactivación, un departamento a la vez. |
 * | Umbrales | Solo leer. Los que ya existen dicen dónde viven; los que no, de qué decisión dependen. |
 *
 * **Nada se borra de verdad.** Las cinco listas de entidades y las ciudades ya
 * nacían con borrado lógico: una EPS que se liquida se desactiva, deja de
 * ofrecerse en los formularios y sigue leyéndose en los empleados que ya la
 * tenían. Los festivos son la excepción que se corrigió en esta tarea: la tabla
 * no tenía `deleted_at` y quitar un festivo mal cargado habría sido un borrado
 * físico, que es justo lo que la regla 4 del proyecto prohíbe.
 *
 * **Aquí no hay listado paginado y es una decisión.** Las listas se miran de a
 * una —una entidad de las cinco, un departamento de los treinta y tres, un año
 * de festivos—, y ninguna de esas vistas pasa de unas decenas de filas. Copiar
 * por cuarta vez el armazón de filtros, orden y paginación antes de que `REV-01`
 * lo unifique habría hecho esa tarea más cara, no más barata.
 */
class CatalogosDelSistema
{
    /**
     * Las cinco pestañas del prototipo, en su orden.
     *
     * @var list<array{clave: string, titulo: string}>
     */
    public const PESTANAS = [
        ['clave' => 'constantes', 'titulo' => 'Constantes legales'],
        ['clave' => 'festivos', 'titulo' => 'Festivos'],
        ['clave' => 'entidades', 'titulo' => 'EPS y entidades'],
        ['clave' => 'ciudades', 'titulo' => 'Ciudades'],
        ['clave' => 'umbrales', 'titulo' => 'Umbrales'],
    ];

    /**
     * Las cinco listas de entidades, con el modelo que cada una administra.
     *
     * Es el único sitio donde está escrito qué listas hay. La pantalla, la
     * validación y las tres acciones de escritura salen todas de aquí, así que
     * una lista nueva es un renglón y nada más.
     *
     * @var array<string, array{titulo: string, singular: string, codigo: string, modelo: class-string<Catalogo>}>
     */
    public const ENTIDADES = [
        'eps' => [
            'titulo' => 'EPS',
            'singular' => 'la EPS',
            'codigo' => 'Código de la Supersalud',
            'modelo' => Eps::class,
        ],
        'fondos-pension' => [
            'titulo' => 'Fondos de pensión',
            'singular' => 'el fondo de pensión',
            'codigo' => 'Código de la Superfinanciera',
            'modelo' => FondoPension::class,
        ],
        'arls' => [
            'titulo' => 'ARL',
            'singular' => 'la ARL',
            'codigo' => 'Código de la entidad',
            'modelo' => Arl::class,
        ],
        'cajas-compensacion' => [
            'titulo' => 'Cajas de compensación',
            'singular' => 'la caja de compensación',
            'codigo' => 'Código de la entidad',
            'modelo' => CajaCompensacion::class,
        ],
        'bancos' => [
            'titulo' => 'Bancos',
            'singular' => 'el banco',
            'codigo' => 'Código de la Superfinanciera',
            'modelo' => Banco::class,
        ],
    ];

    /**
     * A partir de qué mes se avisa que falta el calendario del año siguiente.
     *
     * Octubre, como dice el capítulo 8: «el panel avisa desde octubre si el año
     * siguiente no está cargado». Antes de octubre el aviso sería ruido, y en
     * enero ya es tarde: las alertas del primer día hábil del año se corren con
     * un calendario que no existe.
     */
    public const MES_DEL_AVISO = 10;

    /**
     * Lo que se dice cuando el calendario del año siguiente todavía no está.
     *
     * Nombra las tres cosas que dependen de esa tabla porque el aviso tiene que
     * explicar por qué corre prisa, no solo que algo falta.
     */
    public static function avisoDelAnioSiguiente(int $anio): string
    {
        return 'El calendario de festivos de '.$anio.' no está cargado. De esa tabla dependen el '
            .'cálculo de vacaciones en días hábiles, el de licencias y el corrimiento de todas las '
            .'alertas al día hábil anterior. Cárguelo antes de que termine el año.';
    }

    /**
     * Lo que se dice cuando falta el calendario del año en curso, que es peor.
     */
    public static function avisoDelAnioEnCurso(int $anio): string
    {
        return 'El calendario de festivos de '.$anio.' no está cargado, y '.$anio.' ya empezó. '
            .'Hasta que se cargue, los días hábiles se cuentan como si no hubiera ningún festivo.';
    }

    private function __construct(
        public readonly string $pestana,
        public readonly string $entidad,
        public readonly int $departamento,
        public readonly int $anio,
    ) {}

    /**
     * Lee de la dirección qué se está mirando.
     *
     * Todo va en la dirección y nada en la sesión: la pestaña, la lista y el año
     * que se están viendo se pueden copiar, compartir y volver a ellos después
     * de guardar, que es lo que hace que una alta encadenada no obligue a
     * navegar de nuevo hasta donde se estaba.
     */
    public static function desde(?string $pestana, ?string $entidad, ?int $departamento, ?int $anio): self
    {
        return new self(
            pestana: self::opcion($pestana, array_column(self::PESTANAS, 'clave'), 'constantes'),
            entidad: self::opcion($entidad, array_keys(self::ENTIDADES), 'eps'),
            departamento: $departamento ?? self::primerDepartamento(),
            anio: $anio ?? Carbon::now()->year,
        );
    }

    /**
     * Todas las props de la pantalla.
     *
     * @return array<string, mixed>
     */
    public function paraLaPantalla(): array
    {
        return [
            'pestanas' => self::PESTANAS,
            'viendo' => [
                'pestana' => $this->pestana,
                'entidad' => $this->entidad,
                'departamento' => $this->departamento,
                'anio' => $this->anio,
            ],
            'aviso' => self::avisoDeFestivos(),
            'constantes' => [
                'filas' => ConstantesLegales::filas(),
                'nota' => ConstantesLegales::SOLO_LECTURA,
            ],
            'festivos' => [
                'filas' => self::festivos($this->anio),
                'anios' => self::aniosOfrecidos(),
            ],
            'entidades' => [
                'listas' => self::listasDeEntidades(),
                'filas' => self::entidades($this->entidad),
                'codigo' => self::ENTIDADES[$this->entidad]['codigo'],
            ],
            'ciudades' => [
                'departamentos' => self::departamentos(),
                'filas' => self::ciudades($this->departamento),
            ],
            'umbrales' => self::umbrales(),
        ];
    }

    /**
     * El aviso del calendario, si hay algo que avisar.
     *
     * @return array{tono: string, texto: string}|null
     */
    public static function avisoDeFestivos(): ?array
    {
        $hoy = Carbon::now();
        $cargados = Festivo::aniosCargados();

        if (! in_array($hoy->year, $cargados, true)) {
            return ['tono' => 'error', 'texto' => self::avisoDelAnioEnCurso($hoy->year)];
        }

        if ($hoy->month >= self::MES_DEL_AVISO && ! in_array($hoy->year + 1, $cargados, true)) {
            return ['tono' => 'advertencia', 'texto' => self::avisoDelAnioSiguiente($hoy->year + 1)];
        }

        return null;
    }

    /**
     * Los festivos de un año, en orden.
     *
     * @return list<array{id: int, fecha: string, dia: string, nombre: string, enSabado: bool}>
     */
    public static function festivos(int $anio): array
    {
        return Festivo::query()
            ->delAnio($anio)
            ->get()
            ->map(function (Festivo $festivo): array {
                $fecha = $festivo->fecha;

                return [
                    'id' => (int) $festivo->getKey(),
                    'fecha' => Formato::fecha($fecha),
                    'dia' => Formato::diaCompleto($fecha),
                    'nombre' => (string) $festivo->getAttribute('nombre'),
                    // Se marcan porque son los que más se olvidan al cargar el
                    // año y los únicos que solo afectan a una parte de los
                    // clientes: los que tienen el sábado laboral.
                    'enSabado' => $fecha->isSaturday(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Los años que se ofrecen en el selector: los cargados y el siguiente al
     * último, que es el que hay que llegar a cargar.
     *
     * @return list<int>
     */
    public static function aniosOfrecidos(): array
    {
        $cargados = Festivo::aniosCargados();
        $siguiente = ($cargados === [] ? Carbon::now()->year - 1 : max($cargados)) + 1;

        $anios = array_values(array_unique([...$cargados, Carbon::now()->year, $siguiente]));
        sort($anios);

        return $anios;
    }

    /**
     * Las cinco listas de entidades, para el selector de la pestaña.
     *
     * @return list<array{valor: string, texto: string}>
     */
    public static function listasDeEntidades(): array
    {
        return array_map(
            fn (string $clave): array => [
                'valor' => $clave,
                'texto' => self::ENTIDADES[$clave]['titulo'],
            ],
            array_keys(self::ENTIDADES),
        );
    }

    /**
     * Las entidades de una de las cinco listas, activas y desactivadas.
     *
     * Aquí sí se enseña lo desactivado: es la pantalla desde la que se vuelve a
     * activar, y una entidad que desaparece de la vista no se puede recuperar.
     *
     * @return list<array{id: int, codigo: string, codigoValor: string, nombre: string, activo: bool, estado: string, tono: string}>
     */
    public static function entidades(string $clave): array
    {
        $modelo = self::modeloDe($clave);

        return $modelo::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Catalogo $entidad): array => [
                'id' => (int) $entidad->getKey(),
                // Dos veces el mismo dato y a propósito: `codigo` es lo que se
                // lee en la tabla, con la raya cuando no hay ninguno, y
                // `codigoValor` lo que se le pone al formulario de edición. Sin
                // la segunda, corregir un nombre escribiría la raya como código.
                'codigo' => (string) ($entidad->getAttribute('codigo') ?? Formato::VACIO),
                'codigoValor' => (string) ($entidad->getAttribute('codigo') ?? ''),
                'nombre' => (string) $entidad->getAttribute('nombre'),
                'activo' => $entidad->getAttribute('activo') === true,
                'estado' => $entidad->getAttribute('activo') === true ? 'Se ofrece' : 'Desactivada',
                'tono' => $entidad->getAttribute('activo') === true ? 'exito' : 'neutro',
            ])
            ->values()
            ->all();
    }

    /**
     * @return class-string<Catalogo>
     */
    public static function modeloDe(string $clave): string
    {
        abort_unless(array_key_exists($clave, self::ENTIDADES), 404);

        return self::ENTIDADES[$clave]['modelo'];
    }

    /**
     * Los treinta y tres departamentos, para el selector de la pestaña.
     *
     * @return list<array{valor: int, texto: string}>
     */
    public static function departamentos(): array
    {
        return Departamento::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (Departamento $departamento): array => [
                'valor' => (int) $departamento->getKey(),
                'texto' => (string) $departamento->getAttribute('nombre'),
            ])
            ->values()
            ->all();
    }

    /**
     * Los municipios de un departamento.
     *
     * Un departamento a la vez y no los mil cien juntos: es como se eligen en el
     * formulario de empleado y es la única forma de que la lista quepa en una
     * pantalla sin inventarle filtros.
     *
     * @return list<array{id: int, codigo: string, codigoValor: string, nombre: string, activo: bool, estado: string, tono: string}>
     */
    public static function ciudades(int $departamento): array
    {
        return Ciudad::query()
            ->where('departamento_id', $departamento)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Ciudad $ciudad): array => [
                'id' => (int) $ciudad->getKey(),
                'codigo' => (string) ($ciudad->getAttribute('codigo_dane') ?? Formato::VACIO),
                'codigoValor' => (string) ($ciudad->getAttribute('codigo_dane') ?? ''),
                'nombre' => (string) $ciudad->getAttribute('nombre'),
                'activo' => $ciudad->getAttribute('activo') === true,
                'estado' => $ciudad->getAttribute('activo') === true ? 'Se ofrece' : 'Desactivada',
                'tono' => $ciudad->getAttribute('activo') === true ? 'exito' : 'neutro',
            ])
            ->values()
            ->all();
    }

    /**
     * La pestaña «Umbrales»: los números que no vienen de la ley.
     *
     * El capítulo 8 lista cuatro, y la pestaña dice la verdad de cada uno en vez
     * de ofrecer cuatro campos que escribirían un número inventado en alguna
     * parte: los umbrales que ya están en uso dicen dónde viven, y los que
     * faltan, de qué decisión dependen y en qué tarea se van a poder usar.
     *
     * La anticipación de la alerta de tope fue el primero en cerrarse —decisión
     * D.3, tres meses, F3-04— y enseña el camino: cada decisión que se cierra es
     * un renglón de un archivo de configuración y un renglón de aquí.
     *
     * @return array{filas: list<array{umbral: string, valor: string, donde: string, estado: string, tono: string}>, nota: string}
     */
    public static function umbrales(): array
    {
        return [
            'filas' => [
                [
                    'umbral' => 'Días sin ingreso para que una empresa siga contando como activa',
                    'valor' => Formato::dias((int) config('panel.salud_de_uso.dias_de_actividad')),
                    'donde' => 'config/panel.php',
                    'estado' => 'Propuesto, sin confirmar',
                    'tono' => 'advertencia',
                ],
                [
                    'umbral' => 'Días sin ingreso para considerar a una empresa sin uso',
                    'valor' => Formato::dias((int) config('panel.salud_de_uso.dias_sin_uso')),
                    'donde' => 'config/panel.php',
                    'estado' => 'Propuesto, sin confirmar',
                    'tono' => 'advertencia',
                ],
                [
                    'umbral' => 'Vigencia del enlace de invitación',
                    'valor' => Formato::dias(Invitaciones::DIAS_DE_VIGENCIA),
                    'donde' => 'App\Autenticacion\Invitaciones',
                    'estado' => 'En uso',
                    'tono' => 'exito',
                ],
                [
                    'umbral' => 'Prórroga que supera el tope de cuatro años',
                    'valor' => 'Impide guardar',
                    'donde' => 'config/validaciones.php',
                    'estado' => 'En uso',
                    'tono' => 'exito',
                ],
                [
                    'umbral' => 'Prórroga inferior a un año después del límite de prórrogas cortas',
                    'valor' => 'Advierte y deja guardar',
                    'donde' => 'config/validaciones.php',
                    'estado' => 'Hasta que el asesor laboral confirme la regla (documento 6, B.1)',
                    'tono' => 'advertencia',
                ],
                [
                    'umbral' => 'Anticipación de la alerta de cercanía al tope de cuatro años',
                    'valor' => Formato::tiempoTranscurrido((int) config('alertas.cercania_al_tope_meses')),
                    'donde' => 'config/alertas.php',
                    'estado' => 'En uso',
                    'tono' => 'exito',
                ],
                [
                    'umbral' => 'Tiempo para considerar prolongado un contrato de obra o labor',
                    'valor' => 'No hay umbral',
                    'donde' => 'En ningún archivo: la alerta lista todos los activos',
                    'estado' => 'Decisión D.2 resuelta: en su lugar, un listado cada dos meses',
                    'tono' => 'exito',
                ],
                [
                    'umbral' => 'Vigencia del usuario de demostración',
                    'valor' => 'La elige quien genera cada acceso',
                    'donde' => 'App\Proveedor\AccesosDePrueba',
                    'estado' => 'D.12 sigue sin un número por defecto: el documento 4, 12.1, dice que '
                        .'es David quien lo decide cada vez, así que el formulario no trae ninguno puesto',
                    'tono' => 'advertencia',
                ],
                [
                    'umbral' => 'Días de mora antes de suspender a un cliente',
                    'valor' => 'Sin decidir',
                    'donde' => 'Llega con las suscripciones (F5-04)',
                    'estado' => 'La escalera del capítulo 6 propone 5, 15 y 30 días',
                    'tono' => 'neutro',
                ],
            ],
            'nota' => 'Estos números no vienen de la ley: son decisiones del negocio. Los que ya '
                .'están en uso se cambian en el archivo que se nombra al lado, sin tocar la lógica '
                .'ni la pantalla. Los que dicen «sin decidir» no se inventan aquí: cuando se '
                .'decidan, entran por el mismo camino.',
        ];
    }

    /**
     * Lo que se le dice al owner después de cada escritura.
     *
     * Las frases viven donde vive el efecto, no en la pantalla: la misma prueba
     * que comprueba que la entidad quedó desactivada lee el texto que lo cuenta.
     */
    public static function confirmacionDeAlta(string $nombre): string
    {
        return 'Se agregó «'.$nombre.'». Ya se ofrece en los formularios de todas las empresas.';
    }

    public static function confirmacionDeEdicion(string $nombre): string
    {
        return 'Se guardó «'.$nombre.'». El cambio se ve en los formularios desde ahora.';
    }

    public static function confirmacionDeEstado(string $nombre, bool $activo): string
    {
        return $activo
            ? 'Se activó «'.$nombre.'». Vuelve a ofrecerse en los formularios.'
            : 'Se desactivó «'.$nombre.'». Deja de ofrecerse en los formularios y sigue leyéndose '
                .'en los empleados que ya la tenían.';
    }

    public static function confirmacionDeFestivo(Carbon $fecha, string $nombre): string
    {
        return 'Se agregó el festivo «'.$nombre.'» del '.Formato::fechaLarga($fecha)
            .'. Los días hábiles de vacaciones y el corrimiento de las alertas ya lo tienen en cuenta.';
    }

    public static function confirmacionDeFestivoQuitado(Carbon $fecha): string
    {
        return 'Se quitó el festivo del '.Formato::fechaLarga($fecha).'. Ese día vuelve a contar '
            .'como hábil. Queda registrado en la auditoría, no se borró de verdad.';
    }

    /**
     * Un valor de la dirección que tiene que estar en una lista cerrada.
     *
     * @param  list<string>  $permitidos
     */
    private static function opcion(?string $valor, array $permitidos, string $porDefecto): string
    {
        return in_array($valor, $permitidos, true) ? (string) $valor : $porDefecto;
    }

    /**
     * El departamento que se enseña cuando la dirección no dice cuál.
     *
     * El primero en orden alfabético y no uno escrito a mano: los identificadores
     * dependen del orden en que se cargó el catálogo y no son estables.
     */
    private static function primerDepartamento(): int
    {
        return (int) (Departamento::query()->orderBy('nombre')->value('id') ?? 0);
    }
}
