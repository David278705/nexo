<?php

namespace App\Configuracion;

use App\Autorizacion\Modulo;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;

/**
 * Los cuatro parámetros del capítulo 4 del documento 1, con su explicación en
 * lenguaje claro, en un solo sitio.
 *
 * Cuatro decisiones que son toda la tarea F1-12:
 *
 * 1. **Tres parámetros se guardan y el cuarto se deduce.** Sábado laboral,
 *    efecto de las suspensiones sobre las vacaciones y perfil sociodemográfico
 *    son columnas de `tenants`. «Correos que reciben las alertas» no es una
 *    columna ninguna: el capítulo 4 dice que sus opciones son «administradores
 *    con acceso a cada módulo», así que la lista se deduce de los usuarios y de
 *    sus permisos y se cambia en la pestaña de usuarios, no aquí. Guardar
 *    aparte una lista de correos sería una segunda fuente para la misma
 *    pregunta, y las dos se separarían a la primera baja de un usuario.
 * 2. **Las explicaciones se escriben en el servidor.** Cada frase afirma qué
 *    cambia al activar el parámetro, y son las del prototipo. Están aquí y no
 *    en el componente para que una prueba pueda leer la promesa y el efecto a
 *    la vez.
 * 3. **Nada se guarda en caché.** El valor se lee de la empresa en contexto en
 *    cada petición, que es lo que hace que cambiar un parámetro se refleje de
 *    inmediato en los cálculos: quien lo consulta —hoy
 *    `BloqueSociodemografico`, mañana el motor de la fase 2— pregunta por
 *    `tenant_actual()` y no por una copia.
 * 4. **Sí y No viajan como texto.** Un selector de HTML manda cadenas, y la
 *    cadena «false» es verdadera en PHP: es el error clásico de un booleano en
 *    un formulario. Aquí las opciones son `si` y `no`, la validación las exige
 *    y la conversión a booleano ocurre en un solo sitio.
 */
class ParametrosDeLaEmpresa
{
    public const SI = 'si';

    public const NO = 'no';

    /**
     * Los tres parámetros que sí son columnas de `tenants`, en el orden del
     * documento 1, capítulo 4.
     *
     * @var list<string>
     */
    public const CAMPOS = [
        'sabado_laboral',
        'suspensiones_afectan_vacaciones',
        'perfil_sociodemografico_habilitado',
    ];

    /**
     * Lo que el cliente no edita (documento 1, capítulo 3): «la información de
     * la empresa no la edita el cliente. Si necesita cambiarla, lo solicita al
     * proveedor».
     */
    public const NOTA_DE_DATOS_DE_LA_EMPRESA = 'Los datos de la empresa los cambia el proveedor. '
        .'Si algo de aquí está mal, solicítalo y se corrige.';

    private function __construct(private readonly Tenant $empresa) {}

    public static function de(Tenant $empresa): self
    {
        return new self($empresa);
    }

    /**
     * Todas las props de la pantalla.
     *
     * @return array<string, mixed>
     */
    public function paraLaPantalla(): array
    {
        return [
            'empresa' => [
                'nombre' => $this->empresa->nombre,
                'nit' => $this->empresa->nit,
                'nota' => self::NOTA_DE_DATOS_DE_LA_EMPRESA,
            ],
            'parametros' => $this->parametros(),
            'destinatarios' => [
                'explicacion' => self::EXPLICACION_DE_LOS_CORREOS,
                'titulo' => 'Correos que reciben las alertas',
                'filas' => $this->destinatarios(),
            ],
            'perfil' => [
                'habilitado' => $this->activo('perfil_sociodemografico_habilitado'),
                'aviso' => $this->avisoDeActivarElPerfil(),
            ],
        ];
    }

    /**
     * Los tres parámetros editables, cada uno con su explicación y sus dos
     * opciones escritas como el prototipo.
     *
     * @return list<array{clave: string, titulo: string, explicacion: string, valor: string, opciones: list<array{valor: string, texto: string}>}>
     */
    public function parametros(): array
    {
        return array_map(fn (string $campo) => [
            'clave' => $campo,
            'titulo' => self::TITULOS[$campo],
            'explicacion' => self::EXPLICACIONES[$campo],
            'valor' => $this->activo($campo) ? self::SI : self::NO,
            'opciones' => [
                ['valor' => self::SI, 'texto' => self::TEXTOS_DEL_SI[$campo]],
                ['valor' => self::NO, 'texto' => self::TEXTOS_DEL_NO[$campo]],
            ],
        ], self::CAMPOS);
    }

