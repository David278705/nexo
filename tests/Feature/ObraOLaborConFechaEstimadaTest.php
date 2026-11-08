<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\FechaFinEstimada;
use App\Contratacion\TipoContrato;
use App\Dominio\Alertas\Reglas\PlazoDeRenovacion;
use App\Dominio\Alertas\Reglas\PruebaFijoDosDias;
use App\Dominio\Alertas\Reglas\PruebaFijoVispera;
use App\Dominio\Alertas\Reglas\PruebaObraLaborDosDias;
use App\Dominio\Alertas\Reglas\PruebaObraLaborVispera;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Dominio\Calculo\PeriodoDePrueba;
use App\Inicio\AtencionDelInicio;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Database\Factories\VinculacionFactory;
use Illuminate\Support\Carbon;

/**
 * La obra o labor con fecha de finalización estimada (`OL-01`).
 *
 * Decidido por David el 2026-11-02: la obra o labor maneja periodo de prueba,
 * calculado con las reglas del término fijo sobre una fecha que la empresa
 * estima y que es opcional. Sin ella no hay periodo de prueba.
 *
 * Los ejemplos reutilizan el ingreso del 15 de abril de 2026 de las alertas de
 * periodo de prueba, para que las cuentas se lean contra las del término fijo:
 * con una fecha estimada del 14 de octubre, la duración son 183 días, la quinta
 * parte 36 y el periodo termina el 20 de mayo.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; `empleadaEnPrueba()` y
 * `alertasDe()` en `AlertasDePeriodoDePruebaTest.php`; `catalogosDelFormulario()`,
 * `registrarEmpleado()` y `ultimaVinculacion()` en `RegistroDeEmpleadoTest.php`;
 * `fichaDe()` en `FichaDeEmpleadoTest.php`.
 */
const INGRESO_DE_LA_OBRA = '2026-04-15';

const FIN_ESTIMADO_DE_LA_OBRA = '2026-10-14';

beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Constructora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados, Modulo::Contratos);
    $this->catalogos = catalogosDelFormulario();
});

/**
 * La vinculación sin guardar, armada con la empresa en contexto: su persona sí
 * se crea, y nada se escribe fuera de una empresa.
 */
function hecha(VinculacionFactory $fabrica): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, fn (): Vinculacion => $fabrica->make());
}

function prueba(Vinculacion $vinculacion): PeriodoDePrueba
{
    return PeriodoDePrueba::de($vinculacion);
}

describe('el cálculo', function () {
    it('no calcula periodo de prueba en la obra o labor sin fecha estimada', function () {
        $obra = hecha(Vinculacion::factory()->deObraOLabor()->conIngreso(INGRESO_DE_LA_OBRA));

        expect(prueba($obra)->dias())->toBe(0)
            ->and(prueba($obra)->aplica())->toBeFalse()
            ->and(prueba($obra)->esEstimado())->toBeFalse();
    });

    it('con fecha estimada toma la quinta parte, igual que el término fijo', function () {
        $obra = hecha(Vinculacion::factory()
            ->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)
            ->conIngreso(INGRESO_DE_LA_OBRA)
        );
        $fijo = hecha(Vinculacion::factory()->aTerminoFijo(INGRESO_DE_LA_OBRA, FIN_ESTIMADO_DE_LA_OBRA));

        expect(prueba($obra)->dias())->toBe(36)
            ->and(prueba($obra)->terminaEl()->toDateString())->toBe('2026-05-20')
            ->and(prueba($obra)->dias())->toBe(prueba($fijo)->dias());
    });

    it('con una estimación de un año o más no pasa de dos meses', function () {
        $obra = hecha(Vinculacion::factory()
            ->deObraOLaborEstimadaHasta('2027-12-31')
            ->conIngreso(INGRESO_DE_LA_OBRA)
        );

        expect(prueba($obra)->terminaEl()->toDateString())->toBe('2026-06-14');
    });

    it('marca el periodo como estimado solo en la obra o labor', function () {
        $obra = hecha(Vinculacion::factory()
            ->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)
            ->conIngreso(INGRESO_DE_LA_OBRA)
        );
        $fijo = hecha(Vinculacion::factory()->aTerminoFijo(INGRESO_DE_LA_OBRA, FIN_ESTIMADO_DE_LA_OBRA));

        expect(prueba($obra)->esEstimado())->toBeTrue()
            ->and(prueba($fijo)->esEstimado())->toBeFalse();
    });

    it('no confunde la fecha estimada con un vencimiento', function () {
        $obra = hecha(Vinculacion::factory()
            ->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)
            ->conIngreso(INGRESO_DE_LA_OBRA)
        );

        expect($obra->fecha_fin)->toBeNull();
    });
});

