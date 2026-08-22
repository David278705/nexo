<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Alertas\Reglas\AcumulacionDeQuinceDias;
use App\Dominio\Alertas\Reglas\AnioSinDisfruteMinimo;
use App\Dominio\Alertas\Reglas\ContratosVencidosSinGestionar;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\CorreoDeAlerta;
use App\Terminaciones\TipoDeSalida;
use Database\Factories\VinculacionFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Los resúmenes mensuales (F3-05, documento 1, 9.4, 9.5 y 9.6).
 *
 * Tres de los cuatro: contratos vencidos sin gestionar, acumulación de 15 días
 * de vacaciones y año cumplido sin disfrute mínimo. El cuarto —obra o labor
 * prolongada— depende del umbral de la decisión D.2, que sigue abierta, y no
 * tiene regla construida ni prueba que escribir.
 *
 * Lo que los separa de las nueve alertas individuales es lo que más se prueba
 * aquí: **salen el último día hábil del mes** y **van agrupados en un solo
 * correo por empresa**, sin vinculación.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; `alertasDe()` y
 * `alertaRegistrada()`, en `AlertasDePeriodoDePruebaTest.php`;
 * `conCadenaDeProrrogas()` y `alertaDelTipo()`, en `AlertasDeTerminoFijoTest.php`.
 */

/**
 * El último día hábil de agosto de 2026, que es lunes 31: el mes termina en día
 * de oficina y el corte cae en el último día del calendario.
 */
const CORTE_DE_AGOSTO = '2026-08-31';

/**
 * El último día hábil de mayo de 2026. Mayo termina en domingo, así que el
 * corte se queda en el viernes 29.
 */
const CORTE_DE_MAYO = '2026-05-29';

beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos, Modulo::Vacaciones);
});

/**
 * Alguien con nombre y cargo fijos, porque los dos son la mitad de cada línea
 * del listado y con un nombre aleatorio no se podría comprobar el texto.
 */
function empleadoDelResumen(
    Tenant $empresa,
    VinculacionFactory $vinculacion,
    string $nombre,
    string $apellido,
    string $cargo,
): Vinculacion {
    return app(ContextoTenant::class)->paraTenant($empresa, fn (): Vinculacion => $vinculacion
        ->de(Persona::factory()->sinSegundosNombres()->create([
            'primer_nombre' => $nombre,
            'primer_apellido' => $apellido,
        ]))
        ->create(['cargo' => $cargo]));
}

/**
 * Ana Gómez, auxiliar contable, con un contrato a término fijo que venció el 31
 * de diciembre de 2025 y que nadie prorrogó ni terminó.
 */
function contratoVencido(Tenant $empresa): Vinculacion
{
    return empleadoDelResumen(
        $empresa,
        Vinculacion::factory()->aTerminoFijo('2025-01-01', '2025-12-31'),
        'Ana',
        'Gómez',
        'Auxiliar contable',
    );
}

describe('el «hecho cuando» de la tarea', function () {
    it('el resumen llega agrupado en un solo correo, no uno por empleado', function () {
        contratoVencido($this->empresa);
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2025-11-30'),
            'Luis',
            'Ríos',
            'Mensajero',
        );
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2025-10-31'),
            'Carla',
            'Díaz',
            'Recepcionista',
        );

        $alertas = alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO);

        expect($alertas)->toHaveCount(1)
            ->and($alertas[0]->vinculacion)->toBeNull()
            ->and($alertas[0]->cuerpo)->toContain('Ana Gómez')
            ->and($alertas[0]->cuerpo)->toContain('Luis Ríos')
            ->and($alertas[0]->cuerpo)->toContain('Carla Díaz');
    });

    it('el proceso diario lo registra sin vinculación y manda un solo correo', function () {
        Mail::fake();
        contratoVencido($this->empresa);
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2025-11-30'),
            'Luis',
            'Ríos',
            'Mensajero',
        );

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(CORTE_DE_AGOSTO));

        $alerta = alertaDelTipo($this->empresa, TipoDeAlerta::ContratosVencidosSinGestionar);

        expect($alerta)->not->toBeNull()
            ->and($alerta->vinculacion_id)->toBeNull()
            ->and($alerta->destinatarios)->toBe([$this->usuario->email]);

        Mail::assertSent(CorreoDeAlerta::class, 1);
    });

    it('correrlo dos veces el mismo día no duplica el resumen', function () {
        Mail::fake();
        contratoVencido($this->empresa);

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(CORTE_DE_AGOSTO));
        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(CORTE_DE_AGOSTO));

        Mail::assertSent(CorreoDeAlerta::class, 1);
    });
});

