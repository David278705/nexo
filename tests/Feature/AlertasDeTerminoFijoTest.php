<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Alertas\Reglas\CercaniaAlTope;
use App\Dominio\Alertas\Reglas\EfectoDelSilencio;
use App\Dominio\Alertas\Reglas\PlazoDeRenovacion;
use App\Dominio\Alertas\Reglas\UltimaRenovacionCorta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\AlertaEnviada;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Las alertas del término fijo (F3-04, documento 1, 9.4).
 *
 * Las cuatro filas de la tabla: el plazo para definir la renovación, 32 días
 * antes del vencimiento; el efecto del silencio, 30 días antes, con las tres
 * variantes de texto que le puso la decisión D.3; la última renovación corta
 * posible, en el mismo día que la primera pero solo en los contratos que
 * agotaron el cupo de prórrogas cortas; y la cercanía al tope de cuatro años,
 * tres meses antes.
 *
 * ## El contrato de la batería
 *
 * Ana Gómez, a término fijo del 1 de enero al 31 de diciembre de 2026. La fecha
 * límite del preaviso es el 1 de diciembre —treinta días antes— y el plazo para
 * definir la renovación cae el 29 de noviembre, que en 2026 es domingo: el
 * ejemplo trae de regalo su propio caso de corrimiento al día hábil anterior.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; `empleadaEnPrueba()`,
 * `alertasDe()` y `alertaRegistrada()`, en `AlertasDePeriodoDePruebaTest.php`.
 */
const INICIO_DEL_FIJO = '2026-01-01';

const FIN_DEL_FIJO = '2026-12-31';

/**
 * La fecha límite del preaviso: treinta días antes del vencimiento. Es el día
 * del que hablan las dos alertas.
 */
const LIMITE_DEL_PREAVISO = '2026-12-01';

/**
 * Treinta y dos días antes del vencimiento. Domingo, en 2026.
 */
const PLAZO_PARA_DEFINIR = '2026-11-29';

/**
 * El día en que el vínculo cumpliría los cuatro años a término fijo: cuatro
 * años después del 1 de enero de 2026. Es la fecha de la que habla la alerta de
 * cercanía al tope, y no depende de las prórrogas que se registren en medio.
 */
const FECHA_DEL_TOPE = '2030-01-01';

/**
 * Tres meses antes del tope, que es la anticipación decidida en D.3.
 */
const DISPARO_DEL_TOPE = '2029-10-01';

/**
 * Encadena prórrogas sobre un contrato, con la empresa en contexto.
 *
 * Cada tramo es el tipo de prórroga y hasta cuándo llega; el inicio lo pone
 * `Vinculacion::renovar()`, que es el día siguiente al vencimiento vigente.
 *
 * @param  list<array{0: EventoContractual, 1: string}>  $tramos
 */
function conCadenaDeProrrogas(Vinculacion $contrato, array $tramos): void
{
    app(ContextoTenant::class)->paraTenant($contrato->tenant, function () use ($contrato, $tramos): void {
        foreach ($tramos as [$prorroga, $hasta]) {
            $contrato->renovar($prorroga, Carbon::parse($hasta));
        }
    });
}

function alertaDelTipo(Tenant $empresa, TipoDeAlerta $tipo): ?AlertaEnviada
{
    return app(ContextoTenant::class)->paraTenant(
        $empresa,
        fn () => AlertaEnviada::query()->where('tipo', $tipo->value)->first(),
    );
}

beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
    $this->empleada = empleadaEnPrueba(
        $this->empresa,
        Vinculacion::factory()->aTerminoFijo(INICIO_DEL_FIJO, FIN_DEL_FIJO)
    );
});