describe('el registro', function () {
    it('guarda la fecha estimada de una obra o labor', function () {
        registrarEmpleado([
            'tipo_contrato' => TipoContrato::ObraLabor->value,
            'fecha_ingreso' => INGRESO_DE_LA_OBRA,
            'fecha_fin_estimada' => FIN_ESTIMADO_DE_LA_OBRA,
        ])->assertSessionHasNoErrors()->assertRedirect('/empleados');

        $vinculacion = ultimaVinculacion();

        expect($vinculacion->fecha_fin_estimada?->toDateString())->toBe(FIN_ESTIMADO_DE_LA_OBRA)
            ->and($vinculacion->fecha_fin)->toBeNull();
    });

    it('registra la obra o labor sin fecha estimada, porque es opcional', function () {
        registrarEmpleado([
            'tipo_contrato' => TipoContrato::ObraLabor->value,
            'fecha_ingreso' => INGRESO_DE_LA_OBRA,
        ])->assertSessionHasNoErrors()->assertRedirect('/empleados');

        expect(ultimaVinculacion()->fecha_fin_estimada)->toBeNull();
    });

    it('rechaza una fecha estimada que no es posterior al ingreso', function () {
        registrarEmpleado([
            'tipo_contrato' => TipoContrato::ObraLabor->value,
            'fecha_ingreso' => INGRESO_DE_LA_OBRA,
            'fecha_fin_estimada' => INGRESO_DE_LA_OBRA,
        ])->assertSessionHasErrors([
            'fecha_fin_estimada' => 'La fecha de finalización estimada tiene que ser posterior a la de ingreso.',
        ]);
    });

    it('rechaza la fecha estimada en las otras modalidades', function (TipoContrato $tipo, array $extra) {
        registrarEmpleado([
            'tipo_contrato' => $tipo->value,
            'fecha_ingreso' => INGRESO_DE_LA_OBRA,
            'fecha_fin_estimada' => FIN_ESTIMADO_DE_LA_OBRA,
            ...$extra,
        ])->assertSessionHasErrors([
            'fecha_fin_estimada' => 'Solo el contrato de obra o labor lleva fecha de finalización estimada.',
        ]);
    })->with([
        'indefinido' => [TipoContrato::Indefinido, []],
        'término fijo' => [TipoContrato::Fijo, ['fecha_fin' => FIN_ESTIMADO_DE_LA_OBRA]],
    ]);

    it('le entrega al asistente el aviso con los textos de FechaFinEstimada', function () {
        $opciones = test()->actingAs(test()->usuario)->get('/empleados/nuevo')
            ->assertOk()
            ->viewData('page')['props']['opciones'];

        expect($opciones['contratoConFechaEstimada'])->toBe(TipoContrato::ObraLabor->value)
            ->and($opciones['avisoDeObraOLabor'])->toBe(FechaFinEstimada::aviso());
    });
});

describe('el aviso', function () {
    it('dice que el periodo es una sugerencia sobre la fecha que pone el cliente', function () {
        expect(FechaFinEstimada::EXPLICACION)
            ->toContain('fecha estimada de finalización')
            ->toContain('periodo de prueba sugerido')
            ->toContain('mismas reglas del contrato a término fijo')
            ->toContain('Si no la registra, el sistema no calcula periodo de prueba');
    });

    it('dice que el sistema no puede comprobar la fecha y que el riesgo es de la empresa', function () {
        expect(FechaFinEstimada::RIESGO)
            ->toContain('no tiene cómo comprobar')
            ->toContain('si la obra termina antes de lo estimado')
            ->toContain('no ser válido')
            ->toContain('le corresponde a la empresa');
    });
});

