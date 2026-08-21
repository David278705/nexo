<?php

namespace App\Proveedor;

use App\Auditoria\AccionAuditada;
use App\Autenticacion\ResultadoDeAcceso;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\Auditoria;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * La ficha de una empresa cliente en el panel del proveedor (documento 4, 4.3).
 *
 * Siete pestañas pide el documento. **Cuatro se construyen y tres se declaran**,
 * porque tres dependen de tablas que todavía no existen y una ficha que finge
 * tener información es peor que una que dice qué falta:
 *
 * | Pestaña | Estado |
 * |---|---|
 * | Resumen | Construida. Datos, plan, fecha de alta y quién la creó. |
 * | Parámetros | Construida y **editable por el owner**, con constancia. |
 * | Usuarios | Construida. Último acceso, reenviar invitación y restablecer contraseña. |
 * | Uso | Construida con lo que ya se puede medir; las novedades por mes y los módulos que usa son de la fase 2. |
 * | Suscripción | Declarada. Plan, pagos y facturas son F5-04. |
 * | Alertas | Declarada. La consola de envíos es F3-06 y F5-05. |
 * | Bitácora | Declarada. La tabla `notas_cliente` es F5-07. |
 *
 * **Aquí no hay `tenant_actual()`.** El `owner` no tiene empresa y el middleware
 * no le fija ninguna, así que todo lo que consulte tablas de negocio —hoy los
 * empleados y el aviso del perfil sociodemográfico— se pregunta dentro de
 * `ContextoTenant::paraTenant()`, que es lo mismo que hace el recorrido de
 * empresas del proceso diario.
 *
 * La promesa de la contraseña se cumple en la clase, no en la pantalla: **no
 * existe ningún método que devuelva una contraseña**, ni siquiera con hash. Lo
 * que se ofrece es mandarle al usuario el enlace para que la cambie él.
 */
class FichaDeEmpresa
{
    /**
     * Lo que dice cada pestaña que todavía no tiene contenido, y en qué tarea
     * lo va a tener. Está escrito aquí porque es información del proyecto, no
     * decoración de la pantalla.
     *
     * @var array<string, array{titulo: string, descripcion: string}>
     */
    public const PENDIENTES = [
        'suscripcion' => [
            'titulo' => 'La suscripción todavía no existe',
            'descripcion' => 'El plan pactado se ve en el resumen. El historial de pagos, las '
                .'facturas y el estado de cobro llegan con las tablas de suscripciones (F5-04).',
        ],
        'alertas' => [
            'titulo' => 'Todavía no se ha enviado ninguna alerta',
            'descripcion' => 'El motor de avisos por correo es la fase 3. Cuando exista, aquí se '
                .'verá todo lo enviado a esta empresa con su resultado.',
        ],
        'bitacora' => [
            'titulo' => 'La bitácora todavía no existe',
            'descripcion' => 'Las notas internas sobre el cliente —llamadas, quejas y promesas— '
                .'necesitan su propia tabla, que llega en F5-07.',
        ],
    ];