describe('el día de disparo de cada una', function () {
    it('el plazo para definir la renovación sale 32 días antes del vencimiento', function () {
        expect(alertasDe(new PlazoDeRenovacion, $this->empresa, PLAZO_PARA_DEFINIR))->toHaveCount(1)
            ->and(alertasDe(new PlazoDeRenovacion, $this->empresa, '2026-11-28'))->toBe([])
            ->and(alertasDe(new PlazoDeRenovacion, $this->empresa, '2026-11-30'))->toBe([]);
    });

    it('el efecto del silencio sale 30 días antes, el día del plazo legal', function () {
        expect(alertasDe(new EfectoDelSilencio, $this->empresa, LIMITE_DEL_PREAVISO))->toHaveCount(1)
            ->and(alertasDe(new EfectoDelSilencio, $this->empresa, '2026-11-30'))->toBe([])
            ->and(alertasDe(new EfectoDelSilencio, $this->empresa, '2026-12-02'))->toBe([]);
    });

    it('las dos hablan de la fecha límite del preaviso, no del vencimiento', function () {
        $plazo = alertasDe(new PlazoDeRenovacion, $this->empresa, PLAZO_PARA_DEFINIR)[0];
        $silencio = alertasDe(new EfectoDelSilencio, $this->empresa, LIMITE_DEL_PREAVISO)[0];

        expect($plazo->fechaDelHecho->toDateString())->toBe(LIMITE_DEL_PREAVISO)
            ->and($silencio->fechaDelHecho->toDateString())->toBe(LIMITE_DEL_PREAVISO);
    });
});

describe('los textos de la sección 5.4', function () {
    it('la de los 32 días escribe el vencimiento entero y el plazo en cuenta atrás', function () {
        $alerta = alertasDe(new PlazoDeRenovacion, $this->empresa, PLAZO_PARA_DEFINIR)[0];

        expect($alerta->asunto)->toBe('Definir renovación del contrato de Ana Gómez')
            ->and($alerta->cuerpo)->toBe(
                'El contrato de Ana Gómez, Auxiliar contable, termina el 31 de diciembre de 2026. '
                .'En dos días (1 de diciembre) vence el plazo legal de 30 días para avisarle si el '
                .'contrato no se va a renovar.'
            );
    });

    it('la de los 30 días dice «Hoy (1 de diciembre) es el último día»', function () {
        $alerta = alertasDe(new EfectoDelSilencio, $this->empresa, LIMITE_DEL_PREAVISO)[0];

        expect($alerta->asunto)->toBe('Último día para avisar la no renovación de Ana Gómez')
            ->and($alerta->cuerpo)->toBe(
                'Hoy (1 de diciembre) es el último día para notificar a Ana Gómez que su contrato '
                .'no se renovará. Si no se avisa, el contrato queda prorrogado automáticamente por '
                .'un término igual al último pactado.'
            );
    });
});

describe('a qué contratos les toca', function () {
    it('el indefinido no recibe ninguna de las dos, porque no vence', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);
        empleadaEnPrueba($otra, Vinculacion::factory()->conIngreso(INICIO_DEL_FIJO));

        expect(alertasDe(new PlazoDeRenovacion, $otra, PLAZO_PARA_DEFINIR))->toBe([])
            ->and(alertasDe(new EfectoDelSilencio, $otra, LIMITE_DEL_PREAVISO))->toBe([]);
    });

    it('la obra o labor tampoco, como dice la sección 9.6', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);
        empleadaEnPrueba($otra, Vinculacion::factory()->deObraOLabor()->conIngreso(INICIO_DEL_FIJO));

        expect(alertasDe(new PlazoDeRenovacion, $otra, PLAZO_PARA_DEFINIR))->toBe([])
            ->and(alertasDe(new EfectoDelSilencio, $otra, LIMITE_DEL_PREAVISO))->toBe([]);
    });

    it('un contrato de veinte días no avisa, porque el plazo caería antes del ingreso', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);

        // Del 1 al 20 de enero: la fecha límite del preaviso sería el 21 de
        // diciembre del año anterior, cuando no había a quién avisar ni de qué.
        empleadaEnPrueba($otra, Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-01-20'));

        expect(alertasDe(new PlazoDeRenovacion, $otra, '2025-12-19'))->toBe([])
            ->and(alertasDe(new EfectoDelSilencio, $otra, '2025-12-21'))->toBe([]);
    });
});

describe('«si no se ha registrado prórroga ni terminación»', function () {
    it('registrar la prórroga mueve el aviso al plazo del tramo nuevo', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
            $this->empleada->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2027-12-31'));
        });

        // El plazo viejo dejó de existir y el nuevo es el del tramo vigente.
        expect(alertasDe(new EfectoDelSilencio, $this->empresa, LIMITE_DEL_PREAVISO))->toBe([])
            ->and(alertasDe(new EfectoDelSilencio, $this->empresa, '2027-12-01'))->toHaveCount(1);
    });

    it('registrada la terminación, el empleado inactivo no recibe ninguna', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);
        empleadaEnPrueba(
            $otra,
            Vinculacion::factory()->aTerminoFijo(INICIO_DEL_FIJO, FIN_DEL_FIJO)->terminada()
        );

        expect(alertasDe(new PlazoDeRenovacion, $otra, PLAZO_PARA_DEFINIR))->toBe([])
            ->and(alertasDe(new EfectoDelSilencio, $otra, LIMITE_DEL_PREAVISO))->toBe([]);
    });
});

