<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Dominio\Alertas\CatalogoDeReglas;
use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Alertas\ResumenDelProceso;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Jobs\EnviarAlerta;
use App\Models\AlertaEnviada;
use App\Models\Festivo;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\CorreoDeAlerta;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\ReglaDePrueba;

/**
 * La infraestructura del motor de alertas (F3-01).
 *
 * Aquí no se prueba ninguna alerta concreta: las once llegan en F3-03, F3-04 y
 * F3-05 y cada una traerá sus propios casos. Lo que se prueba es el armazón que
 * todas comparten, y que es donde están los errores que arruinarían el
 * producto:
 *
 * - que el proceso recorra las empresas **una por una**, con su contexto;
 * - que correrlo dos veces el mismo día **no duplique ningún correo**;
 * - que cada día evalúe además las fechas de los días no hábiles que vienen;
 * - que todo envío quede registrado con sus destinatarios, estado e intentos.
 *
 * La regla de disparo es `Tests\Fixtures\ReglaDePrueba`, de mentira, igual que
 * `RegistroDePrueba` en el aislamiento: lo que se mide es el armazón.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; Pest carga las funciones de
 * todos los archivos.
 */

/**
 * El viernes de referencia de casi todas las pruebas. El sábado y el domingo
 * que le siguen son el 22 y el 23.
 */
const VIERNES = '2026-08-21';

beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
});

function empleadoDeLaEmpresa(Tenant $empresa, array $vinculacion = []): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant($empresa, fn (): Vinculacion => Vinculacion::factory()
        ->de(Persona::factory()->create())
        ->create($vinculacion));
}

function conLaRegla(ReglaDePrueba $regla): ReglaDePrueba
{
    app(CatalogoDeReglas::class)->registrar($regla);

    return $regla;
}

function correrElProceso(string $dia): ResumenDelProceso
{
    return app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse($dia));
}

describe('el «hecho cuando» de la tarea', function () {
    it('ejecutar el proceso dos veces el mismo día no duplica ningún correo', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        $primera = correrElProceso(VIERNES);
        $segunda = correrElProceso(VIERNES);

        expect($primera->registradas())->toBe(1)
            ->and($segunda->registradas())->toBe(0)
            ->and($segunda->repetidas())->toBe(1);

        Mail::assertSent(CorreoDeAlerta::class, 1);

        expect(app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::count()))->toBe(1);
    });
});

describe('el recorrido de empresas', function () {
    it('evalúa cada empresa dentro de su propio contexto y ninguna alerta cae en la empresa equivocada', function () {
        Mail::fake();
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);

        empleadoDeLaEmpresa($this->empresa);
        empleadoDeLaEmpresa($otra);
        conLaRegla(new ReglaDePrueba(VIERNES));

        $resumen = correrElProceso(VIERNES);

        expect($resumen->empresas())->toBe(2)
            ->and($resumen->registradas())->toBe(2);

        $contexto = app(ContextoTenant::class);

        $deLaPrimera = $contexto->paraTenant($this->empresa, fn () => AlertaEnviada::pluck('asunto')->all());
        $deLaOtra = $contexto->paraTenant($otra, fn () => AlertaEnviada::pluck('asunto')->all());

        expect($deLaPrimera)->toBe(['Aviso de prueba de Comercializadora del Valle'])
            ->and($deLaOtra)->toBe(['Aviso de prueba de Textiles del Norte']);
    });

    it('deja el contexto como estaba al terminar', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        correrElProceso(VIERNES);

        expect(app(ContextoTenant::class)->hay())->toBeFalse();
    });

    it('no le manda alertas a una empresa suspendida', function () {
        Mail::fake();
        $suspendida = Tenant::factory()->suspendida()->create(['nombre' => 'Mora y Compañía']);
        usuarioDe($suspendida, Rol::Administrador, Modulo::Contratos);
        empleadoDeLaEmpresa($suspendida);
        conLaRegla(new ReglaDePrueba(VIERNES));

        $resumen = correrElProceso(VIERNES);

        expect($resumen->empresas())->toBe(1)
            ->and($resumen->registradas())->toBe(0);
    });

    it('una empresa que falla no impide las alertas de las siguientes', function () {
        Mail::fake();
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);
        empleadoDeLaEmpresa($otra);

        // La regla revienta con la primera empresa, la del `beforeEach`, que es
        // la que no tiene empleados. La segunda tiene que salir igual.
        conLaRegla(new class(VIERNES) extends ReglaDePrueba
        {
            public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
            {
                if (app(ContextoTenant::class)->tenant()?->getAttribute('nombre') === 'Comercializadora del Valle') {
                    throw new RuntimeException('un dato roto de esta empresa');
                }

                return parent::evaluar($fechaDelDisparo, $fechaDeEnvio);
            }
        });

        $resumen = correrElProceso(VIERNES);

        expect($resumen->registradas())->toBe(1)
            ->and($resumen->fallidas())->toHaveCount(1)
            ->and($resumen->fallidas()[0]['empresa'])->toBe('Comercializadora del Valle');
    });
});

