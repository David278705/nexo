<?php

use App\Autenticacion\LimitadorDeIntentos;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/**
 * La espera creciente del documento de diseño, sección 5.1.
 *
 * Se prueba contra una caché de memoria, sin base de datos ni peticiones: lo
 * que se comprueba es la aritmética de la espera, no el formulario.
 */
function limitador(): LimitadorDeIntentos
{
    return new LimitadorDeIntentos(new Repository(new ArrayStore));
}

it('no hace esperar en los dos primeros fallos', function () {
    $limitador = limitador();

    expect($limitador->registrarFallo('ana@empresa.test', '10.0.0.1'))->toBe(0)
        ->and($limitador->registrarFallo('ana@empresa.test', '10.0.0.1'))->toBe(0)
        ->and($limitador->estaBloqueado('ana@empresa.test', '10.0.0.1'))->toBeFalse();
});

it('hace esperar un minuto en el tercer fallo', function () {
    $limitador = limitador();

    $limitador->registrarFallo('ana@empresa.test', '10.0.0.1');
    $limitador->registrarFallo('ana@empresa.test', '10.0.0.1');

    expect($limitador->registrarFallo('ana@empresa.test', '10.0.0.1'))->toBe(60)
        ->and($limitador->estaBloqueado('ana@empresa.test', '10.0.0.1'))->toBeTrue();
});

it('duplica la espera con cada fallo nuevo hasta el tope de quince minutos', function () {
    $limitador = limitador();

    $esperas = [];

    foreach (range(1, 8) as $intento) {
        $esperas[] = $limitador->registrarFallo('ana@empresa.test', '10.0.0.1');
    }

    expect($esperas)->toBe([0, 0, 60, 120, 240, 480, 900, 900]);
});

it('cuenta por correo y dirección IP juntos, no por uno solo', function () {
    $limitador = limitador();

    foreach (range(1, 3) as $intento) {
        $limitador->registrarFallo('ana@empresa.test', '10.0.0.1');
    }

    expect($limitador->estaBloqueado('ana@empresa.test', '10.0.0.1'))->toBeTrue()
        // La misma persona desde otra red no arrastra el bloqueo…
        ->and($limitador->estaBloqueado('ana@empresa.test', '10.0.0.2'))->toBeFalse()
        // …ni un compañero suyo desde la misma oficina.
        ->and($limitador->estaBloqueado('luis@empresa.test', '10.0.0.1'))->toBeFalse();
});

it('olvida los fallos cuando por fin se entra', function () {
    $limitador = limitador();

    foreach (range(1, 4) as $intento) {
        $limitador->registrarFallo('ana@empresa.test', '10.0.0.1');
    }

    $limitador->limpiar('ana@empresa.test', '10.0.0.1');

    expect($limitador->estaBloqueado('ana@empresa.test', '10.0.0.1'))->toBeFalse()
        ->and($limitador->registrarFallo('ana@empresa.test', '10.0.0.1'))->toBe(0);
});

it('no distingue mayúsculas en el correo', function () {
    $limitador = limitador();

    foreach (range(1, 3) as $intento) {
        $limitador->registrarFallo('Ana@Empresa.test', '10.0.0.1');
    }

    expect($limitador->estaBloqueado('ana@empresa.test', '10.0.0.1'))->toBeTrue();
});
