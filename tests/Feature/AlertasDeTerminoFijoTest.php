<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Alertas\Reglas\EfectoDelSilencio;
use App\Dominio\Alertas\Reglas\PlazoDeRenovacion;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Las dos alertas del preaviso del término fijo (F3-04, documento 1, 9.4).
 *
 * Son las dos primeras filas de la tabla: el plazo para definir la renovación,
 * 32 días antes del vencimiento, y el efecto del silencio, 30 días antes. Las
 * otras dos de esa tabla —última renovación corta y cercanía al tope— no están
 * construidas: su día de disparo depende de decisiones abiertas del documento 6
 * y el motivo está escrito en `BACKLOG.md` y en `ESTADO.md`.
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