describe('las fechas que le tocan a cada día', function () {
    it('un viernes evalúa viernes, sábado y domingo', function () {
        $fechas = ProcesoDiarioDeAlertas::fechasQueLeTocanA(Carbon::parse(VIERNES));

        expect(array_map(fn (Carbon $dia) => $dia->toDateString(), $fechas))
            ->toBe(['2026-08-21', '2026-08-22', '2026-08-23']);
    });

    it('si el lunes es festivo, el viernes lo evalúa también', function () {
        Festivo::registrar(Carbon::parse('2026-08-24'), 'Festivo de prueba');

        $fechas = ProcesoDiarioDeAlertas::fechasQueLeTocanA(Carbon::parse(VIERNES));

        expect(array_map(fn (Carbon $dia) => $dia->toDateString(), $fechas))
            ->toBe(['2026-08-21', '2026-08-22', '2026-08-23', '2026-08-24']);
    });

    it('un martes corriente evalúa solo el martes', function () {
        $fechas = ProcesoDiarioDeAlertas::fechasQueLeTocanA(Carbon::parse('2026-08-18'));

        expect(array_map(fn (Carbon $dia) => $dia->toDateString(), $fechas))->toBe(['2026-08-18']);
    });

    it('un domingo no evalúa ninguna fecha, porque ninguna alerta se corre hacia adelante', function () {
        $fechas = ProcesoDiarioDeAlertas::fechasQueLeTocanA(Carbon::parse('2026-08-23'));

        expect($fechas)->toBe([]);
    });

    it('el proceso le pasa a la regla las tres fechas del fin de semana', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        $regla = conLaRegla(new ReglaDePrueba('2026-08-23'));

        correrElProceso(VIERNES);

        expect($regla->fechasEvaluadas)->toBe(['2026-08-21', '2026-08-22', '2026-08-23']);
    });

    it('adelanta al viernes la alerta que tocaba el domingo', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba('2026-08-23'));

        correrElProceso(VIERNES);

        $alerta = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::first());

        expect($alerta->fecha_programada->toDateString())->toBe('2026-08-21')
            ->and($alerta->fecha_del_hecho->toDateString())->toBe('2026-08-23');
    });
});

