<?php

use App\Novedades\PeriodoDigitado;
use Illuminate\Http\Request;

/**
 * La lectura de las dos fechas que se están digitando en el modal de una
 * novedad (`App\Novedades\PeriodoDigitado`).
 *
 * Estaba escrita tres veces —incapacidades, vacaciones y licencias— y ahora
 * vive una sola. Se prueba aquí en vez de en cada módulo justamente por eso:
 * las tres copias tenían que decidir igual, y una sola prueba es la forma de
 * garantizar que lo siguen haciendo.
 */
function periodoDigitado(string $consulta): ?array
{
    return PeriodoDigitado::de(Request::create('/novedades?'.$consulta));
}

it('devuelve las dos fechas cuando llegan completas y en orden', function () {
    $periodo = periodoDigitado('fecha_inicio=2026-07-14&fecha_fin=2026-07-16');

    expect($periodo)->toBeArray()
        ->and($periodo[0]->toDateString())->toBe('2026-07-14')
        ->and($periodo[1]->toDateString())->toBe('2026-07-16');
});

it('no devuelve nada mientras falte una de las dos fechas', function () {
    expect(periodoDigitado('fecha_inicio=2026-07-14'))->toBeNull()
        ->and(periodoDigitado('fecha_fin=2026-07-16'))->toBeNull()
        ->and(periodoDigitado('fecha_inicio=&fecha_fin='))->toBeNull();
});

it('no devuelve nada cuando la fecha de fin es anterior a la de inicio', function () {
    // No es un error que haya que reportar: el formulario se está llenando y la
    // validación de verdad la hace el `FormRequest` al guardar.
    expect(periodoDigitado('fecha_inicio=2026-07-16&fecha_fin=2026-07-14'))->toBeNull();
});

it('no revienta con lo que no es una fecha', function () {
    expect(periodoDigitado('fecha_inicio=ayer&fecha_fin=2026-07-16'))->toBeNull();
});

it('escribe los días corridos del periodo digitado, ya en palabras', function () {
    // El caso obligatorio del documento 1, 6.3: del 14 al 16 de julio son tres.
    $peticion = Request::create('/novedades?fecha_inicio=2026-07-14&fecha_fin=2026-07-16');

    expect(PeriodoDigitado::diasCorridos($peticion))->toBe('3 días');
});

it('no escribe ningún día mientras no haya periodo', function () {
    expect(PeriodoDigitado::diasCorridos(Request::create('/novedades')))->toBeNull();
});
