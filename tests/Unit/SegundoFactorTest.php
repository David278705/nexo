<?php

use App\Autenticacion\SegundoFactor;
use Illuminate\Support\Carbon;

/**
 * El algoritmo TOTP está escrito en el proyecto y no traído de una dependencia,
 * así que la garantía de que está bien son los vectores de prueba del propio
 * RFC 6238, apéndice B.
 *
 * El secreto de esos vectores es el texto `12345678901234567890`, que en base32
 * se escribe así. Los códigos esperados son los del RFC recortados a seis
 * dígitos, que es la longitud que usan las aplicaciones.
 */
const SECRETO_DEL_RFC = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

function segundoFactor(): SegundoFactor
{
    return new SegundoFactor;
}

it('produce los códigos de los vectores de prueba del RFC 6238', function (int $instante, string $esperado) {
    expect(segundoFactor()->codigo(SECRETO_DEL_RFC, $instante))->toBe($esperado);
})->with([
    'el primer vector' => [59, '287082'],
    'antes del salto de periodo' => [1111111109, '081804'],
    'después del salto de periodo' => [1111111111, '050471'],
    'un instante cualquiera' => [1234567890, '005924'],
    'dos mil millones' => [2000000000, '279037'],
    'más allá de los 32 bits' => [20000000000, '353130'],
]);

it('acepta el código del momento', function () {
    Carbon::setTestNow(Carbon::createFromTimestamp(1111111111));

    expect(segundoFactor()->verificar(SECRETO_DEL_RFC, '050471'))->toBeTrue();
});

it('acepta el código anterior y el siguiente, y no el de más allá', function () {
    // 1111111111 cae en el periodo cuyo código es 050471; el anterior es el de
    // 1111111109 y el siguiente el de 1111111141.
    $reloj = 1111111111;

    expect(segundoFactor()->verificar(SECRETO_DEL_RFC, '081804', $reloj))->toBeTrue()
        ->and(segundoFactor()->verificar(SECRETO_DEL_RFC, '050471', $reloj))->toBeTrue()
        ->and(segundoFactor()->verificar(
            SECRETO_DEL_RFC,
            segundoFactor()->codigo(SECRETO_DEL_RFC, $reloj + 30),
            $reloj,
        ))->toBeTrue()
        ->and(segundoFactor()->verificar(
            SECRETO_DEL_RFC,
            segundoFactor()->codigo(SECRETO_DEL_RFC, $reloj + 90),
            $reloj,
        ))->toBeFalse();
});

it('acepta el código escrito con el espacio del medio', function () {
    expect(segundoFactor()->verificar(SECRETO_DEL_RFC, '050 471', 1111111111))->toBeTrue();
});

it('rechaza lo que no es un código de seis dígitos', function (string $escrito) {
    expect(segundoFactor()->verificar(SECRETO_DEL_RFC, $escrito, 1111111111))->toBeFalse();
})->with([
    'vacío' => [''],
    'corto' => ['5047'],
    'largo' => ['0504711'],
    'letras' => ['abcdef'],
    'otro código' => ['123456'],
]);

it('rechaza el código correcto de otro secreto', function () {
    $otro = segundoFactor()->secretoNuevo();

    expect(segundoFactor()->verificar($otro, '050471', 1111111111))->toBeFalse();
});

it('emite secretos de treinta y dos caracteres del alfabeto base32, y nunca el mismo', function () {
    $primero = segundoFactor()->secretoNuevo();
    $segundo = segundoFactor()->secretoNuevo();

    expect($primero)->toHaveLength(32)
        ->and($primero)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($segundo)->not->toBe($primero);
});

it('escribe el enlace que leen las aplicaciones de códigos', function () {
    $enlace = segundoFactor()->enlaceDeAplicacion(
        'david@nexo.co',
        SECRETO_DEL_RFC,
        'Gestión de Talento Humano',
    );

    expect($enlace)->toStartWith('otpauth://totp/')
        ->and($enlace)->toContain('secret='.SECRETO_DEL_RFC)
        ->and($enlace)->toContain('digits=6')
        ->and($enlace)->toContain('period=30')
        // El correo va en la etiqueta y el emisor las dos veces que pide el
        // formato: en la etiqueta y como parámetro.
        ->and($enlace)->toContain(rawurlencode('david@nexo.co'))
        ->and(substr_count($enlace, rawurlencode('Gestión de Talento Humano')))->toBe(1)
        ->and($enlace)->toContain('issuer=Gesti');
});

it('parte el secreto en grupos de cuatro para copiarlo a mano', function () {
    expect(segundoFactor()->secretoParaEscribirAMano(SECRETO_DEL_RFC))
        ->toBe('GEZD GNBV GY3T QOJQ GEZD GNBV GY3T QOJQ');
});