    /**
     * Guarda los tres parámetros.
     *
     * Llega el valor de los tres siempre, porque la pantalla es un formulario
     * de la empresa entera y no tres formularios de un campo: así el guardado
     * deja **una** línea de auditoría con lo que cambió, y no una por selector.
     *
     * @param  array<string, string>  $valores
     */
    public function guardar(array $valores): void
    {
        $this->empresa->update(array_combine(
            self::CAMPOS,
            array_map(
                fn (string $campo) => ($valores[$campo] ?? self::NO) === self::SI,
                self::CAMPOS,
            ),
        ));
    }

    /**
     * Las reglas de validación. Los tres son obligatorios y solo admiten `si` o
     * `no`: un parámetro a medio contestar no existe.
     *
     * @return array<string, list<string>>
     */
    public static function reglas(): array
    {
        $valores = 'in:'.self::SI.','.self::NO;

        return array_combine(
            self::CAMPOS,
            array_fill(0, count(self::CAMPOS), ['required', 'string', $valores]),
        );
    }

    /**
     * El aviso del capítulo 4 del documento 1: al habilitar el bloque con la
     * planta ya cargada, los empleados existentes quedan en blanco **y eso no
     * es un error**.
     *
     * Devuelve nulo cuando no hay nadie registrado todavía: sin empleados el
     * aviso no advierte de nada y solo estorbaría en la empresa recién creada,
     * que es justo cuando el parámetro se activa sin consecuencias.
     */
    public function avisoDeActivarElPerfil(): ?string
    {
        $registrados = Persona::count();

        if ($registrados === 0) {
            return null;
        }

        $cuantos = $registrados === 1
            ? 'El empleado ya registrado quedará'
            : "Los {$registrados} empleados ya registrados quedarán";

        return $cuantos.' con estos campos vacíos. Puedes completarlos cuando quieras; '
            .'si no lo haces, no pasa nada.';
    }

    /**
     * A quién le llegan las alertas hoy, según los permisos de cada usuario.
     *
     * Se lee por la relación de la empresa y no con un `where tenant_id`: el
     * usuario no lleva el trait a propósito —hay que poder encontrarlo por su
     * correo antes de saber de qué empresa es—, así que el filtro lo pone la
     * relación.
     *
     * Un administrador sin módulos también aparece, diciendo que no recibe
     * ninguna. Esconderlo dejaría al superadministrador preguntándose dónde
     * quedó el usuario que acaba de crear.
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public function destinatarios(): array
    {
        return $this->empresa->usuarios()
            ->with('modulos')
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => [
                'etiqueta' => $usuario->email,
                'valor' => $this->modulosDe($usuario),
            ])
            ->values()
            ->all();
    }

    private function modulosDe(User $usuario): string
    {
        if ($usuario->esSuperadministrador()) {
            return 'Todos los módulos';
        }

        $modulos = $usuario->modulosAsignados()
            ->map(fn (Modulo $modulo) => $modulo->etiqueta())
            ->all();

        return $modulos === [] ? 'Sin módulos asignados' : implode(' · ', $modulos);
    }

    private function activo(string $campo): bool
    {
        return $this->empresa->getAttribute($campo) === true;
    }

    /**
     * Los textos del prototipo, que son también los que la prueba lee.
     */
    private const TITULOS = [
        'sabado_laboral' => '¿El sábado es día laboral?',
        'suspensiones_afectan_vacaciones' => '¿Las suspensiones afectan la acumulación de vacaciones?',
        'perfil_sociodemografico_habilitado' => 'Perfil sociodemográfico',
    ];

    private const EXPLICACIONES = [
        'sabado_laboral' => 'Afecta el cálculo de los días de vacaciones y qué festivos aplican. '
            .'Si el sábado es laboral, un festivo que caiga en sábado sí se descuenta.',
        'suspensiones_afectan_vacaciones' => 'Si se activa, los días de licencia no remunerada, '
            .'ausencia y suspensión reducen proporcionalmente los 1,25 días que se acumulan cada mes.',
        'perfil_sociodemografico_habilitado' => 'Habilita un bloque opcional con estado civil, '
            .'nivel educativo, número de hijos y datos de vivienda y transporte. Es un insumo del '
            .'Sistema de Gestión de Seguridad y Salud en el Trabajo.',
    ];

    private const TEXTOS_DEL_SI = [
        'sabado_laboral' => 'Sí, el sábado es día laboral',
        'suspensiones_afectan_vacaciones' => 'Sí, reducen la acumulación',
        'perfil_sociodemografico_habilitado' => 'Sí, habilitado',
    ];

    private const TEXTOS_DEL_NO = [
        'sabado_laboral' => 'No, la empresa no trabaja los sábados',
        'suspensiones_afectan_vacaciones' => 'No, no la afectan',
        'perfil_sociodemografico_habilitado' => 'No',
    ];

    private const EXPLICACION_DE_LOS_CORREOS = 'Cada alerta llega a los administradores con acceso '
        .'al módulo correspondiente. Se cambia dando o quitando módulos en Usuarios y permisos.';
}