describe('el día en que salen', function () {
    it('salen el último día hábil del mes y ningún otro día', function () {
        contratoVencido($this->empresa);

        expect(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO))->toHaveCount(1)
            ->and(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, '2026-08-28'))->toBe([])
            ->and(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, '2026-09-01'))->toBe([]);
    });

    it('cuando el mes termina en domingo, el corte se queda en el viernes anterior', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2026-04-30'),
            'Ana',
            'Gómez',
            'Auxiliar contable',
        );

        expect(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_MAYO))->toHaveCount(1)
            ->and(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, '2026-05-31'))->toBe([]);
    });

    it('no manda nada el mes en que no hay a quién listar', function () {
        Mail::fake();

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(CORTE_DE_AGOSTO));

        expect(alertaRegistrada($this->empresa))->toBeNull();

        Mail::assertNothingSent();
    });
});

describe('contratos vencidos sin gestionar', function () {
    it('escribe el asunto y el listado con la fecha y los días transcurridos', function () {
        contratoVencido($this->empresa);

        $alerta = alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO)[0];

        expect($alerta->asunto)->toBe('Contratos vencidos sin registrar: 1')
            ->and($alerta->cuerpo)->toBe(
                "Este contrato superó su fecha de finalización y no se registró ni su prórroga ni su terminación.\n"
                .'Ana Gómez, Auxiliar contable — venció el 31 de diciembre de 2025, hace 243 días.'
            );
    });

    it('sale del listado en cuanto se registra la prórroga', function () {
        $contrato = contratoVencido($this->empresa);

        conCadenaDeProrrogas($contrato, [[EventoContractual::ProrrogaPactada, '2026-12-31']]);

        expect(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });

    it('sale del listado en cuanto se registra la terminación', function () {
        $contrato = contratoVencido($this->empresa);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $contrato->registrarTerminacion(
            TipoDeSalida::ExpiracionDelPlazo,
            null,
            Carbon::parse('2025-12-31'),
        ));

        expect(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });

    it('no mira el indefinido ni el de obra o labor, que no tienen fecha que superar', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2024-01-01'),
            'Luis',
            'Ríos',
            'Mensajero',
        );
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->deObraOLabor()->conIngreso('2024-01-01'),
            'Carla',
            'Díaz',
            'Recepcionista',
        );

        expect(alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });

    it('pone primero el que lleva más tiempo vencido', function () {
        contratoVencido($this->empresa);
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2025-10-31'),
            'Carla',
            'Díaz',
            'Recepcionista',
        );

        $cuerpo = alertasDe(new ContratosVencidosSinGestionar, $this->empresa, CORTE_DE_AGOSTO)[0]->cuerpo;

        expect(mb_strpos($cuerpo, 'Carla Díaz'))->toBeLessThan(mb_strpos($cuerpo, 'Ana Gómez'));
    });
});

