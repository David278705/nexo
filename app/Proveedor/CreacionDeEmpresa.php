<?php

namespace App\Proveedor;

use App\Autorizacion\Rol;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\Ciudad;
use App\Models\Tenant;
use App\Models\User;
use App\Presentacion\Formato;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Crear una empresa cliente desde el panel del proveedor (documento 4, 4.2).
 *
 * Es la pantalla sin la cual no hay cliente posible: hasta hoy dar de alta a
 * alguien era insertar filas a mano en la base de datos. Los tres pasos son los
 * del documento —datos, parámetros, plan y cuenta— y se diligencian de una sola
 * vez, delante del cliente, al final de la reunión de onboarding.
 *
 * Lo que aquí se decide y conviene no perder de vista:
 *
 * 1. **La empresa y su superadministrador nacen juntos o no nace ninguno.** Una
 *    empresa sin cuenta es una empresa a la que nadie puede entrar, y un usuario
 *    sin empresa no pasa del inicio de sesión. Por eso las dos escrituras van
 *    dentro de la misma transacción. La invitación no: se envía desde la ficha
 *    cuando la carga inicial esté lista (`PROD-22`).
 * 2. **Nadie escribe una contraseña aquí.** Se genera una al azar que no se
 *    enseña en ninguna parte y que nadie conoce, ni siquiera el proveedor; el
 *    superadministrador define la suya con el enlace de la invitación. Es la
 *    misma promesa de la ficha de la empresa: «nunca ver la contraseña, no existe
 *    forma de verla».
 * 3. **El plan se guarda como lo que hoy es.** Nombre y dos números pactados. La
 *    tabla `planes` con sus precios es F5-04 y depende de la decisión A.1, que
 *    sigue abierta.
 * 4. **El estado inicial es «activa».** El formulario del documento no pregunta
 *    el estado, y las otras dos posibilidades no se pueden fijar aquí sin
 *    inventar nada: la suspensión nace de la mora, que es del capítulo 6, y el
 *    periodo de prueba es del ambiente de demostración del capítulo 10.
 */
class CreacionDeEmpresa
{
    /**
     * Los tres pasos, con las palabras del documento. El asistente los pinta y
     * las pruebas los leen de aquí.
     *
     * @var list<string>
     */
    public const PASOS = [
        'Datos de la empresa',
        'Parámetros',
        'Plan y cuenta',
    ];

    /**
     * Lo que se le lee al cliente en voz alta antes de cerrar la reunión.
     *
     * El documento lo pide con nombre propio: «al terminar, el panel debe
     * mostrar un resumen de lo que quedó configurado y ofrecer copiarlo o
     * imprimirlo. Evita la discusión posterior de "yo nunca dije que el sábado
     * fuera laboral"».
     */
    public const TITULO_DEL_RESUMEN = 'Esto es lo que quedó configurado';

    /**
     * Dónde queda el certificado de Cámara de Comercio.
     *
     * En el disco privado, nunca en `public`: es un documento societario de un
     * cliente y una dirección adivinable lo dejaría al alcance de cualquiera.
     * Se recupera por la ruta del panel, que exige el rol `owner`.
     *
     * El disco sale de la configuración (`filesystems.certificados`) y no de
     * una constante: en producción puede ser un volumen o un disco tipo S3, y
     * cambiar de uno a otro no debe pedir tocar código.
     */
    public static function discoDeCertificados(): string
    {
        return (string) config('filesystems.certificados', 'local');
    }

    public const CARPETA_DE_CERTIFICADOS = 'certificados-camara';

    /**
     * Lo que se admite como certificado. No es una regla de negocio: es lo que
     * emite la Cámara de Comercio —un PDF— y lo que sale de la cámara del
     * teléfono cuando se fotografía en la reunión.
     *
     * @var list<string>
     */
    public const FORMATOS_DEL_CERTIFICADO = ['pdf', 'jpg', 'jpeg', 'png'];

    public const TAMANO_MAXIMO_EN_KB = 10240;

    /**
     * Escribe la empresa y su superadministrador, **sin invitación**.
     *
     * La invitación se envía desde la ficha de la empresa cuando su carga
     * inicial ya entró (decidido por David el 2026-11-08, `PROD-22`). Los
     * documentos 1 (2.2) y 4 (12.2) lo piden así: un cliente que entra a un
     * sistema vacío concluye que el producto no sirve.
     *
     * @param  array<string, mixed>  $datos  ya validados por `CrearEmpresa`
     */
    public function ejecutar(array $datos): Tenant
    {
        return DB::transaction(function () use ($datos): Tenant {
            /** @var array<string, string> $parametros */
            $parametros = $datos;

            $empresa = Tenant::query()->create([
                'nombre' => $datos['nombre'],
                'nit' => $datos['nit'],
                'telefono' => $datos['telefono'],
                'ciudad_id' => $datos['ciudad_id'],
                'estado' => Tenant::ESTADO_ACTIVA,
                ...ParametrosDeLaEmpresa::comoBooleanos($parametros),
                'plan_nombre' => $datos['plan_nombre'],
                'plan_empleados_incluidos' => $datos['plan_empleados_incluidos'],
                'plan_usuarios_incluidos' => $datos['plan_usuarios_incluidos'],
                'plan_inicia_el' => $datos['plan_inicia_el'],
                ...$this->certificado($datos['certificado'] ?? null),
            ]);

            /** @var array{nombre: string, correo: string} $cuenta */
            $cuenta = $datos['superadministrador'];

            $this->superadministrador($empresa, $cuenta);

            return $empresa;
        });
    }