describe('la idempotencia', function () {
    it('la clave se arma con el tipo, la vinculación y la fecha programada', function () {
        Mail::fake();
        $empleado = empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        correrElProceso(VIERNES);

        $alerta = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::first());

        expect($alerta->clave)->toBe('prueba_indefinido_vispera:'.$empleado->getKey().':2026-08-21');
    });

    it('dos empresas distintas sí pueden tener la misma clave', function () {
        Mail::fake();
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);

        // El mismo tipo y el mismo día en las dos: lo único que las distingue
        // es la empresa, y el índice único la lleva delante justamente para eso.
        empleadoDeLaEmpresa($this->empresa);
        empleadoDeLaEmpresa($otra);
        conLaRegla(new ReglaDePrueba(VIERNES));

        $resumen = correrElProceso(VIERNES);

        expect($resumen->registradas())->toBe(2)
            ->and($resumen->repetidas())->toBe(0);
    });

    it('otro día sí vuelve a mandar la misma alerta, porque la clave lleva la fecha', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba('*'));

        correrElProceso(VIERNES);
        $delLunes = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-08-24'));

        expect($delLunes->registradas())->toBe(1)
            ->and($delLunes->repetidas())->toBe(0)
            ->and(app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::count()))->toBe(2);
    });

    it('el índice único la impide aunque nadie compruebe nada antes', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));
        correrElProceso(VIERNES);

        // La misma alerta, escrita a mano por fuera del proceso: la base de
        // datos la rechaza igual, que es lo que dice la sección 5.2.
        $repetir = fn () => app(ContextoTenant::class)->paraTenant($this->empresa, fn () => DB::transaction(function () {
            $original = AlertaEnviada::firstOrFail();

            return AlertaEnviada::create([
                'vinculacion_id' => $original->vinculacion_id,
                'tipo' => $original->tipo,
                'clave' => $original->clave,
                'fecha_programada' => $original->fecha_programada,
                'fecha_del_hecho' => $original->fecha_del_hecho,
                'asunto' => $original->asunto,
                'cuerpo' => $original->cuerpo,
                'destinatarios' => $original->destinatarios,
            ]);
        }));

        expect($repetir)->toThrow(UniqueConstraintViolationException::class);

        expect(app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::count()))->toBe(1);
    });
});

describe('el registro de lo enviado', function () {
    it('queda registrada con sus destinatarios, su asunto y su cuerpo', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        correrElProceso(VIERNES);

        $alerta = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::first());

        expect($alerta->tipo)->toBe(TipoDeAlerta::PruebaIndefinidoVispera)
            ->and($alerta->asunto)->toBe('Aviso de prueba de Comercializadora del Valle')
            ->and($alerta->cuerpo)->toContain('(2026-08-21)')
            ->and($alerta->destinatarios)->toBe([$this->usuario->email])
            ->and($alerta->estado)->toBe(EstadoDeAlerta::Enviada)
            ->and($alerta->intentos)->toBe(1);
    });

    it('los destinatarios son los usuarios con acceso al módulo de la alerta', function () {
        Mail::fake();
        $deVacaciones = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);
        $superadministrador = usuarioDe($this->empresa, Rol::Superadministrador);
        empleadoDeLaEmpresa($this->empresa);

        // La alerta es de Contratos: el administrador de vacaciones no la
        // recibe y el superadministrador sí, porque tiene todos los módulos.
        conLaRegla(new ReglaDePrueba(VIERNES));
        correrElProceso(VIERNES);

        $alerta = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::first());

        expect($alerta->destinatarios)->toContain($this->usuario->email)
            ->and($alerta->destinatarios)->toContain($superadministrador->email)
            ->and($alerta->destinatarios)->not->toContain($deVacaciones->email);
    });

    it('sin nadie a quien mandársela no registra ni encola', function () {
        Mail::fake();
        $sinNadie = Tenant::factory()->create(['nombre' => 'Empresa sin usuarios']);
        empleadoDeLaEmpresa($sinNadie);
        conLaRegla(new ReglaDePrueba(VIERNES));

        $resumen = correrElProceso(VIERNES);

        expect($resumen->sinDestinatarios())->toBe(1)
            ->and(app(ContextoTenant::class)->paraTenant($sinNadie, fn () => AlertaEnviada::count()))->toBe(0);

        Mail::assertNothingSent();
    });
});