describe('las alertas', function () {
    it('avisa dos días antes y la víspera, con los tipos de la obra o labor', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)->conIngreso(INGRESO_DE_LA_OBRA),
        );

        $dosDias = alertasDe(new PruebaObraLaborDosDias, $this->empresa, '2026-05-18');
        $vispera = alertasDe(new PruebaObraLaborVispera, $this->empresa, '2026-05-19');

        expect($dosDias)->toHaveCount(1)
            ->and($dosDias[0]->tipo)->toBe(TipoDeAlerta::PruebaObraLaborDosDias)
            ->and($vispera)->toHaveCount(1)
            ->and($vispera[0]->tipo)->toBe(TipoDeAlerta::PruebaObraLaborVispera);
    });

    it('lleva en el correo la nota de que el periodo es estimado', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)->conIngreso(INGRESO_DE_LA_OBRA),
        );

        $alerta = alertasDe(new PruebaObraLaborDosDias, $this->empresa, '2026-05-18')[0];

        expect($alerta->cuerpo)->toContain(FechaFinEstimada::NOTA);
    });

    it('no le pone la nota al término fijo', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->aTerminoFijo(INGRESO_DE_LA_OBRA, FIN_ESTIMADO_DE_LA_OBRA),
        );

        $alerta = alertasDe(new PruebaFijoDosDias, $this->empresa, '2026-05-18')[0];

        expect($alerta->cuerpo)->not->toContain(FechaFinEstimada::NOTA);
    });

    it('no avisa nada a la obra o labor sin fecha estimada', function () {
        empleadaEnPrueba($this->empresa, Vinculacion::factory()->deObraOLabor()->conIngreso(INGRESO_DE_LA_OBRA));

        expect(alertasDe(new PruebaObraLaborDosDias, $this->empresa, '2026-06-12'))->toBe([])
            ->and(alertasDe(new PruebaObraLaborVispera, $this->empresa, '2026-06-13'))->toBe([]);
    });

    it('no se cruza con las alertas del término fijo', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)->conIngreso(INGRESO_DE_LA_OBRA),
        );

        expect(alertasDe(new PruebaFijoDosDias, $this->empresa, '2026-05-18'))->toBe([])
            ->and(alertasDe(new PruebaFijoVispera, $this->empresa, '2026-05-19'))->toBe([]);
    });

    it('no dispara alertas de renovación: la fecha estimada no es un vencimiento', function () {
        empleadaEnPrueba(
            $this->empresa,
            Vinculacion::factory()->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)->conIngreso(INGRESO_DE_LA_OBRA),
        );

        // El plazo para definir la renovación de un fijo que vence el 14 de
        // octubre cae el 12 de septiembre.
        expect(alertasDe(new PlazoDeRenovacion, $this->empresa, '2026-09-12'))->toBe([]);
    });
});

describe('las pantallas', function () {
    it('la tarjeta del inicio cuenta la obra o labor con fecha estimada', function () {
        $hoy = Carbon::parse('2026-08-17');

        $ids = app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($hoy): array {
            // Estimación larga: dos meses de prueba, hasta el 19 de agosto.
            Vinculacion::factory()->deObraOLaborEstimadaHasta('2027-12-31')->conIngreso('2026-06-20')->create();
            // Sin estimación: no tiene periodo de prueba que terminar.
            Vinculacion::factory()->deObraOLabor()->conIngreso('2026-06-20')->create();

            return AtencionDelInicio::PeriodosDePrueba->identificadores($hoy);
        });

        expect($ids)->toHaveCount(1);
    });

    it('la ficha enseña la fecha estimada y el periodo sugerido con su nota', function () {
        $obra = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->deObraOLaborEstimadaHasta(FIN_ESTIMADO_DE_LA_OBRA)
            ->conIngreso(INGRESO_DE_LA_OBRA)
            ->create());

        $campos = collect(fichaDe($obra)['contrato']['campos'])->pluck('valor', 'etiqueta');

        expect($campos['Fecha de finalización estimada'])->toBe('14/10/2026')
            ->and($campos['Periodo de prueba sugerido'])->toBe('Hasta el 20/05/2026. '.FechaFinEstimada::NOTA);
    });

    it('la ficha dice por qué no hay periodo cuando falta la fecha estimada', function () {
        $obra = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->deObraOLabor()
            ->conIngreso(INGRESO_DE_LA_OBRA)
            ->create());

        $campos = collect(fichaDe($obra)['contrato']['campos'])->pluck('valor', 'etiqueta');

        expect($campos['Periodo de prueba sugerido'])
            ->toBe('Sin calcular: no se registró fecha de finalización estimada.');
    });

    it('la ficha no enseña la fecha estimada en el término fijo', function () {
        $fijo = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->aTerminoFijo(INGRESO_DE_LA_OBRA, FIN_ESTIMADO_DE_LA_OBRA)
            ->create());

        $etiquetas = array_column(fichaDe($fijo)['contrato']['campos'], 'etiqueta');

        expect($etiquetas)->not->toContain('Fecha de finalización estimada')
            ->not->toContain('Periodo de prueba sugerido');
    });
});