    /**
     * Guarda el certificado adjunto y devuelve las dos columnas que lo apuntan.
     *
     * El archivo queda con un nombre al azar en el disco privado: el nombre con
     * el que llegó es dato del cliente y no puede decidir dónde se escribe. Se
     * conserva aparte, que es el que se le enseña a quien lo descargue.
     *
     * @return array<string, string|null>
     */
    private function certificado(mixed $archivo): array
    {
        if (! $archivo instanceof UploadedFile) {
            return [];
        }

        $ruta = $archivo->store(self::CARPETA_DE_CERTIFICADOS, self::discoDeCertificados());

        return [
            'certificado_camara_ruta' => is_string($ruta) ? $ruta : null,
            'certificado_camara_nombre' => $archivo->getClientOriginalName(),
        ];
    }

    /**
     * La cuenta del cliente. Es superadministrador por su rol y por eso no
     * recibe módulos: los tiene todos dentro de su empresa y ninguno fuera.
     *
     * @param  array{nombre: string, correo: string}  $cuenta
     */
    private function superadministrador(Tenant $empresa, array $cuenta): User
    {
        return User::query()->create([
            'tenant_id' => $empresa->getKey(),
            'rol' => Rol::Superadministrador,
            'name' => $cuenta['nombre'],
            'email' => $cuenta['correo'],

            // Una contraseña que nadie ha visto nunca y que nadie va a usar: el
            // acceso se abre con el enlace de la invitación. La columna no
            // admite nulo, y dejarla vacía sería peor que llenarla con azar.
            'password' => Str::password(64),
        ]);
    }

    /**
     * Las reglas de los tres pasos.
     *
     * Los tres parámetros llegan como `si` y `no` y los valida
     * `ParametrosDeLaEmpresa`, que es donde vive esa conversión.
     *
     * @return array<string, mixed>
     */
    public static function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'nit' => ['required', 'string', 'max:20', Rule::unique('tenants', 'nit')],
            'telefono' => ['required', 'string', 'max:30'],
            'ciudad_id' => ['required', 'integer', Rule::exists('ciudades', 'id')->where('activo', true)],

            /*
             * El certificado es opcional: el documento pide adjuntar el que se
             * recibió, no impedir que se cree la empresa cuando la reunión
             * termina sin él a mano. Que falte se ve en el resumen, y es ahí
             * donde tiene que verse.
             */
            'certificado' => [
                'nullable',
                'file',
                'mimes:'.implode(',', self::FORMATOS_DEL_CERTIFICADO),
                'max:'.self::TAMANO_MAXIMO_EN_KB,
            ],

            ...ParametrosDeLaEmpresa::reglas(),

            'plan_nombre' => ['required', 'string', 'max:80'],
            'plan_empleados_incluidos' => ['required', 'integer', 'min:1'],
            'plan_usuarios_incluidos' => ['required', 'integer', 'min:1'],
            'plan_inicia_el' => ['required', 'date'],

            'superadministrador.nombre' => ['required', 'string', 'max:150'],
            // El correo es único en todo el sistema, no por empresa: es con lo
            // que se inicia sesión, y el inicio de sesión ocurre antes de saber
            // de qué empresa es quien escribe.
            'superadministrador.correo' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * Los nombres con los que se reconoce cada campo en los mensajes de error.
     *
     * @return array<string, string>
     */
    public static function atributos(): array
    {
        return [
            'nombre' => 'razón social',
            'nit' => 'NIT',
            'telefono' => 'teléfono',
            'ciudad_id' => 'ciudad',
            'certificado' => 'certificado de Cámara de Comercio',
            'plan_nombre' => 'plan',
            'plan_empleados_incluidos' => 'número de empleados incluidos',
            'plan_usuarios_incluidos' => 'número de usuarios incluidos',
            'plan_inicia_el' => 'fecha de inicio',
            'superadministrador.nombre' => 'nombre del superadministrador',
            'superadministrador.correo' => 'correo del superadministrador',
        ];
    }

