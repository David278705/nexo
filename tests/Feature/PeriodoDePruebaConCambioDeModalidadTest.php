<?php

use App\Contratacion\EventoContractual;
use App\Dominio\Alertas\Reglas\PruebaFijoDosDias;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoUnMes;
use App\Dominio\Calculo\PeriodoDePrueba;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;

/**
 * El periodo de prueba no cambia cuando cambia la modalidad (`PROD-19`).
 *
 * El periodo de prueba se pacta al inicio del contrato, y ni una prórroga ni el
 * paso a indefinido abren uno nuevo (documento 6, C.3). Por eso se calcula con
 * el **tramo inicial** del historial y no con lo vigente: antes, un fijo que
 * pasaba a indefinido en sus primeros meses quedaba con el periodo de dos meses
 * del indefinido, y podían salir alertas de un periodo que no existía.
 *
 * El ejemplo de siempre: ingreso el 15 de abril de 2026. Un fijo hasta el 14 de
 * octubre son 183 días, la quinta parte 36, y el periodo termina el 20 de mayo.
 * Si fuera indefinido terminaría el 14 de junio.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create();
});

function vinculacionDeLaPrueba(Closure $fabrica): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, fn (): Vinculacion => $fabrica()->refresh());
}

function finDeLaPrueba(Vinculacion $vinculacion): ?string
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function () use ($vinculacion): ?string {
        $prueba = PeriodoDePrueba::de($vinculacion->fresh());

        return $prueba->aplica() ? $prueba->terminaEl()->toDateString() : null;
    });
}

it('un fijo que pasa a indefinido conserva la quinta parte de su término', function () {
    $contrato = vinculacionDeLaPrueba(fn () => Vinculacion::factory()->aTerminoFijo('2026-04-15', '2026-10-14')->create());

    app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $contrato->cambiarATerminoIndefinido(Carbon::parse('2026-05-01')));

    expect(finDeLaPrueba($contrato))->toBe('2026-05-20');
});

it('un fijo prorrogado conserva el periodo de su término inicial, no el del total', function () {
    $contrato = vinculacionDeLaPrueba(fn () => Vinculacion::factory()->aTerminoFijo('2026-04-15', '2026-05-14')->create());

    app(ContextoTenant::class)->paraTenant(
        $this->empresa,
        fn () => $contrato->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2026-11-14')),
    );

    // Treinta días pactados al inicio: seis de prueba, hasta el 20 de abril.
    expect(finDeLaPrueba($contrato))->toBe('2026-04-20');
});

it('una obra o labor con fecha estimada que pasa a indefinido conserva su periodo', function () {
    $contrato = vinculacionDeLaPrueba(fn () => Vinculacion::factory()
        ->deObraOLaborEstimadaHasta('2026-10-14')->conIngreso('2026-04-15')->create());

    app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $contrato->cambiarATerminoIndefinido(Carbon::parse('2026-05-01')));

    expect(finDeLaPrueba($contrato))->toBe('2026-05-20');
});

it('una obra o labor sin fecha estimada que pasa a indefinido sigue sin periodo de prueba', function () {
    $contrato = vinculacionDeLaPrueba(fn () => Vinculacion::factory()->deObraOLabor()->conIngreso('2026-04-15')->create());

    app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $contrato->cambiarATerminoIndefinido(Carbon::parse('2026-05-01')));

    expect(finDeLaPrueba($contrato))->toBeNull();
});

it('el indefinido de siempre conserva sus dos meses', function () {
    $contrato = vinculacionDeLaPrueba(fn () => Vinculacion::factory()->conIngreso('2026-04-15')->create());

    expect(finDeLaPrueba($contrato))->toBe('2026-06-14');
});

it('las alertas siguen la modalidad inicial: el fijo que pasó a indefinido no recibe las del indefinido', function () {
    $contrato = vinculacionDeLaPrueba(fn () => Vinculacion::factory()->aTerminoFijo('2026-04-15', '2026-10-14')->create());

    app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $contrato->cambiarATerminoIndefinido(Carbon::parse('2026-05-01')));

    $evaluar = fn ($regla, string $dia) => app(ContextoTenant::class)->paraTenant(
        $this->empresa,
        fn (): array => iterator_to_array($regla->evaluar(Carbon::parse($dia), Carbon::parse($dia)), false),
    );

    // La del fijo, dos días antes del 20 de mayo, sí sale.
    expect($evaluar(new PruebaFijoDosDias, '2026-05-18'))->toHaveCount(1)
        // La del indefinido, un mes cumplido el 14 de mayo, no: no hay periodo de dos meses.
        ->and($evaluar(new PruebaIndefinidoUnMes, '2026-05-14'))->toBe([]);
});
