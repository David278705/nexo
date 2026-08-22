<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Dominio\Alertas\AlertaPorEnviar;
use App\Dominio\Alertas\CatalogoDeReglas;
use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Alertas\ReglaDeAlerta;
use App\Dominio\Alertas\Reglas\PruebaFijoDosDias;
use App\Dominio\Alertas\Reglas\PruebaFijoVispera;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoQuinceDias;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoUnMes;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoVispera;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\AlertaEnviada;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\CorreoDeAlerta;
use Database\Factories\VinculacionFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Las cinco alertas de periodo de prueba (F3-03, documento 1, 9.2 y 9.3).
 *
 * El armazón —recorrido de empresas, cola, plantilla base— ya está probado en
 * `AlertasTest`. Aquí se prueba lo que es de estas cinco: qué día dispara cada
 * una, sobre qué modalidades de contrato, y **qué dice exactamente**, que es lo
 * que el cliente lee y lo único que no se puede aproximar.
 *
 * ## El ejemplo del documento, de punta a punta
 *
 * Toda la batería gira alrededor del ejemplo de la sección 9.2: alguien que
 * entra el 15 de abril de 2026. Su periodo de prueba termina el 14 de junio y
 * las tres alertas caen el 14 de mayo, el 30 de mayo y el 13 de junio. Dos de
 * esas tres fechas son sábado en 2026, así que el ejemplo trae de regalo dos
 * casos reales de corrimiento al día hábil anterior.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`.
 */

/**
 * El ejemplo del documento: ingreso el 15 de abril, periodo de prueba hasta el
 * 14 de junio.
 */
const INGRESO_DEL_EJEMPLO = '2026-04-15';

const FIN_DE_LA_PRUEBA_GENERAL = '2026-06-14';

beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
});

/**
 * Ana Gómez, auxiliar contable, con la vinculación que le pase la prueba.
 *
 * El nombre y el cargo son fijos porque son la mitad del texto de las cinco
 * plantillas: con un nombre aleatorio no se podría comprobar la frase entera.
 */
function empleadaEnPrueba(Tenant $empresa, VinculacionFactory $vinculacion): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant($empresa, fn (): Vinculacion => $vinculacion
        ->de(Persona::factory()->sinSegundosNombres()->create([
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Gómez',
        ]))
        ->create(['cargo' => 'Auxiliar contable']));
}

/**
 * Lo que una regla decide para esa fecha, con la empresa en contexto.
 *
 * `$envio` solo se pasa cuando la prueba está mirando un corrimiento; sin él,
 * el correo sale el mismo día que le tocaba.
 *
 * @return list<AlertaPorEnviar>
 */
function alertasDe(ReglaDeAlerta $regla, Tenant $empresa, string $disparo, ?string $envio = null): array
{
    return app(ContextoTenant::class)->paraTenant($empresa, fn (): array => iterator_to_array(
        $regla->evaluar(Carbon::parse($disparo), Carbon::parse($envio ?? $disparo)),
        false,
    ));
}

function alertaRegistrada(Tenant $empresa): ?AlertaEnviada
{
    return app(ContextoTenant::class)->paraTenant($empresa, fn () => AlertaEnviada::first());
}

describe('el «hecho cuando» de la tarea', function () {
    it('el ingreso el 15 de abril produce las tres alertas en 14/05, 30/05 y 13/06', function () {
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        $enSusFechas = [
            '2026-05-14' => new PruebaIndefinidoUnMes,
            '2026-05-30' => new PruebaIndefinidoQuinceDias,
            '2026-06-13' => new PruebaIndefinidoVispera,
        ];

        foreach ($enSusFechas as $fecha => $regla) {
            expect(alertasDe($regla, $this->empresa, $fecha))->toHaveCount(1);

            // Y ninguna de las tres se dispara la víspera ni el día siguiente:
            // una alerta que se repite dos días seguidos es tan mala como una
            // que no llega.
            expect(alertasDe($regla, $this->empresa, Carbon::parse($fecha)->subDay()->toDateString()))->toBe([])
                ->and(alertasDe($regla, $this->empresa, Carbon::parse($fecha)->addDay()->toDateString()))->toBe([]);
        }
    });
});

describe('los textos del contrato a término indefinido', function () {
    beforeEach(function () {
        $this->empleada = empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO)
        );
    });

    it('la del mes cumplido escribe la fecha entera y no una referencia relativa', function () {
        $alerta = alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14')[0];

        expect($alerta->asunto)->toBe('Periodo de prueba: Ana Gómez completó un mes')
            ->and($alerta->cuerpo)->toBe(
                'Ana Gómez, Auxiliar contable, ha completado un mes de periodo de prueba. '
                .'Su periodo de prueba termina el 14 de junio de 2026.'
            );
    });

    it('la de los 15 días dice «completará en 15 días (14 de junio)»', function () {
        $alerta = alertasDe(new PruebaIndefinidoQuinceDias, $this->empresa, '2026-05-30')[0];

        expect($alerta->asunto)->toBe('Periodo de prueba de Ana Gómez: termina en 15 días (14 de junio)')
            ->and($alerta->cuerpo)->toBe(
                'Ana Gómez, Auxiliar contable, completará en 15 días (14 de junio) su periodo de prueba.'
            );
    });

    it('la víspera dice «Mañana (14 de junio) es el último día»', function () {
        $alerta = alertasDe(new PruebaIndefinidoVispera, $this->empresa, '2026-06-13')[0];

        expect($alerta->asunto)->toBe('Mañana (14 de junio) vence el periodo de prueba de Ana Gómez')
            ->and($alerta->cuerpo)->toBe(
                'Mañana (14 de junio) es el último día del periodo de prueba de Ana Gómez, Auxiliar contable.'
            );
    });

    it('las tres hablan del último día del periodo de prueba, no del día en que salen', function () {
        $alerta = alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14')[0];

        expect($alerta->fechaDelHecho->toDateString())->toBe(FIN_DE_LA_PRUEBA_GENERAL);
    });
});

describe('los textos del contrato a término fijo', function () {
    // Seis meses pactados, del 15 de abril al 14 de octubre: 183 días, cuya
    // quinta parte son 36, así que el periodo de prueba va del 15 de abril al
    // 20 de mayo. No son los dos meses del indefinido y ese es el punto.
    beforeEach(function () {
        $this->empleada = empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo(INGRESO_DEL_EJEMPLO, '2026-10-14')
        );
    });

    it('la de dos días antes dice «termina en dos días (20 de mayo)»', function () {
        $alerta = alertasDe(new PruebaFijoDosDias, $this->empresa, '2026-05-18')[0];

        expect($alerta->asunto)->toBe('Periodo de prueba de Ana Gómez: termina en dos días (20 de mayo)')
            ->and($alerta->cuerpo)->toBe(
                'El periodo de prueba de Ana Gómez, Auxiliar contable, termina en dos días (20 de mayo).'
            );
    });

    it('la víspera repite el texto de la del indefinido, palabra por palabra', function () {
        $alerta = alertasDe(new PruebaFijoVispera, $this->empresa, '2026-05-19')[0];

        expect($alerta->asunto)->toBe('Mañana (20 de mayo) vence el periodo de prueba de Ana Gómez')
            ->and($alerta->cuerpo)->toBe(
                'Mañana (20 de mayo) es el último día del periodo de prueba de Ana Gómez, Auxiliar contable.'
            );
    });

    it('las dos salen de la quinta parte del plazo y no de los dos meses del indefinido', function () {
        // El 13 de junio es la víspera del periodo de prueba del indefinido. A
        // este contrato no le toca nada ese día: el suyo se acabó en mayo.
        expect(alertasDe(new PruebaFijoVispera, $this->empresa, '2026-06-13'))->toBe([]);
    });
});

describe('a quién le toca cada calendario', function () {
    it('el contrato a término fijo no recibe las tres del indefinido', function () {
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->aTerminoFijo(INGRESO_DEL_EJEMPLO, '2026-10-14'));

        expect(alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14'))->toBe([])
            ->and(alertasDe(new PruebaIndefinidoQuinceDias, $this->empresa, '2026-05-30'))->toBe([])
            ->and(alertasDe(new PruebaIndefinidoVispera, $this->empresa, '2026-06-13'))->toBe([]);
    });

    it('el contrato indefinido no recibe las dos del término fijo', function () {
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        expect(alertasDe(new PruebaFijoDosDias, $this->empresa, '2026-06-12'))->toBe([])
            ->and(alertasDe(new PruebaFijoVispera, $this->empresa, '2026-06-13'))->toBe([]);
    });

    it('la obra o labor recibe las tres del periodo general, como dice la sección 9.6', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->deObraOLabor()->conIngreso(INGRESO_DEL_EJEMPLO)
        );

        expect(alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14'))->toHaveCount(1)
            ->and(alertasDe(new PruebaIndefinidoQuinceDias, $this->empresa, '2026-05-30'))->toHaveCount(1)
            ->and(alertasDe(new PruebaIndefinidoVispera, $this->empresa, '2026-06-13'))->toHaveCount(1);
    });

    it('no se dispara si el empleado está inactivo', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO)->terminada()
        );

        expect(alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14'))->toBe([])
            ->and(alertasDe(new PruebaIndefinidoVispera, $this->empresa, '2026-06-13'))->toBe([]);
    });
});

describe('los contratos demasiado cortos', function () {
    it('un contrato de siete días no avisa dos días antes, porque sería antes del ingreso', function () {
        // Siete días pactados: la quinta parte es un día, así que el periodo de
        // prueba empieza y termina el 15 de abril. «Dos días antes» y «la
        // víspera» caen el 13 y el 14, cuando todavía no había nadie contratado.
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->aTerminoFijo(INGRESO_DEL_EJEMPLO, '2026-04-21'));

        expect(alertasDe(new PruebaFijoDosDias, $this->empresa, '2026-04-13'))->toBe([])
            ->and(alertasDe(new PruebaFijoVispera, $this->empresa, '2026-04-14'))->toBe([]);
    });

    it('un contrato de cuatro días no tiene periodo de prueba y no genera ninguna alerta', function () {
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->aTerminoFijo(INGRESO_DEL_EJEMPLO, '2026-04-18'));

        foreach (['2026-04-12', '2026-04-13', '2026-04-14', '2026-04-15'] as $dia) {
            expect(alertasDe(new PruebaFijoDosDias, $this->empresa, $dia))->toBe([])
                ->and(alertasDe(new PruebaFijoVispera, $this->empresa, $dia))->toBe([]);
        }
    });
});

describe('el corrimiento al día hábil anterior', function () {
    it('la del 30 de mayo, que es sábado, sale el viernes diciendo «en 16 días»', function () {
        Mail::fake();
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-05-29'));

        $alerta = alertaRegistrada($this->empresa);

        expect($alerta->tipo)->toBe(TipoDeAlerta::PruebaIndefinidoQuinceDias)
            ->and($alerta->fecha_programada->toDateString())->toBe('2026-05-29')
            ->and($alerta->fecha_del_hecho->toDateString())->toBe(FIN_DE_LA_PRUEBA_GENERAL)
            ->and($alerta->cuerpo)->toBe(
                'Ana Gómez, Auxiliar contable, completará en 16 días (14 de junio) su periodo de prueba.'
            );
    });

    it('la víspera del 13 de junio, que es sábado, sale el viernes y ya no dice «mañana»', function () {
        Mail::fake();
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-06-12'));

        $alerta = alertaRegistrada($this->empresa);

        expect($alerta->tipo)->toBe(TipoDeAlerta::PruebaIndefinidoVispera)
            ->and($alerta->asunto)->toBe('En dos días (14 de junio) vence el periodo de prueba de Ana Gómez')
            ->and($alerta->cuerpo)->toBe(
                'En dos días (14 de junio) es el último día del periodo de prueba de Ana Gómez, Auxiliar contable.'
            );
    });

    it('la del mes cumplido no cambia de texto al adelantarse, porque no habla en relativo', function () {
        Mail::fake();

        // Ingreso el 16 de abril: el mes se cumple el 15 de mayo, que es
        // viernes, y el periodo de prueba termina el 15 de junio.
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso('2026-04-16'));

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-05-15'));

        expect(alertaRegistrada($this->empresa)->cuerpo)->toContain('termina el 15 de junio de 2026.');
    });
});

describe('el proceso diario con las cinco reglas registradas', function () {
    it('el catálogo trae las reglas construidas y solo esas', function () {
        $tipos = array_map(
            fn (ReglaDeAlerta $regla): TipoDeAlerta => $regla->tipo(),
            app(CatalogoDeReglas::class)->reglas(),
        );

        expect($tipos)->toBe([
            TipoDeAlerta::PruebaIndefinidoUnMes,
            TipoDeAlerta::PruebaIndefinidoQuinceDias,
            TipoDeAlerta::PruebaIndefinidoVispera,
            TipoDeAlerta::PruebaFijoDosDias,
            TipoDeAlerta::PruebaFijoVispera,
            TipoDeAlerta::PlazoDeRenovacion,
            TipoDeAlerta::EfectoDelSilencio,
            TipoDeAlerta::UltimaRenovacionCorta,
            TipoDeAlerta::CercaniaAlTope,
        ]);
    });

    it('queda registrada en alertas_enviadas con su tipo y sus destinatarios', function () {
        Mail::fake();
        $empleada = empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-05-14'));

        $alerta = alertaRegistrada($this->empresa);

        expect($alerta->tipo)->toBe(TipoDeAlerta::PruebaIndefinidoUnMes)
            ->and($alerta->vinculacion_id)->toBe($empleada->getKey())
            ->and($alerta->destinatarios)->toBe([$this->usuario->email]);
    });

    it('ejecutar el proceso dos veces el mismo día no duplica el correo', function () {
        Mail::fake();
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        $primera = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-05-14'));
        $segunda = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-05-14'));

        expect($primera->registradas())->toBe(1)
            ->and($segunda->registradas())->toBe(0)
            ->and($segunda->repetidas())->toBe(1);

        Mail::assertSent(CorreoDeAlerta::class, 1);
    });

    it('un día cualquiera sin nadie en su fecha no manda nada', function () {
        Mail::fake();
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->conIngreso(INGRESO_DEL_EJEMPLO));

        $resumen = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse('2026-05-20'));

        expect($resumen->registradas())->toBe(0);

        Mail::assertNothingSent();
    });
});