    /**
     * El resumen de la configuración, renglón por renglón.
     *
     * Se arma leyendo la empresa ya guardada y no lo que se digitó: lo que se le
     * lee al cliente tiene que ser lo que quedó en la base de datos, que es de
     * lo que se va a discutir dentro de seis meses.
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public static function resumen(Tenant $empresa): array
    {
        $renglones = [
            ['etiqueta' => 'Razón social', 'valor' => (string) $empresa->getAttribute('nombre')],
            ['etiqueta' => 'NIT', 'valor' => (string) $empresa->getAttribute('nit')],
            ['etiqueta' => 'Teléfono', 'valor' => (string) $empresa->getAttribute('telefono')],
            ['etiqueta' => 'Ciudad', 'valor' => self::ciudadDe($empresa)],
            [
                'etiqueta' => 'Certificado de Cámara de Comercio',
                'valor' => $empresa->tieneCertificadoDeCamara()
                    ? (string) $empresa->getAttribute('certificado_camara_nombre')
                    : self::SIN_CERTIFICADO,
            ],
        ];

        foreach (ParametrosDeLaEmpresa::CAMPOS as $campo) {
            $renglones[] = [
                'etiqueta' => self::ETIQUETAS_DE_LOS_PARAMETROS[$campo],
                'valor' => ParametrosDeLaEmpresa::texto($campo, $empresa->getAttribute($campo) === true),
            ];
        }

        $renglones[] = ['etiqueta' => 'Plan', 'valor' => (string) $empresa->getAttribute('plan_nombre')];
        $renglones[] = [
            'etiqueta' => 'Empleados incluidos',
            'valor' => (string) $empresa->getAttribute('plan_empleados_incluidos'),
        ];
        $renglones[] = [
            'etiqueta' => 'Usuarios administradores incluidos',
            'valor' => (string) $empresa->getAttribute('plan_usuarios_incluidos'),
        ];
        $renglones[] = [
            'etiqueta' => 'Fecha de inicio',
            'valor' => Formato::fechaLarga($empresa->getAttribute('plan_inicia_el')),
        ];

        $cuenta = self::superadministradorDe($empresa);

        if ($cuenta instanceof User) {
            $renglones[] = ['etiqueta' => 'Superadministrador', 'valor' => (string) $cuenta->getAttribute('name')];
            $renglones[] = ['etiqueta' => 'Correo', 'valor' => (string) $cuenta->getAttribute('email')];
        }

        return $renglones;
    }

    /**
     * El mismo resumen como texto plano, que es lo que se copia o se imprime.
     *
     * Se arma en el servidor y no en el navegador a propósito: es el mismo
     * documento que el resumen de la pantalla, y dos versiones del acta de una
     * reunión se separan solas.
     */
    public static function textoParaCopiar(Tenant $empresa): string
    {
        $lineas = array_map(
            fn (array $renglon) => $renglon['etiqueta'].': '.$renglon['valor'],
            self::resumen($empresa),
        );

        return implode("\n", [
            self::TITULO_DEL_RESUMEN,
            '',
            ...$lineas,
        ]);
    }

    /**
     * La frase que confirma lo que el servidor acaba de hacer. Está aquí y no en
     * la pantalla porque afirma dos cosas —la empresa quedó creada y el correo
     * todavía no salió— y una prueba tiene que poder leer la promesa y comprobarla.
     */
    public static function confirmacion(Tenant $empresa): string
    {
        return $empresa->getAttribute('nombre').' quedó creada. Todavía no se le envió la invitación al '
            .'superadministrador: envíala desde la pestaña Usuarios de la ficha cuando su carga inicial esté lista.';
    }

    /**
     * El superadministrador de la empresa, que es el que se acaba de crear.
     */
    public static function superadministradorDe(Tenant $empresa): ?User
    {
        return $empresa->usuarios()
            ->where('rol', Rol::Superadministrador->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * La ciudad de la empresa como se lee: «Medellín, Antioquia».
     *
     * Es pública porque la ficha de la empresa (F1B-03) enseña el mismo dato en
     * su pestaña de resumen, y dos formas de escribir la misma ciudad en dos
     * pantallas del mismo panel se separan solas.
     */
    public static function ciudadDe(Tenant $empresa): string
    {
        $ciudad = $empresa->ciudad()->first();

        if (! $ciudad instanceof Ciudad) {
            return '';
        }

        $departamento = $ciudad->departamento()->first();

        return $departamento === null
            ? (string) $ciudad->getAttribute('nombre')
            : $ciudad->getAttribute('nombre').', '.$departamento->getAttribute('nombre');
    }

    /**
     * Lo que dice el resumen cuando no se adjuntó el certificado. Un renglón en
     * blanco parecería un olvido de la pantalla; esto es un pendiente declarado
     * delante del cliente, que es exactamente para lo que sirve el resumen.
     */
    public const SIN_CERTIFICADO = 'Pendiente de recibir';

    /**
     * En el resumen los parámetros no van como pregunta sino como acuerdo: lo
     * que se lee en voz alta es «Sábado laboral: No», no «¿El sábado es día
     * laboral?: No».
     */
    private const ETIQUETAS_DE_LOS_PARAMETROS = [
        'sabado_laboral' => 'Sábado laboral',
        'suspensiones_afectan_vacaciones' => 'Las suspensiones afectan las vacaciones',
        'perfil_sociodemografico_habilitado' => 'Perfil sociodemográfico',
    ];
}
