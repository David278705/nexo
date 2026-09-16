<?php

it('tiene el archivo de constantes legales cargado', function () {
    expect(config('reglas_laborales'))->toBeArray();
});

it('lee cada constante legal con su valor vigente', function (string $constante, int|float $valor) {
    expect(config("reglas_laborales.{$constante}"))->toBe($valor);
})->with([
    'acumulación de vacaciones por mes' => ['dias_vacaciones_por_mes', 1.25],
    'periodo de prueba en indefinido' => ['periodo_prueba_indefinido_meses', 2],
    'divisor del periodo de prueba en fijo' => ['periodo_prueba_fijo_divisor', 5],
    'tope del periodo de prueba en fijo' => ['periodo_prueba_fijo_tope_meses', 2],
    'preaviso de no prórroga' => ['preaviso_dias', 30],
    'tope del contrato a término fijo' => ['tope_termino_fijo_anios', 4],
    'disfrute mínimo anual de vacaciones' => ['disfrute_minimo_dias_habiles', 6],
    // Una sola cuenta para toda la cadena de prórrogas, sean pactadas o
    // automáticas: es la corrección de C.1 que hizo Angela en agosto de 2026.
    'prórrogas inferiores a un año' => ['prorrogas_cortas', 3],
]);

it('cuenta las prórrogas cortas una sola vez, sin separar por tipo', function () {
    // La versión anterior tenía dos constantes —cuatro pactadas y tres
    // automáticas— y un contrato que mezclaba tipos acumulaba más prórrogas
    // cortas de las que la ley admite. Que las claves viejas no existan es lo
    // que impide que alguien vuelva a leerlas sin darse cuenta.
    expect(config('reglas_laborales.prorrogas_cortas_pactadas'))->toBeNull()
        ->and(config('reglas_laborales.prorrogas_cortas_automaticas'))->toBeNull();
});

it('sabe desde cuándo rige el régimen del término fijo', function () {
    // No es un número sino una fecha: se compara contra la fecha de inicio del
    // contrato para saber desde cuándo corren los cuatro años del tope
    // (documento 6, C.2).
    expect(config('reglas_laborales.vigencia_reforma_termino_fijo'))->toBe('2025-06-25');
});

it('sabe que la incapacidad interrumpe el disfrute de vacaciones, aunque le falte la norma', function () {
    // HD-04, decisión D.7: el comportamiento ya está decidido y no espera la
    // cita legal, que sigue siendo pregunta para Angela.
    expect(config('reglas_laborales.incapacidad_interrumpe_vacaciones'))->toBeTrue();
});

it('no tiene constantes legales de más ni de menos', function () {
    expect(array_keys(config('reglas_laborales')))->toHaveCount(10);
});

it('opera en la zona horaria de Colombia', function () {
    expect(config('app.timezone'))->toBe('America/Bogota')
        ->and(date_default_timezone_get())->toBe('America/Bogota');
});

it('está configurado en español', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('es');
});

it('traduce los mensajes de validación al español', function () {
    expect(__('validation.required', ['attribute' => 'documento']))
        ->not->toBe('validation.required');
});