describe('acumulación de 15 días', function () {
    it('lista a quien completa este mes el periodo completo', function () {
        // Ingreso el 1 de septiembre de 2025: al cerrar agosto de 2026 lleva
        // doce meses acumulando a 1,25, que son los quince días del periodo.
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-09-01'),
            'Luis',
            'Ríos',
            'Mensajero',
        );

        $alerta = alertasDe(new AcumulacionDeQuinceDias, $this->empresa, CORTE_DE_AGOSTO)[0];

        expect($alerta->asunto)->toBe('Vacaciones acumuladas: 1 persona completó 15 días')
            ->and($alerta->cuerpo)->toBe(
                'Esta persona completó este mes 15 días de vacaciones acumuladas. Ya causó un periodo '
                ."completo y conviene programarlas.\n"
                .'Luis Ríos, Mensajero — 15 días acumulados.'
            );
    });

    it('no repite al que ya tenía el periodo completo el mes anterior', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-08-01'),
            'Luis',
            'Ríos',
            'Mensajero',
        );

        expect(alertasDe(new AcumulacionDeQuinceDias, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });

    it('no lista a quien todavía no llega', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-10-01'),
            'Luis',
            'Ríos',
            'Mensajero',
        );

        expect(alertasDe(new AcumulacionDeQuinceDias, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });

    it('también le aplica al contrato de obra o labor', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->deObraOLabor()->conIngreso('2025-09-01'),
            'Carla',
            'Díaz',
            'Recepcionista',
        );

        expect(alertasDe(new AcumulacionDeQuinceDias, $this->empresa, CORTE_DE_AGOSTO))->toHaveCount(1);
    });
});

describe('año cumplido sin disfrute mínimo', function () {
    it('escribe el asunto y el listado de quien cumple el año el mes entrante', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-09-15'),
            'Ana',
            'Gómez',
            'Auxiliar contable',
        );

        $alerta = alertasDe(new AnioSinDisfruteMinimo, $this->empresa, CORTE_DE_AGOSTO)[0];

        expect($alerta->asunto)->toBe('Vacaciones pendientes: 1 persona cumple un año de servicio')
            ->and($alerta->cuerpo)->toBe(
                'Esta persona cumple el mes entrante un año de servicio en la empresa sin haber tomado '
                ."al menos 6 días hábiles continuos de vacaciones, que es el mínimo anual que exige la ley.\n"
                .'Ana Gómez, Auxiliar contable — cumple un año el 14 de septiembre de 2026.'
            );
    });

    it('no lista a quien sí tomó seis días hábiles continuos en ese año', function () {
        $empleada = empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-09-15'),
            'Ana',
            'Gómez',
            'Auxiliar contable',
        );

        // Del lunes 6 al martes 14 de julio de 2026: siete días hábiles, sin
        // festivos en el medio.
        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()->de($empleada)->create([
            'fecha_inicio' => '2026-07-06',
            'fecha_fin' => '2026-07-14',
        ]));

        expect(alertasDe(new AnioSinDisfruteMinimo, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });

    it('dos periodos cortos no son un descanso continuo', function () {
        $empleada = empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-09-15'),
            'Ana',
            'Gómez',
            'Auxiliar contable',
        );

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($empleada) {
            // Cinco días hábiles y luego tres: ocho en total, ninguno continuo
            // de seis.
            Vacacion::factory()->de($empleada)->del27al31DeJulio(2026)->create();
            Vacacion::factory()->de($empleada)->create([
                'fecha_inicio' => '2026-06-01',
                'fecha_fin' => '2026-06-03',
            ]);
        });

        expect(alertasDe(new AnioSinDisfruteMinimo, $this->empresa, CORTE_DE_AGOSTO))->toHaveCount(1);
    });

    it('también avisa de los aniversarios posteriores al primero', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2023-09-15'),
            'Ana',
            'Gómez',
            'Auxiliar contable',
        );

        expect(alertasDe(new AnioSinDisfruteMinimo, $this->empresa, CORTE_DE_AGOSTO)[0]->cuerpo)
            ->toContain('cumple 3 años el 14 de septiembre de 2026.');
    });

    it('no lista a quien cumple el año en un mes que no es el entrante', function () {
        empleadoDelResumen(
            $this->empresa,
            Vinculacion::factory()->conIngreso('2025-08-15'),
            'Ana',
            'Gómez',
            'Auxiliar contable',
        );

        expect(alertasDe(new AnioSinDisfruteMinimo, $this->empresa, CORTE_DE_AGOSTO))->toBe([]);
    });
});