describe('la cercanía al tope de cuatro años', function () {
    it('sale tres meses antes del día en que el vínculo cumple los cuatro años', function () {
        expect(alertasDe(new CercaniaAlTope, $this->empresa, DISPARO_DEL_TOPE))->toHaveCount(1)
            ->and(alertasDe(new CercaniaAlTope, $this->empresa, '2029-09-30'))->toBe([])
            ->and(alertasDe(new CercaniaAlTope, $this->empresa, '2029-10-02'))->toBe([])
            // Y no vuelve a salir el día del tope: es un solo aviso.
            ->and(alertasDe(new CercaniaAlTope, $this->empresa, FECHA_DEL_TOPE))->toBe([]);
    });

    it('habla del día del tope y escribe la antigüedad que lleva el vínculo', function () {
        $alerta = alertasDe(new CercaniaAlTope, $this->empresa, DISPARO_DEL_TOPE)[0];

        expect($alerta->fechaDelHecho->toDateString())->toBe(FECHA_DEL_TOPE)
            ->and($alerta->asunto)->toBe('El contrato de Ana Gómez se acerca al tope de cuatro años')
            ->and($alerta->cuerpo)->toBe(
                'El vínculo a término fijo de Ana Gómez lleva 3 años y 9 meses y se acerca al '
                .'límite legal de cuatro años, que se cumple el 1 de enero de 2030. Al superarlo, '
                .'el contrato se convierte en indefinido por ley, sin necesidad de liquidar '
                .'prestaciones en ese momento.'
            );
    });

    it('en un contrato anterior a la reforma cuenta desde el 25 de junio de 2025', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);
        empleadaEnPrueba($otra, Vinculacion::factory()->aTerminoFijo('2020-01-01', '2029-12-31'));

        // Los años anteriores a la Ley 2466 de 2025 no cuentan para el tope
        // (documento 6, C.2), así que el tope es el 25 de junio de 2029 y el
        // aviso sale el 25 de marzo, no cuatro años después de la firma.
        expect(alertasDe(new CercaniaAlTope, $otra, '2024-10-01'))->toBe([]);

        $alerta = alertasDe(new CercaniaAlTope, $otra, '2029-03-25')[0];

        expect($alerta->fechaDelHecho->toDateString())->toBe('2029-06-25')
            ->and($alerta->cuerpo)->toContain('lleva 3 años y 9 meses')
            ->and($alerta->cuerpo)->toContain('que se cumple el 25 de junio de 2029');
    });

    it('no le toca al indefinido, ni a la obra o labor, ni a quien ya salió', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);
        empleadaEnPrueba($otra, Vinculacion::factory()->conIngreso(INICIO_DEL_FIJO));
        empleadaEnPrueba($otra, Vinculacion::factory()->deObraOLabor()->conIngreso(INICIO_DEL_FIJO));
        empleadaEnPrueba(
            $otra,
            Vinculacion::factory()->aTerminoFijo(INICIO_DEL_FIJO, FIN_DEL_FIJO)->terminada()
        );

        expect(alertasDe(new CercaniaAlTope, $otra, DISPARO_DEL_TOPE))->toBe([]);
    });

    it('el proceso diario la registra y no la repite al correr dos veces', function () {
        Mail::fake();

        $primera = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(DISPARO_DEL_TOPE));
        $segunda = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(DISPARO_DEL_TOPE));

        $alerta = alertaRegistrada($this->empresa);

        expect($alerta->tipo)->toBe(TipoDeAlerta::CercaniaAlTope)
            ->and($alerta->fecha_del_hecho->toDateString())->toBe(FECHA_DEL_TOPE)
            ->and($alerta->destinatarios)->toBe([$this->usuario->email])
            ->and($primera->registradas())->toBe(1)
            ->and($segunda->registradas())->toBe(0);
    });
});

