<?php

use App\Contratacion\EstadoDeValidacion;
use App\Contratacion\RenovacionDeContrato;

/**
 * Con cuánta fuerza actúa cada validación legal (documento 6, B.1 y B.3).
 *
 * Lo que aquí se defiende es que el estado y el color sean la misma decisión: el
 * rojo solo aparece cuando algo no se puede guardar, y algo que sí se puede
 * guardar no se pinta de rojo.
 */
it('el bloqueo impide guardar y se lee en rojo', function () {
    expect(EstadoDeValidacion::Bloquea->bloquea())->toBeTrue()
        ->and(EstadoDeValidacion::Bloquea->tono())->toBe('error');
});

it('la advertencia permite guardar y se lee en ámbar', function () {
    expect(EstadoDeValidacion::Advierte->bloquea())->toBeFalse()
        ->and(EstadoDeValidacion::Advierte->tono())->toBe('advertencia');
});

it('lee el estado configurado de cada validación', function (string $valor, EstadoDeValidacion $estado) {
    config(['validaciones.una_regla' => $valor]);

    expect(EstadoDeValidacion::configurada('una_regla'))->toBe($estado);
})->with([
    ['bloquea', EstadoDeValidacion::Bloquea],
    ['advierte', EstadoDeValidacion::Advierte],
]);

it('bloquea cuando la configuración no dice ninguno de los dos', function (mixed $valor) {
    // Ante una configuración equivocada, la opción segura es no registrar algo
    // que puede ser ilegal.
    config(['validaciones.una_regla' => $valor]);

    expect(EstadoDeValidacion::configurada('una_regla'))->toBe(EstadoDeValidacion::Bloquea);
})->with(['', 'avisa', null]);

it('bloquea el tope de cuatro años, donde la ley no deja alternativa', function () {
    expect(config('validaciones.'.RenovacionDeContrato::VALIDACION_TOPE))->toBe('bloquea');
});

it('solo advierte la prórroga mínima, mientras el asesor laboral la confirme', function () {
    expect(config('validaciones.'.RenovacionDeContrato::VALIDACION_PRORROGA_MINIMA))
        ->toBe('advierte');
});

it('no tiene validaciones configurables de más ni de menos', function () {
    expect(array_keys(config('validaciones')))->toBe([
        'renovacion_tope_termino_fijo',
        'renovacion_prorroga_minima',
    ]);
});
