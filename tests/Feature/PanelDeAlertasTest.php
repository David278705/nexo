<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Dominio\Alertas\CatalogoDeReglas;
use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\AlertaEnviada;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\CorreoDeAlerta;
use App\Notificaciones\ProveedorDeCorreo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * La entregabilidad y el registro de envíos (F3-06, documento 2, sección 5.5).
 *
 * Se prueban las tres cosas que pide la sección y que no estaban:
 *
 * - **El proveedor transaccional**, en lo único que se puede comprobar desde
 *   dentro: que el sistema sepa decir cuándo lo configurado no sirve para
 *   producción, y que el correo de prueba salga por el mismo camino que las
 *   alertas de verdad y quede registrado.
 * - **El registro de envíos consultable**: destinatarios, estado e intentos.
 * - **El panel como canal alterno**: el aviso entero se lee en pantalla, y solo
 *   lo lee quien lo habría recibido por correo.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; Pest carga las funciones de
 * todos los archivos de prueba.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso S.A.S.',
        'nit' => '900123456-7',
    ]);
});

/**
 * Deja una alerta ya registrada en la empresa, como la habría dejado el proceso
 * diario.
 *
 * @param  array<string, mixed>  $atributos
 */
function alertaDeLaEmpresa(Tenant $empresa, array $atributos = [], ?Vinculacion $vinculacion = null): AlertaEnviada
{
    return app(ContextoTenant::class)->paraTenant($empresa, function () use ($atributos, $vinculacion): AlertaEnviada {
        $factory = AlertaEnviada::factory();

        if ($vinculacion instanceof Vinculacion) {
            $factory = $factory->de($vinculacion);
        }

        return $factory->create($atributos);
    });
}

/**
 * Una empleada cuyo nombre completo son exactamente estas dos palabras.
 *
 * `sinSegundosNombres()` no es un adorno: sin él la fábrica rellena el segundo
 * nombre y el segundo apellido, `Persona::nombreCompleto()` los concatena, y la
 * prueba compararía «Ana Torres» contra «Ana Lucía Torres Bernal». La columna
 * «Sobre» del panel enseña el nombre completo, así que la prueba tiene que
 * conocer el nombre completo entero.
 */
function empleadaConNombre(Tenant $empresa, string $nombres, string $apellidos): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant($empresa, fn (): Vinculacion => Vinculacion::factory()
        ->de(Persona::factory()->sinSegundosNombres()->create([
            'primer_nombre' => $nombres,
            'primer_apellido' => $apellidos,
        ]))
        ->create());
}

/**
 * Las filas que el panel le enseña a este usuario.
 *
 * @return array<int, array<string, mixed>>
 */
function filasDelPanel(User $usuario, string $consulta = ''): array
{
    $respuesta = test()->actingAs($usuario)->get('/alertas'.($consulta === '' ? '' : '?'.$consulta));

    $respuesta->assertOk();

    /** @var array<string, mixed> $props */
    $props = $respuesta->viewData('page')['props'];

    return $props['alertas']['filas'];
}

describe('el «hecho cuando» de la tarea', function () {
    it('un correo de prueba se envía por el proveedor y queda registrado', function () {
        Mail::fake();

        $this->artisan('alertas:prueba', [
            'empresa' => (string) $this->empresa->getKey(),
            'correo' => 'angela@elprogreso.co',
        ])->assertExitCode(0);

        Mail::assertSent(CorreoDeAlerta::class, 1);

        $registrada = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn (): ?AlertaEnviada => AlertaEnviada::query()->latest('id')->first()
        );

        expect($registrada)->not->toBeNull()
            ->and($registrada->tipo)->toBe(TipoDeAlerta::PruebaDeEntregabilidad)
            ->and($registrada->destinatarios)->toBe(['angela@elprogreso.co'])
            ->and($registrada->estado)->toBe(EstadoDeAlerta::Enviada)
            ->and($registrada->intentos)->toBe(1)
            ->and($registrada->enviada_en)->not->toBeNull();
    });

    it('el correo de prueba se puede consultar en el panel, con su destinatario y su estado', function () {
        Mail::fake();

        $this->artisan('alertas:prueba', [
            'empresa' => (string) $this->empresa->getKey(),
            'correo' => 'angela@elprogreso.co',
        ]);

        // El correo de prueba es del módulo Configuración: lo ve quien opera la
        // empresa, no cualquiera con acceso a contratos.
        $filas = filasDelPanel(usuarioDe($this->empresa, Rol::Superadministrador));

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['tipo'])->toBe('Correo de prueba de entregabilidad')
            ->and($filas[0]['destinatarios'])->toBe(['angela@elprogreso.co'])
            ->and($filas[0]['estado'])->toBe('Enviada')
            ->and($filas[0]['intentos'])->toBe(1);
    });
});

