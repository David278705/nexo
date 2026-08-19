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
    'prórrogas cortas pactadas por escrito' => ['prorrogas_cortas_pactadas', 4],
    'prórrogas cortas automáticas por silencio' => ['prorrogas_cortas_automaticas', 3],
]);

it('sabe desde cuándo rige el régimen del término fijo', function () {
    // No es un número sino una fecha: se compara contra la fecha de inicio del
    // contrato para saber desde cuándo corren los cuatro años del tope
    // (documento 6, C.2).
    expect(config('reglas_laborales.vigencia_reforma_termino_fijo'))->toBe('2025-06-25');
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