describe('el efecto del silencio cuando el tope está cerca (documento 6, D.3)', function () {
    it('avisa que la prórroga queda truncada si el término completo se pasaría del tope', function () {
        // El tope cae el 1 de enero de 2030. El contrato se renueva hasta el 1
        // de diciembre de 2029 por seis meses, así que otro término igual se
        // pasaría cinco meses del límite y la ley lo trunca solo.
        app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
            $this->empleada->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2029-06-01'));
            $this->empleada->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2029-12-01'));
        });

        $alerta = alertasDe(new EfectoDelSilencio, $this->empresa, '2029-11-01')[0];

        expect($alerta->cuerpo)->toBe(
            'Hoy (1 de noviembre) es el último día para notificar a Ana Gómez que su contrato no '
            .'se renovará. Si no se avisa, el contrato queda prorrogado automáticamente solo hasta '
            .'el 1 de enero de 2030, y al llegar a esa fecha se convierte en indefinido por ley.'
        );
    });

    it('avisa que ya no cabe ninguna prórroga si el contrato vence en el tope', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
            $this->empleada->renovar(EventoContractual::ProrrogaPactada, Carbon::parse(FECHA_DEL_TOPE));
        });

        $alerta = alertasDe(new EfectoDelSilencio, $this->empresa, '2029-12-02')[0];

        expect($alerta->cuerpo)->toBe(
            'Hoy (2 de diciembre) es el último día para notificar a Ana Gómez que su contrato no '
            .'se renovará. Si no se avisa, el contrato ya no puede prorrogarse: el vínculo cumple '
            .'el tope legal de cuatro años el 1 de enero de 2030 y a partir de esa fecha se '
            .'convierte en indefinido por ley.'
        );
    });

    it('mantiene el texto de la sección 5.4 mientras la prórroga entera quepa', function () {
        // El contrato de la batería vence el 31 de diciembre de 2026 y otro año
        // igual lo deja en 2027, tres años antes del tope.
        $alerta = alertasDe(new EfectoDelSilencio, $this->empresa, LIMITE_DEL_PREAVISO)[0];

        expect($alerta->cuerpo)->toEndWith(
            'Si no se avisa, el contrato queda prorrogado automáticamente por un término igual al '
            .'último pactado.'
        );
    });
});