describe('corregir la fecha estimada (PROD-23)', function () {
    function obraDeLaPrueba(?string $estimada = FIN_ESTIMADO_DE_LA_OBRA): Vinculacion
    {
        return app(ContextoTenant::class)->paraTenant(test()->empresa, fn (): Vinculacion => ($estimada === null
            ? Vinculacion::factory()->deObraOLabor()
            : Vinculacion::factory()->deObraOLaborEstimadaHasta($estimada))
            ->conIngreso(INGRESO_DE_LA_OBRA)
            ->create());
    }

    it('corrige la fecha y el periodo de prueba se recalcula con ella', function () {
        $obra = obraDeLaPrueba();

        $this->actingAs($this->usuario)
            ->put("/contratos/{$obra->id}/fecha-estimada", ['fecha_fin_estimada' => '2027-12-31'])
            ->assertRedirect("/contratos/{$obra->id}");

        expect($obra->fresh()->fecha_fin_estimada->toDateString())->toBe('2027-12-31')
            ->and(prueba($obra->fresh())->terminaEl()->toDateString())->toBe('2026-06-14');
    });

    it('la deja vacía y el contrato se queda sin periodo de prueba', function () {
        $obra = obraDeLaPrueba();

        $this->actingAs($this->usuario)
            ->put("/contratos/{$obra->id}/fecha-estimada", ['fecha_fin_estimada' => ''])
            ->assertRedirect();

        expect($obra->fresh()->fecha_fin_estimada)->toBeNull()
            ->and(prueba($obra->fresh())->aplica())->toBeFalse();
    });

    it('la agrega a una obra que no la tenía', function () {
        $obra = obraDeLaPrueba(null);

        $this->actingAs($this->usuario)
            ->put("/contratos/{$obra->id}/fecha-estimada", ['fecha_fin_estimada' => FIN_ESTIMADO_DE_LA_OBRA]);

        expect(prueba($obra->fresh())->terminaEl()->toDateString())->toBe('2026-05-20');
    });

    it('rechaza una fecha que no es posterior al ingreso', function () {
        $obra = obraDeLaPrueba();

        $this->actingAs($this->usuario)
            ->put("/contratos/{$obra->id}/fecha-estimada", ['fecha_fin_estimada' => INGRESO_DE_LA_OBRA])
            ->assertSessionHasErrors('fecha_fin_estimada');
    });

    it('no existe en un término fijo', function () {
        $fijo = app(ContextoTenant::class)->paraTenant(test()->empresa, fn () => Vinculacion::factory()
            ->aTerminoFijo(INGRESO_DE_LA_OBRA, FIN_ESTIMADO_DE_LA_OBRA)->create());

        $this->actingAs($this->usuario)
            ->put("/contratos/{$fijo->id}/fecha-estimada", ['fecha_fin_estimada' => '2027-01-01'])
            ->assertForbidden();
    });

    it('el detalle del contrato trae la fecha para corregirla solo en obra o labor', function () {
        $obra = obraDeLaPrueba();

        $props = $this->actingAs($this->usuario)->get("/contratos/{$obra->id}")->viewData('page')['props'];

        expect($props['fechaEstimada']['valor'])->toBe(FIN_ESTIMADO_DE_LA_OBRA)
            ->and($props['fechaEstimada']['texto'])->toBe('14/10/2026');
    });
});