describe('el comando de prueba', function () {
    it('encuentra la empresa por su NIT y no solo por su identificador', function () {
        Mail::fake();

        $this->artisan('alertas:prueba', [
            'empresa' => '900123456-7',
            'correo' => 'angela@elprogreso.co',
        ])->assertExitCode(0);

        Mail::assertSent(CorreoDeAlerta::class, 1);
    });

    it('no manda nada si la empresa no existe', function () {
        Mail::fake();

        $this->artisan('alertas:prueba', ['empresa' => '99999', 'correo' => 'angela@elprogreso.co'])
            ->assertExitCode(1);

        Mail::assertNothingSent();
    });

    it('no manda nada si la dirección no es un correo', function () {
        Mail::fake();

        $this->artisan('alertas:prueba', [
            'empresa' => (string) $this->empresa->getKey(),
            'correo' => 'angela arroba el progreso',
        ])->assertExitCode(1);

        Mail::assertNothingSent();
    });

    it('avisa de que el transporte configurado no es un proveedor transaccional', function () {
        Mail::fake();

        // Es lo que hay en local y en las pruebas, y es justo el caso que la
        // sección 5.5 prohíbe en producción.
        config()->set('mail.default', 'log');

        $this->artisan('alertas:prueba', [
            'empresa' => (string) $this->empresa->getKey(),
            'correo' => 'angela@elprogreso.co',
        ])->expectsOutputToContain('no es un proveedor transaccional');
    });

    it('se puede repetir el mismo día, al contrario que las alertas', function () {
        Mail::fake();
        $argumentos = [
            'empresa' => (string) $this->empresa->getKey(),
            'correo' => 'angela@elprogreso.co',
        ];

        // Quien está corrigiendo el SPF de su dominio manda, mira la bandeja,
        // corrige y vuelve a mandar, todo en la misma mañana.
        $this->artisan('alertas:prueba', $argumentos)->assertExitCode(0);
        Carbon::setTestNow(Carbon::now()->addMinutes(10));
        $this->artisan('alertas:prueba', $argumentos)->assertExitCode(0);

        Mail::assertSent(CorreoDeAlerta::class, 2);

        expect(app(ContextoTenant::class)->paraTenant($this->empresa, fn (): int => AlertaEnviada::count()))
            ->toBe(2);

        Carbon::setTestNow();
    });
});