    /**
     * Lo que el «Uso» todavía no puede contar, dicho en la propia pestaña.
     */
    public const USO_INCOMPLETO = 'Las novedades registradas por mes y los módulos que la empresa '
        .'usa de verdad se podrán contar cuando existan las novedades, en la fase 2.';

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
            'empresa' => $this->encabezado(),
            'cifras' => $this->cifras(),
            'pestanas' => self::PESTANAS,
            'resumen' => $this->resumen(),
            'parametros' => $this->parametros(),
            'perfil' => $this->perfil(),
            'usuarios' => $this->usuarios(),
            'uso' => $this->uso(),
            'pendientes' => self::PENDIENTES,
        ];
    }

    /**
     * El nombre, el NIT, el estado y desde cuándo es cliente.
     *
     * @return array<string, mixed>
     */
    public function encabezado(): array
    {
        $estado = $this->empresa->estado;

        return [
            'id' => $this->empresa->getKey(),
            'nombre' => (string) $this->empresa->getAttribute('nombre'),
            'nit' => (string) $this->empresa->getAttribute('nit'),
            'estado' => $estado->etiqueta(),
            'estadoTono' => $estado->tono(),
            'clienteDesde' => 'Cliente desde el '.Formato::fechaLarga($this->empresa->getAttribute('created_at')),
        ];
    }

    /**
     * Las cifras del encabezado del prototipo.
     *
     * Las dos que el prototipo enseña y no están —valor mensual y próximo
     * cobro— son precio, que es la decisión A.1 y la tarea F5-04.
     *
     * @return list<array{etiqueta: string, valor: string}>
     */
    public function cifras(): array
    {
        $ultimoIngreso = $this->empresa->getAttribute('ultimo_acceso_en');

        return [
            ['etiqueta' => 'Plan', 'valor' => (string) $this->empresa->getAttribute('plan_nombre')],
            [
                'etiqueta' => 'Empleados',
                'valor' => $this->empleados().' de '.(int) $this->empresa->getAttribute('plan_empleados_incluidos'),
            ],
            [
                'etiqueta' => 'Usuarios',
                'valor' => $this->empresa->usuarios()->count()
                    .' de '.(int) $this->empresa->getAttribute('plan_usuarios_incluidos'),
            ],
            [
                'etiqueta' => 'Último ingreso',
                'valor' => SaludDeUso::textoDelUltimoIngreso($ultimoIngreso),
            ],
            [
                'etiqueta' => 'Uso',
                'valor' => SaludDeUso::segunElUltimoIngreso($ultimoIngreso)->etiqueta(),
            ],
        ];
    }

    /**
     * La pestaña «Resumen»: datos, plan y alta.
     *
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        return [
            'datos' => [
                ['etiqueta' => 'Razón social', 'valor' => (string) $this->empresa->getAttribute('nombre')],
                ['etiqueta' => 'NIT', 'valor' => (string) $this->empresa->getAttribute('nit')],
                ['etiqueta' => 'Teléfono', 'valor' => (string) $this->empresa->getAttribute('telefono')],
                ['etiqueta' => 'Ciudad', 'valor' => CreacionDeEmpresa::ciudadDe($this->empresa)],
            ],
            'plan' => [
                ['etiqueta' => 'Plan', 'valor' => (string) $this->empresa->getAttribute('plan_nombre')],
                [
                    'etiqueta' => 'Empleados incluidos',
                    'valor' => (string) $this->empresa->getAttribute('plan_empleados_incluidos'),
                ],
                [
                    'etiqueta' => 'Usuarios administradores incluidos',
                    'valor' => (string) $this->empresa->getAttribute('plan_usuarios_incluidos'),
                ],
                [
                    'etiqueta' => 'Fecha de inicio del plan',
                    'valor' => Formato::fechaLarga($this->empresa->getAttribute('plan_inicia_el')),
                ],
            ],
            'alta' => [
                [
                    'etiqueta' => 'Fecha de alta',
                    'valor' => Formato::fechaLarga($this->empresa->getAttribute('created_at')),
                ],
                ['etiqueta' => 'Creada por', 'valor' => $this->quienLaCreo()],
            ],
            /*
             * El certificado de Cámara de Comercio llega a su sitio: hasta hoy
             * solo se alcanzaba desde el resumen que se le lee al cliente en la
             * reunión, que no es donde se busca seis meses después.
             */
            'certificado' => $this->empresa->tieneCertificadoDeCamara()
                ? [
                    'nombre' => (string) $this->empresa->getAttribute('certificado_camara_nombre'),
                    'url' => '/proveedor/empresas/'.$this->empresa->getKey().'/certificado',
                ]
                : null,
            'sinCertificado' => CreacionDeEmpresa::SIN_CERTIFICADO,
        ];
    }

    /**
     * Los tres parámetros de cálculo, con las mismas palabras con las que se le
     * preguntaron al cliente en la reunión y con las que él los ve en su propia
     * pantalla de configuración.
     *
     * @return list<array<string, mixed>>
     */
    public function parametros(): array
    {
        return ParametrosDeLaEmpresa::de($this->empresa)->parametros();
    }

    /**
     * El aviso de activar el perfil sociodemográfico, que necesita saber cuántos
     * empleados hay ya cargados **en esa empresa** y por eso se pregunta dentro
     * de su contexto.
     *
     * @return array{habilitado: bool, aviso: string|null}
     */
    public function perfil(): array
    {
        return app(ContextoTenant::class)->paraTenant($this->empresa, fn (): array => [
            'habilitado' => $this->empresa->getAttribute('perfil_sociodemografico_habilitado') === true,
            'aviso' => ParametrosDeLaEmpresa::de($this->empresa)->avisoDeActivarElPerfil(),
        ]);
    }

    /**
     * La pestaña «Usuarios»: quiénes entran a esa empresa y cuándo entró cada
     * uno por última vez.
     *
     * De cada uno se dice si ya definió su contraseña o si sigue con la
     * invitación pendiente, porque de eso depende cuál de las dos acciones tiene
     * sentido ofrecerle. **Ninguna de las dos enseña una contraseña**: una
     * reenvía el enlace de la invitación y la otra manda el de restablecer.
     *
     * @return list<array<string, mixed>>
     */
    public function usuarios(): array
    {
        return $this->empresa->usuarios()
            ->orderBy('rol')
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->getKey(),
                'nombre' => (string) $usuario->getAttribute('name'),
                'correo' => (string) $usuario->getAttribute('email'),
                'rol' => $usuario->rol->etiqueta(),
                'ultimoAcceso' => SaludDeUso::textoDelUltimoIngreso($this->ultimoAccesoDe($usuario)),
                'invitacionPendiente' => $this->tieneInvitacionSinAceptar($usuario),
            ])
            ->values()
            ->all();
    }

    /**
     * La pestaña «Uso»: lo que ya se puede medir de verdad.
     *
     * @return array<string, mixed>
     */
    public function uso(): array
    {
        $desde = Carbon::now()->subDays(30);

        return [
            'cifras' => [
                ['etiqueta' => 'Empleados cargados', 'valor' => (string) $this->empleados()],
                [
                    'etiqueta' => 'Entradas en los últimos 30 días',
                    'valor' => (string) $this->empresa->accesos()
                        ->where('resultado', ResultadoDeAcceso::Exitoso->value)
                        ->where('created_at', '>=', $desde)
                        ->count(),
                ],
                [
                    'etiqueta' => 'Usuarios que han entrado alguna vez',
                    'valor' => (string) $this->empresa->accesos()
                        ->where('resultado', ResultadoDeAcceso::Exitoso->value)
                        ->distinct()
                        ->count('user_id'),
                ],
            ],
            'incompleto' => self::USO_INCOMPLETO,
        ];
    }

    /**
     * Cuántos empleados vigentes tiene, contados dentro de su contexto.
     */
    private function empleados(): int
    {
        return ListadoDeEmpresas::empleadosDe($this->empresa);
    }

    /**
     * Quién dio de alta a esta empresa.
     *
     * Sale de la auditoría, que es donde quedó constancia de la creación, y no
     * de una columna `creada_por`: la columna sería una segunda versión de un
     * hecho que ya está registrado, y esa segunda versión no la escribe nadie
     * cuando la empresa se crea por otro camino.
     */
    private function quienLaCreo(): string
    {
        $linea = Auditoria::query()
            ->with('usuario')
            ->where('modelo', Tenant::class)
            ->where('registro_id', (string) $this->empresa->getKey())
            ->where('accion', AccionAuditada::Creado->value)
            ->orderBy('id')
            ->first();

        $autor = $linea?->usuario()->first();

        return $autor instanceof User
            ? (string) $autor->getAttribute('name')
            : 'No quedó registrado';
    }

    private function ultimoAccesoDe(User $usuario): ?Carbon
    {
        $fecha = $this->empresa->accesos()
            ->where('user_id', $usuario->getKey())
            ->where('resultado', ResultadoDeAcceso::Exitoso->value)
            ->max('created_at');

        return $fecha === null ? null : Carbon::parse((string) $fecha);
    }

    /**
     * Si el usuario sigue sin estrenar su cuenta.
     *
     * Se pregunta por la invitación y no por la contraseña: la contraseña
     * siempre está —se genera al azar al crear la cuenta— y preguntarle a ella
     * diría que todo el mundo ya entró.
     */
    private function tieneInvitacionSinAceptar(User $usuario): bool
    {
        return $usuario->invitaciones()
            ->whereNull('aceptada_en')
            ->exists();
    }

    /**
     * Las siete pestañas del documento, en su orden.
     *
     * @var list<array{clave: string, titulo: string}>
     */
    public const PESTANAS = [
        ['clave' => 'resumen', 'titulo' => 'Resumen'],
        ['clave' => 'parametros', 'titulo' => 'Parámetros'],
        ['clave' => 'usuarios', 'titulo' => 'Usuarios'],
        ['clave' => 'uso', 'titulo' => 'Uso'],
        ['clave' => 'suscripcion', 'titulo' => 'Suscripción'],
        ['clave' => 'alertas', 'titulo' => 'Alertas'],
        ['clave' => 'bitacora', 'titulo' => 'Bitácora'],
    ];

    /**
     * Lo que se le dice al owner cuando acaba de cambiar un parámetro.
     *
     * Nombra la empresa a propósito: en el panel se miran varias en la misma
     * sesión y «se guardaron los parámetros» no dice de cuál.
     */
    public static function confirmacionDeParametros(Tenant $empresa): string
    {
        return 'Se guardaron los parámetros de '.$empresa->getAttribute('nombre')
            .'. Los cálculos y los correos usan los valores nuevos desde ahora.';
    }
}
