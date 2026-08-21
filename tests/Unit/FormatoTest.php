<?php

use App\Presentacion\Formato;
use Illuminate\Support\Carbon;

/**
 * Los textos compartidos de presentación.
 *
 * Se prueban aparte de las pantallas que los usan porque hasta la revisión de
 * F1-13 estaban copiados en cinco clases: cada pantalla comprobaba su copia y
 * ninguna comprobaba la regla. Ahora la regla es una y se prueba una vez.
 */
it('escribe la fecha como se lee en Colombia', function () {
    expect(Formato::fecha(Carbon::parse('2026-12-25')))->toBe('25/12/2026');
});

it('pone una raya donde no hay fecha', function () {
    expect(Formato::fecha(null))->toBe('—')
        ->and(Formato::fecha('2026-12-25'))->toBe('—')
        ->and(Formato::fecha(''))->toBe('—');
});

it('escribe la fecha larga en español, para meterla en una frase', function () {
    expect(Formato::fechaLarga(Carbon::parse('2026-12-25')))->toBe('25 de diciembre de 2026');
});

it('pone una raya donde no hay fecha larga', function () {
    expect(Formato::fechaLarga(null))->toBe('—');
});

it('escribe el día con su nombre delante y en mayúscula', function () {
    // El 25 de diciembre de 2026 es viernes. La mayúscula inicial es lo que la
    // distingue de una fecha metida en mitad de una frase.
    expect(Formato::diaCompleto(Carbon::parse('2026-12-25')))
        ->toBe('Viernes, 25 de diciembre de 2026');
});

it('pone una raya donde no hay día completo', function () {
    expect(Formato::diaCompleto(null))->toBe('—');
});

it('dice «1 día» en singular y «2 días» en plural', function () {
    expect(Formato::dias(1))->toBe('1 día')
        ->and(Formato::dias(0))->toBe('0 días')
        ->and(Formato::dias(2))->toBe('2 días')
        ->and(Formato::dias(1461))->toBe('1461 días');
});

it('escribe el dinero con punto de miles y sin centavos cuando no los hay', function () {
    expect(Formato::dinero(2_500_000))->toBe('$ 2.500.000')
        ->and(Formato::dinero('1423500.00'))->toBe('$ 1.423.500');
});

it('escribe los centavos cuando el valor los tiene', function () {
    expect(Formato::dinero('1423500.50'))->toBe('$ 1.423.500,50');
});

it('pone una raya donde no hay dinero, y no un cero', function () {
    // Un cero diría que la persona no gana nada, que es una afirmación
    // distinta de «no está registrado».
    expect(Formato::dinero(null))->toBe('—')
        ->and(Formato::dinero(''))->toBe('—');
});

it('escribe cero pesos cuando el valor sí es cero', function () {
    expect(Formato::dinero(0))->toBe('$ 0');
});