describe('el registro de envíos', function () {
    it('enseña a quién, cuándo, con qué resultado y con cuántos intentos', function () {
        $alerta = alertaDeLaEmpresa($this->empresa, [
            'destinatarios' => ['gestion@elprogreso.co', 'gerencia@elprogreso.co'],
            'estado' => EstadoDeAlerta::Enviada,
            'intentos' => 2,
            'enviada_en' => Carbon::parse('2026-08-21 06:04:00'),
        ]);

        $filas = filasDelPanel(usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos));

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['id'])->toBe((int) $alerta->getKey())
            ->and($filas[0]['destinatarios'])->toBe(['gestion@elprogreso.co', 'gerencia@elprogreso.co'])
            ->and($filas[0]['estado'])->toBe('Enviada')
            ->and($filas[0]['intentos'])->toBe(2)
            ->and($filas[0]['enviada'])->toContain('21/08/2026');
    });

    it('guarda el motivo del fallo de la que no se pudo entregar', function () {
        alertaDeLaEmpresa($this->empresa, [
            'estado' => EstadoDeAlerta::Fallida,
            'intentos' => 3,
            'motivo_del_fallo' => 'El proveedor rechazó la dirección',
        ]);

        $filas = filasDelPanel(usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos));

        expect($filas[0]['estado'])->toBe('Fallida')
            ->and($filas[0]['tono'])->toBe('error')
            ->and($filas[0]['motivo'])->toBe('El proveedor rechazó la dirección');
    });

    it('dice de quién habla la alerta, y que un resumen mensual no es de nadie', function () {
        $empleada = empleadaConNombre($this->empresa, 'Ana', 'Torres');

        alertaDeLaEmpresa($this->empresa, [], $empleada);
        alertaDeLaEmpresa($this->empresa, [
            'tipo' => TipoDeAlerta::ContratosVencidosSinGestionar,
            'clave' => 'resumen:2026-08-31',
        ]);

        $filas = filasDelPanel(usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos));
        $sobre = array_column($filas, 'empleado');

        expect($sobre)->toContain('Ana Torres')
            ->and($sobre)->toContain('Toda la empresa');
    });
});

describe('el canal alterno', function () {
    it('enseña el aviso entero, con el mismo texto que salió por correo', function () {
        alertaDeLaEmpresa($this->empresa, [
            'asunto' => 'Mañana vence el periodo de prueba de Ana',
            'cuerpo' => "Mañana (14 de junio) es el último día del periodo de prueba.\nAna Torres, Analista.",
        ]);

        $filas = filasDelPanel(usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos));

        expect($filas[0]['asunto'])->toBe('Mañana vence el periodo de prueba de Ana')
            ->and($filas[0]['parrafos'])->toBe([
                'Mañana (14 de junio) es el último día del periodo de prueba.',
                'Ana Torres, Analista.',
            ]);
    });

    it('solo enseña las alertas de los módulos que el usuario ya tenía', function () {
        alertaDeLaEmpresa($this->empresa, [
            'tipo' => TipoDeAlerta::PlazoDeRenovacion,
            'clave' => 'contratos:2026-08-21',
        ]);
        alertaDeLaEmpresa($this->empresa, [
            'tipo' => TipoDeAlerta::AcumulacionDeQuinceDias,
            'clave' => 'vacaciones:2026-08-31',
        ]);

        $deVacaciones = filasDelPanel(usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Vacaciones));

        expect(array_column($deVacaciones, 'tipo'))
            ->toBe(['Vacaciones: acumulación de 15 días']);
    });

    it('tampoco las deja pedir por la dirección, aunque se nombre el tipo', function () {
        alertaDeLaEmpresa($this->empresa, [
            'tipo' => TipoDeAlerta::PlazoDeRenovacion,
            'clave' => 'contratos:2026-08-21',
        ]);

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Vacaciones);

        expect(filasDelPanel($usuario, 'tipo='.TipoDeAlerta::PlazoDeRenovacion->value))->toBe([]);
    });

    it('ofrece en el filtro solo los tipos que ese usuario puede ver', function () {
        $respuesta = $this->actingAs(usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Vacaciones))
            ->get('/alertas');

        /** @var array<string, mixed> $props */
        $props = $respuesta->viewData('page')['props'];
        $tipos = array_column($props['opciones']['tipos'], 'valor');

        expect($tipos)->toBe([
            TipoDeAlerta::AcumulacionDeQuinceDias->value,
            TipoDeAlerta::AnioSinDisfruteMinimo->value,
        ]);
    });

    it('devuelve 403 a quien no tiene el módulo, aunque escriba la dirección', function () {
        $this->actingAs(usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos))
            ->get('/alertas')
            ->assertForbidden();
    });

    it('no enseña las alertas de otra empresa', function () {
        alertaDeLaEmpresa($this->empresa, ['asunto' => 'Confidencial de la Empresa A']);

        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);
        $usuarioB = usuarioDe($otra, Rol::Superadministrador);

        $respuesta = $this->actingAs($usuarioB)->get('/alertas');

        $respuesta->assertOk();
        expect($respuesta->getContent())->not->toContain('Confidencial de la Empresa A');

        /** @var array<string, mixed> $props */
        $props = $respuesta->viewData('page')['props'];
        expect($props['alertas']['filas'])->toBe([]);
    });
});

