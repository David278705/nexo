<?php

/**
 * Monitoreo de errores en producción (F6-07, documento 6, A.5): Sentry
 * conectado al canal de logging para que una excepción no capturada llegue
 * con su traza, no solo al archivo de log.
 *
 * Sin `SENTRY_LARAVEL_DSN` el SDK queda inactivo —así corren local y
 * pruebas—, así que lo que hay que comprobar es que el cableado existe, no
 * que un evento salga de verdad hacia Sentry.
 */
it('declara el canal de log de sentry con el nivel que sí importa en producción', function () {
    expect(config('logging.channels.sentry.driver'))->toBe('sentry')
        ->and(config('logging.channels.sentry.level'))->toBe('error');
});

it('lee el DSN de sentry desde SENTRY_LARAVEL_DSN', function () {
    expect(config('sentry.dsn'))->toBe(env('SENTRY_LARAVEL_DSN'));
});

it('arranca la aplicación sin DSN configurado, sin romper nada', function () {
    expect(config('sentry.dsn'))->toBeNull();

    $respuesta = test()->get('/up');

    $respuesta->assertOk();
});
