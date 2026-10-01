<?php

namespace App\Proveedor;

use App\Auditoria\AccionAuditada;
use App\Autenticacion\ResultadoDeAcceso;
use App\Autorizacion\Modulo;
use App\Autorizacion\SeccionDelPanel;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\AlertaEnviada;
use App\Models\Auditoria;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\User;
use App\Models\Vacacion;
use App\Multiempresa\ContextoTenant;
use App\Presentacion\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * La ficha de una empresa cliente en el panel del proveedor (documento 4, 4.3).
 *
 * Siete pestañas, **todas con contenido**:
 *
 * | Pestaña | Estado |
 * |---|---|
 * | Resumen | Datos, plan, fecha de alta y quién la creó. |
 * | Parámetros | **Editable por el owner**, con constancia. |
 * | Usuarios | Último acceso, reenviar invitación y restablecer contraseña. |
 * | Uso | Empleados, entradas, novedades registradas por mes y módulos que la empresa usa de verdad. |
 * | Suscripción | Construida (F5-04). Plan, cobro, historial de facturas y sus cuatro acciones. |
 * | Alertas | El registro real de `alertas_enviadas` de esta empresa, lo mismo que `ConsolaDeAlertas` acotado a un tenant (REV-12). |
 * | Bitácora | Construida (F5-07). Las notas internas y el historial de visitas al entorno. |
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
     * Cuántas alertas enviadas se traen a la pestaña. Las que no caben se
     * consultan en la consola, filtrada por esta empresa.
     */
    private const MAXIMO_DE_ALERTAS = 50;

    /**
     * Cuántos meses hacia atrás cuenta el «Uso» las novedades registradas.
     */
    private const MESES_DE_USO = 6;

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
            'alertas' => $this->alertas(),
            'suscripcion' => $this->suscripcion(),
            'bitacora' => $this->bitacora(),
            'acceso' => self::acceso(),
        ];
    }

    /**
     * La pestaña «Bitácora» (documento 4, 4.3), con sus dos mitades.
     *
     * Las **notas** son del equipo del proveedor y el cliente no las ve. Las
     * **visitas al entorno** son lo contrario: cada una está también en la
     * auditoría de esa empresa, que él sí puede leer. Van juntas porque son la
     * misma pregunta —qué ha pasado con este cliente— y separadas en la pantalla
     * porque una se le puede leer en voz alta y la otra no.
     *
     * @return array<string, mixed>
     */
    public function bitacora(): array
    {
        return [
            'titulo' => BitacoraDelCliente::TITULO,
            'ayuda' => BitacoraDelCliente::AYUDA,
            'ayudaDeLaNota' => BitacoraDelCliente::AYUDA_DE_LA_NOTA,
            'vacia' => [
                'titulo' => BitacoraDelCliente::VACIA_TITULO,
                'descripcion' => BitacoraDelCliente::VACIA_DESCRIPCION,
            ],
            'notas' => BitacoraDelCliente::de($this->empresa)->notas(),
            'visitas' => [
                'titulo' => 'Accesos del proveedor al entorno',
                'ayuda' => 'Cada uno está también en la auditoría de esta empresa: el cliente los '
                    .'ve, con el motivo escrito y con quién entró.',
                'vacio' => 'Nadie del equipo ha entrado al entorno de este cliente.',
                'filas' => AccesoAlEntorno::historial($this->empresa),
            ],
        ];
    }

    /**
     * Los textos del modal de «Entrar como el cliente» (documento 4, 4.4).
     *
     * Las tres condiciones se leen antes de entrar y por eso viajan con la
     * pantalla. Están escritas en `AccesoAlEntorno`, que es quien las hace
     * cumplir, y no aquí: leerlas en un sitio y cumplirlas en otro es como se
     * separan.
     *
     * @return array<string, mixed>
     */
    public static function acceso(): array
    {
        return [
            'titulo' => 'Entrar al entorno del cliente',
            'boton' => 'Entrar como el cliente',
            'tituloCondiciones' => AccesoAlEntorno::TITULO_DE_LAS_CONDICIONES,
            'condiciones' => AccesoAlEntorno::CONDICIONES,
            'ayudaDelMotivo' => AccesoAlEntorno::AYUDA_DEL_MOTIVO,
            'etiquetaDeEscritura' => AccesoAlEntorno::ETIQUETA_DE_ESCRITURA,
        ];
    }

    /**
     * La pestaña «Suscripción» (documento 4, 4.3): «plan, historial de pagos,
     * facturas y estado».
     *
     * Es donde se actúa sobre un cliente concreto: registrar un pago, cambiarle
     * el plan, suspenderlo y reactivarlo. La pantalla de Suscripciones del
     * capítulo 6 es la del negocio entero y su única acción por fila es venir
     * aquí, igual que en el prototipo.
     *
     * Una empresa sin suscripción no enseña un hueco: dice que no se le cobra
     * todavía y desde dónde se le abre. Es el caso de las que están en prueba y
     * de las que se crearon antes de que existiera el catálogo de planes.
     *
     * @return array<string, mixed>
     */
    public function suscripcion(): array
    {
        $suscripcion = $this->empresa->suscripcion()->first();

        if (! $suscripcion instanceof Suscripcion) {
            return [
                'existe' => false,
                'titulo' => 'A esta empresa no se le cobra todavía',
                'descripcion' => 'No tiene suscripción abierta. Se le abre desde Suscripciones, '
                    .'eligiendo uno de los planes del catálogo.',
                'url' => SeccionDelPanel::Suscripciones->url(),
            ];
        }

        $plan = $suscripcion->plan()->first();
        $estado = $suscripcion->estado;
        $cobro = $suscripcion->cobro();

        return [
            'existe' => true,
            'id' => $suscripcion->getKey(),
            'estado' => $estado->etiqueta(),
            'estadoTono' => $estado->tono(),
            'suspendida' => $suscripcion->estaSuspendida(),
            'cobro' => $suscripcion->textoDelCobro(),
            'cobroTono' => $cobro->tono(),
            'diasDeAtraso' => $suscripcion->diasDeAtraso(),
            'datos' => [
                ['etiqueta' => 'Plan', 'valor' => $plan instanceof Plan ? $plan->comoSeLee() : Formato::VACIO],
                ['etiqueta' => 'Valor mensual', 'valor' => Formato::dinero($suscripcion->valorMensual())],
                [
                    'etiqueta' => 'Próximo cobro',
                    'valor' => Formato::fechaLarga($suscripcion->proxima_fecha_cobro),
                ],
                ['etiqueta' => 'Medio de pago', 'valor' => $suscripcion->medio_de_pago->etiqueta()],
                [
                    'etiqueta' => 'Cliente de pago desde',
                    'valor' => Formato::fechaLarga($suscripcion->inicia_el),
                ],
                [
                    'etiqueta' => 'El plan actual aplica desde',
                    'valor' => Formato::fechaLarga($suscripcion->plan_aplica_desde),
                ],
            ],
            /*
             * La constancia de la suspensión, leída donde se toma la decisión de
             * reactivar. Es lo que el capítulo 6 pide con «motivo y constancia»:
             * la auditoría de la empresa guarda la línea, y esto es el mismo
             * hecho a la vista de quien mira la ficha.
             */
            'suspension' => $suscripcion->estaSuspendida() ? [
                'motivo' => (string) $suscripcion->getAttribute('motivo_de_suspension'),
                'cuando' => Formato::fechaYHora($suscripcion->suspendida_en),
            ] : null,
            'reactivacion' => $suscripcion->reactivada_en !== null && ! $suscripcion->estaSuspendida() ? [
                'motivo' => (string) $suscripcion->getAttribute('motivo_de_reactivacion'),
                'cuando' => Formato::fechaYHora($suscripcion->reactivada_en),
            ] : null,
            'historial' => $this->historialDePagos($suscripcion),
            'planes' => PanelDeSuscripciones::planesParaElegir(),
            'aviso' => Suscripciones::AVISO_DE_LA_SUSPENSION,
            /*
             * La consecuencia de anular, calculada de antemano: la
             * confirmación de la ficha la enseña antes de anular un pago, no
             * solo en el mensaje de después.
             */
            'consecuenciaDeAnular' => Suscripciones::consecuenciaDeAnular($suscripcion),
        ];
    }

    /**
     * El historial de facturas y pagos, de la más reciente a la más antigua.
     *
     * @return list<array<string, mixed>>
     */
    private function historialDePagos(Suscripcion $suscripcion): array
    {
        return $suscripcion->pagos()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Pago $pago): array => [
                'id' => $pago->getKey(),
                'fecha' => Formato::fecha($pago->fecha),
                'monto' => Formato::dinero($pago->monto),
                'numeroFactura' => $pago->numero_factura ?? Formato::VACIO,
                'estado' => $pago->estado->etiqueta(),
                'tono' => $pago->estado->tono(),
                'anulada' => $pago->estado->estaAnulada(),
            ])
            ->values()
            ->all();
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
            'exportacion' => $this->exportacion(),
        ];
    }

    /**
     * Entregarle a la empresa toda su información (documento 6, A.4, paso 1).
     *
     * La fecha de la última entrega se enseña porque de ella cuenta el periodo
     * de gracia antes de poder eliminar sus datos con `empresa:eliminar`. Si
     * ya se eliminaron, no hay nada que exportar y el botón no aparece.
     *
     * @return array{disponible: bool, url: string, ultima: string|null}
     */
    private function exportacion(): array
    {
        $exportada = $this->empresa->getAttribute('datos_exportados_en');

        return [
            'disponible' => $this->empresa->getAttribute('datos_eliminados_en') === null,
            'url' => '/proveedor/empresas/'.$this->empresa->getKey().'/exportacion',
            'ultima' => $exportada === null ? null : 'Información entregada el '.Formato::fechaLarga($exportada),
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
     * La pestaña «Uso» (documento 4, 4.3): «empleados cargados, novedades
     * registradas por mes, frecuencia de entrada por usuario y módulos que
     * efectivamente usa. Es la conversación de renovación en una pantalla».
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
            'novedadesPorMes' => $this->novedadesPorMes(),
            'modulos' => $this->modulosQueUsa(),
        ];
    }

    /**
     * Las cinco novedades que la empresa registra, cada una con su módulo y su
     * modelo. Es la lista sobre la que se mide el uso real: si una empresa no
     * registra incapacidades ni vacaciones, ese módulo no le está sirviendo.
     *
     * @return list<array{modulo: Modulo, modelo: class-string<Model>}>
     */
    private function modelosDeNovedad(): array
    {
        return [
            ['modulo' => Modulo::Incapacidades, 'modelo' => Incapacidad::class],
            ['modulo' => Modulo::Vacaciones, 'modelo' => Vacacion::class],
            ['modulo' => Modulo::Licencias, 'modelo' => Licencia::class],
            ['modulo' => Modulo::Movimientos, 'modelo' => Movimiento::class],
            ['modulo' => Modulo::Terminaciones, 'modelo' => Terminacion::class],
        ];
    }

    /**
     * Cuántas novedades se registraron cada mes, en los últimos seis, del mes
     * en curso hacia atrás.
     *
     * Cuenta por `created_at` —cuándo se cargó la novedad en el sistema— y no
     * por la fecha del hecho: la pregunta de la renovación es con qué
     * frecuencia la empresa entra a trabajar, no cuándo ocurrieron las cosas.
     * Se lee dentro del contexto de la empresa porque las cinco tablas llevan
     * el trait `PerteneceATenant` y su política de RLS (regla 1).
     *
     * @return list<array{mes: string, total: int}>
     */
    private function novedadesPorMes(): array
    {
        return app(ContextoTenant::class)->paraTenant($this->empresa, function (): array {
            $meses = [];

            for ($atras = 0; $atras < self::MESES_DE_USO; $atras++) {
                $inicio = Carbon::now()->startOfMonth()->subMonths($atras);
                $fin = (clone $inicio)->endOfMonth();

                $total = 0;

                foreach ($this->modelosDeNovedad() as $novedad) {
                    /** @var class-string<Model> $modelo */
                    $modelo = $novedad['modelo'];
                    $total += $modelo::query()
                        ->whereBetween('created_at', [$inicio, $fin])
                        ->count();
                }

                $meses[] = ['mes' => Formato::mesYAnio($inicio), 'total' => $total];
            }

            return $meses;
        });
    }

    /**
     * Qué módulos usa de verdad la empresa: cuántas novedades tiene registradas
     * en cada uno y cuándo se registró la última.
     *
     * @return list<array{modulo: string, registros: int, usa: bool, ultimo: string}>
     */
    private function modulosQueUsa(): array
    {
        return app(ContextoTenant::class)->paraTenant($this->empresa, function (): array {
            $filas = [];

            foreach ($this->modelosDeNovedad() as $novedad) {
                /** @var class-string<Model> $modelo */
                $modelo = $novedad['modelo'];
                $registros = $modelo::query()->count();
                $ultimo = $modelo::query()->max('created_at');

                $filas[] = [
                    'modulo' => $novedad['modulo']->etiqueta(),
                    'registros' => $registros,
                    'usa' => $registros > 0,
                    'ultimo' => $ultimo === null
                        ? 'Sin registros'
                        : Formato::fecha(Carbon::parse((string) $ultimo)),
                ];
            }

            return $filas;
        });
    }

    /**
     * La pestaña «Alertas» (documento 4, 4.3): «las enviadas a esa empresa, con
     * su resultado».
     *
     * Es el mismo dato que `ConsolaDeAlertas` enseña de todas las empresas a la
     * vez, acotado a esta —se reutiliza su `fila()` para que las dos pantallas
     * lean una alerta igual—. Se consulta dentro del contexto de la empresa
     * porque `alertas_enviadas` lleva el trait `PerteneceATenant` y su política
     * de RLS, que no tiene excepción para el `owner` (regla 1). Se traen las
     * últimas y, si hay más, se remite a la consola filtrada por esta empresa,
     * que es la que tiene los filtros y la paginación.
     *
     * @return array<string, mixed>
     */
    public function alertas(): array
    {
        return app(ContextoTenant::class)->paraTenant($this->empresa, function (): array {
            $total = AlertaEnviada::query()->count();

            $filas = AlertaEnviada::query()
                ->with('vinculacion.persona')
                ->orderByDesc('fecha_programada')
                ->orderByDesc('id')
                ->limit(self::MAXIMO_DE_ALERTAS)
                ->get()
                ->map(fn (AlertaEnviada $alerta): array => ConsolaDeAlertas::fila($alerta, $this->empresa))
                ->all();

            return [
                'filas' => $filas,
                'total' => $total,
                'maximo' => self::MAXIMO_DE_ALERTAS,
                'hayMas' => $total > self::MAXIMO_DE_ALERTAS,
                'consolaUrl' => SeccionDelPanel::Alertas->url().'?empresa='.$this->empresa->getKey(),
                'vacio' => [
                    'titulo' => 'Todavía no se le ha enviado ninguna alerta',
                    'descripcion' => 'Cuando el proceso diario tenga algo que avisarle a esta empresa, '
                        .'aparecerá aquí con su resultado.',
                ],
            ];
        });
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