describe('los filtros del registro', function () {
    it('filtra por rango de fechas programadas', function () {
        alertaDeLaEmpresa($this->empresa, [
            'fecha_programada' => Carbon::parse('2026-07-15'),
            'clave' => 'julio',
        ]);
        alertaDeLaEmpresa($this->empresa, [
            'fecha_programada' => Carbon::parse('2026-08-21'),
            'clave' => 'agosto',
        ]);

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos);
        $filas = filasDelPanel($usuario, 'desde=2026-08-01');

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['programada'])->toBe('21/08/2026');
    });

    it('filtra por estado', function () {
        alertaDeLaEmpresa($this->empresa, ['estado' => EstadoDeAlerta::Enviada, 'clave' => 'una']);
        alertaDeLaEmpresa($this->empresa, ['estado' => EstadoDeAlerta::Fallida, 'clave' => 'otra']);

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos);
        $filas = filasDelPanel($usuario, 'estado='.EstadoDeAlerta::Fallida->value);

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['estado'])->toBe('Fallida');
    });

    it('abre por lo más reciente, que es lo que se consulta', function () {
        alertaDeLaEmpresa($this->empresa, [
            'fecha_programada' => Carbon::parse('2026-07-15'),
            'clave' => 'vieja',
        ]);
        alertaDeLaEmpresa($this->empresa, [
            'fecha_programada' => Carbon::parse('2026-08-21'),
            'clave' => 'nueva',
        ]);

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Alertas, Modulo::Contratos);

        expect(array_column(filasDelPanel($usuario), 'programada'))
            ->toBe(['21/08/2026', '15/07/2026']);
    });
});

describe('el correo de prueba no es una alerta', function () {
    it('ninguna regla del catálogo lo dispara', function () {
        // Es lo que `esDelCalendario()` separa, y lo que se pone rojo si alguien
        // registra una regla con este tipo: el proceso diario mandaría correos de
        // prueba a los clientes todas las mañanas.
        $delCalendario = array_map(
            fn (ReglaDeAlerta $regla): bool => $regla->tipo()->esDelCalendario(),
            app(CatalogoDeReglas::class)->reglas(),
        );

        expect($delCalendario)->not->toBeEmpty()
            ->and($delCalendario)->not->toContain(false);
    });

    it('es el único tipo que no es del calendario del capítulo 9', function () {
        $fuera = array_values(array_filter(
            TipoDeAlerta::cases(),
            fn (TipoDeAlerta $tipo): bool => ! $tipo->esDelCalendario(),
        ));

        expect($fuera)->toBe([TipoDeAlerta::PruebaDeEntregabilidad]);
    });
});

describe('el proveedor de correo', function () {
    it('no da por transaccional el transporte que envía desde la propia máquina', function () {
        foreach (['log', 'array', 'sendmail'] as $transporte) {
            config()->set('mail.default', $transporte);

            expect(app(ProveedorDeCorreo::class)->esTransaccional())->toBeFalse();
        }
    });

    it('da por bueno el que sale por un proveedor externo', function () {
        foreach (['smtp', 'ses', 'postmark', 'resend'] as $transporte) {
            config()->set('mail.default', $transporte);

            expect(app(ProveedorDeCorreo::class)->esTransaccional())->toBeTrue();
        }
    });

    it('avisa mientras el remitente siga siendo el de ejemplo', function () {
        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', 'hello@example.com');

        expect(app(ProveedorDeCorreo::class)->advertencia())->toContain('dominio propio');
    });

    it('no tiene nada que advertir con el proveedor y el dominio puestos', function () {
        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', 'alertas@elprogreso.co');

        expect(app(ProveedorDeCorreo::class)->advertencia())->toBeNull();
    });
});