describe('la última renovación corta posible', function () {
    beforeEach(function () {
        // Un contrato de tres meses con tres prórrogas cortas encima, y la
        // tercera es automática: es justo el caso mezclado que el conteo viejo
        // —cuatro cupos pactados y tres automáticos por separado— dejaba pasar.
        // El tramo vigente vence el 31 de diciembre de 2026, igual que el
        // contrato de la batería, así que el disparo cae el mismo día.
        $this->corta = empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo(INICIO_DEL_FIJO, '2026-03-31')
        );

        conCadenaDeProrrogas($this->corta, [
            [EventoContractual::ProrrogaPactada, '2026-06-30'],
            [EventoContractual::ProrrogaPactada, '2026-09-30'],
            [EventoContractual::ProrrogaAutomatica, FIN_DEL_FIJO],
        ]);
    });

    it('sale el mismo día que el plazo para definir la renovación', function () {
        expect(alertasDe(new UltimaRenovacionCorta, $this->empresa, PLAZO_PARA_DEFINIR))->toHaveCount(1)
            ->and(alertasDe(new UltimaRenovacionCorta, $this->empresa, '2026-11-28'))->toBe([])
            ->and(alertasDe(new UltimaRenovacionCorta, $this->empresa, '2026-11-30'))->toBe([])
            // Y no el día en que se registró la prórroga que agotó el cupo: es
            // un recordatorio del momento de decidir, no un acuse de recibo.
            ->and(alertasDe(new UltimaRenovacionCorta, $this->empresa, '2026-10-01'))->toBe([]);
    });

    it('escribe el texto de la sección 5.4 y habla del vencimiento del tramo', function () {
        $alerta = alertasDe(new UltimaRenovacionCorta, $this->empresa, PLAZO_PARA_DEFINIR)[0];

        expect($alerta->asunto)->toBe('La próxima renovación de Ana Gómez debe ser de un año')
            ->and($alerta->cuerpo)->toBe(
                'El contrato de Ana Gómez acumula 3 prórrogas inferiores a un año. La próxima '
                .'renovación deberá ser de mínimo un año.'
            )
            ->and($alerta->fechaDelHecho->toDateString())->toBe(FIN_DEL_FIJO);
    });

    it('no sale mientras al contrato le quede cupo', function () {
        $otra = Tenant::factory()->create();
        usuarioDe($otra, Rol::Administrador, Modulo::Contratos);

        $contrato = empleadaEnPrueba(
            $otra,
            Vinculacion::factory()->aTerminoFijo(INICIO_DEL_FIJO, '2026-03-31')
        );

        // Dos cortas de las tres: todavía cabe una más.
        conCadenaDeProrrogas($contrato, [
            [EventoContractual::ProrrogaPactada, '2026-09-30'],
            [EventoContractual::ProrrogaPactada, FIN_DEL_FIJO],
        ]);

        expect(alertasDe(new UltimaRenovacionCorta, $otra, PLAZO_PARA_DEFINIR))->toBe([]);
    });

    it('no se repite en el tramo siguiente cuando ya se renovó por un año', function () {
        // La empresa hizo lo que el aviso le pedía: el tramo vigente ya no es
        // el que agotó el cupo, y volver a avisar sería ruido.
        conCadenaDeProrrogas($this->corta, [[EventoContractual::ProrrogaPactada, '2027-12-31']]);

        expect(alertasDe(new UltimaRenovacionCorta, $this->empresa, PLAZO_PARA_DEFINIR))->toBe([])
            ->and(alertasDe(new UltimaRenovacionCorta, $this->empresa, '2027-11-29'))->toBe([]);
    });

    it('no le toca al contrato inicial de un año o más, aunque acumule prórrogas cortas', function () {
        // La regla del artículo 46 solo alcanza a los contratos inferiores a un
        // año. El de la batería es de un año justo y tiene su propio disparo el
        // mismo día, así que sirve de contraejemplo tal como está.
        conCadenaDeProrrogas($this->empleada, [
            [EventoContractual::ProrrogaPactada, '2027-03-31'],
            [EventoContractual::ProrrogaPactada, '2027-06-30'],
            [EventoContractual::ProrrogaPactada, '2027-09-30'],
        ]);

        expect(alertasDe(new UltimaRenovacionCorta, $this->empresa, '2027-08-29'))->toBe([]);
    });

    it('el proceso diario la manda junto a la del plazo y no la repite', function () {
        Mail::fake();

        // El 29 de noviembre de 2026 es domingo, así que el proceso lo evalúa
        // el viernes anterior, igual que en la batería del corrimiento.
        $primera = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-11-27'));
        $segunda = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-11-27'));

        $alerta = alertaDelTipo($this->empresa, TipoDeAlerta::UltimaRenovacionCorta);

        expect($alerta)->not->toBeNull()
            ->and($alerta->vinculacion_id)->toBe($this->corta->getKey())
            ->and($alerta->fecha_del_hecho->toDateString())->toBe(FIN_DEL_FIJO)
            ->and($alerta->destinatarios)->toBe([$this->usuario->email])
            // Las dos empleadas vencen el mismo día: dos avisos de plazo y uno
            // de última renovación corta.
            ->and($primera->registradas())->toBe(3)
            ->and($segunda->registradas())->toBe(0);
    });
});

describe('el proceso diario', function () {
    it('la del 29 de noviembre, que es domingo, sale el viernes y ajusta la cuenta', function () {
        Mail::fake();

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-11-27'));

        $alerta = alertaRegistrada($this->empresa);

        expect($alerta->tipo)->toBe(TipoDeAlerta::PlazoDeRenovacion)
            ->and($alerta->fecha_programada->toDateString())->toBe('2026-11-27')
            ->and($alerta->fecha_del_hecho->toDateString())->toBe(LIMITE_DEL_PREAVISO)
            ->and($alerta->cuerpo)->toContain(
                'En cuatro días (1 de diciembre) vence el plazo legal de 30 días'
            );
    });

    it('registra el efecto del silencio con su tipo y sus destinatarios', function () {
        Mail::fake();

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(LIMITE_DEL_PREAVISO));

        $alerta = alertaRegistrada($this->empresa);

        expect($alerta->tipo)->toBe(TipoDeAlerta::EfectoDelSilencio)
            ->and($alerta->vinculacion_id)->toBe($this->empleada->getKey())
            ->and($alerta->destinatarios)->toBe([$this->usuario->email]);
    });

    it('ejecutarlo dos veces el mismo día no duplica el correo', function () {
        Mail::fake();

        $primera = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(LIMITE_DEL_PREAVISO));
        $segunda = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(LIMITE_DEL_PREAVISO));

        expect($primera->registradas())->toBe(1)
            ->and($segunda->registradas())->toBe(0)
            ->and($segunda->repetidas())->toBe(1);
    });
});