describe('la cola', function () {
    it('encola un envío por alerta registrada, con su empresa', function () {
        Queue::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        correrElProceso(VIERNES);

        Queue::assertPushed(
            EnviarAlerta::class,
            fn (EnviarAlerta $job) => $job->empresaId === $this->empresa->getKey()
        );
    });

    it('reintenta tres veces con espera creciente', function () {
        $job = new EnviarAlerta(1, 1);

        expect($job->tries)->toBe(3)
            ->and($job->backoff())->toBe([60, 300, 900]);
    });

    it('el trabajador envía el correo, cuenta el intento y marca la alerta como enviada', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        correrElProceso(VIERNES);

        $alerta = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => AlertaEnviada::first());

        expect($alerta->estado)->toBe(EstadoDeAlerta::Enviada)
            ->and($alerta->enviada_en)->not->toBeNull();

        Mail::assertSent(
            CorreoDeAlerta::class,
            fn (CorreoDeAlerta $correo) => $correo->hasTo($this->usuario->email)
        );
    });

    it('el trabajador restablece el contexto de la empresa y lo limpia al terminar', function () {
        Mail::fake();
        $empleado = empleadoDeLaEmpresa($this->empresa);
        $alerta = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AlertaEnviada::factory()->de($empleado)->create()
        );

        // Se ejecuta sin ninguna empresa en contexto, que es como arranca el
        // trabajador de la cola de verdad.
        (new EnviarAlerta($alerta->getKey(), $this->empresa->getKey()))->handle(app(ContextoTenant::class));

        $recargada = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AlertaEnviada::findOrFail($alerta->getKey())
        );

        expect(app(ContextoTenant::class)->hay())->toBeFalse()
            ->and($recargada->estado)->toBe(EstadoDeAlerta::Enviada);
    });

    it('no vuelve a mandar una alerta que ya está enviada', function () {
        Mail::fake();
        $empleado = empleadoDeLaEmpresa($this->empresa);
        $alerta = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AlertaEnviada::factory()->de($empleado)->enviada()->create()
        );

        (new EnviarAlerta($alerta->getKey(), $this->empresa->getKey()))->handle(app(ContextoTenant::class));

        Mail::assertNothingSent();
    });

    it('marca la alerta como fallida cuando la cola agota los reintentos', function () {
        $empleado = empleadoDeLaEmpresa($this->empresa);
        $alerta = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AlertaEnviada::factory()->de($empleado)->create()
        );

        (new EnviarAlerta($alerta->getKey(), $this->empresa->getKey()))
            ->failed(new RuntimeException('el proveedor de correo respondió 500'));

        $recargada = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AlertaEnviada::findOrFail($alerta->getKey())
        );

        expect($recargada->estado)->toBe(EstadoDeAlerta::Fallida)
            ->and($recargada->motivo_del_fallo)->toBe('el proveedor de correo respondió 500');
    });
});

describe('la plantilla base del correo', function () {
    it('lleva el nombre del sistema, el cuerpo, el nombre de la empresa y el enlace para entrar', function () {
        $empleado = empleadoDeLaEmpresa($this->empresa);
        $alerta = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AlertaEnviada::factory()->de($empleado)->create([
                'cuerpo' => 'Mañana (14 de junio) es el último día del periodo de prueba.',
            ])
        );

        $html = (new CorreoDeAlerta($alerta, $this->empresa))->render();

        expect($html)->toContain('Mañana (14 de junio) es el último día del periodo de prueba.')
            ->and($html)->toContain('Comercializadora del Valle')
            ->and($html)->toContain(route('inicio'))
            ->and($html)->toContain(config('app.name'));
    });

    it('parte el cuerpo en párrafos para que el listado de un resumen no salga en un bloque', function () {
        $parrafos = CorreoDeAlerta::parrafosDe("Estos contratos vencieron:\n\nAna Gómez\nLuis Ríos\n");

        expect($parrafos)->toBe(['Estos contratos vencieron:', 'Ana Gómez', 'Luis Ríos']);
    });
});

describe('el comando programado', function () {
    it('corre el proceso y escribe el resumen del día', function () {
        Mail::fake();
        empleadoDeLaEmpresa($this->empresa);
        conLaRegla(new ReglaDePrueba(VIERNES));

        $this->artisan('alertas:diarias', ['--fecha' => VIERNES])
            ->expectsOutputToContain('1 alertas nuevas')
            ->assertSuccessful();
    });

    it('termina en error si alguna empresa falló, para que el programador de tareas lo note', function () {
        Mail::fake();
        conLaRegla(new class(VIERNES) extends ReglaDePrueba
        {
            public function evaluar(Carbon $fechaDelDisparo, Carbon $fechaDeEnvio): iterable
            {
                throw new RuntimeException('un dato roto');
            }
        });

        $this->artisan('alertas:diarias', ['--fecha' => VIERNES])->assertFailed();
    });
});
