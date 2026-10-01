<?php

use App\Models\Acceso;

/**
 * Lo que el sistema le dice al navegador para proteger a quien lo usa, y cómo
 * se entiende con el proxy de Railway que tiene delante.
 */
it('manda las cabeceras de seguridad en cada respuesta', function () {
    $this->get('/acceso')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('las manda también fuera de la pila web, como en la ruta de salud', function () {
    $this->get('/up')->assertHeader('X-Frame-Options', 'DENY');
});

it('exige HTTPS al navegador solo cuando la petición llegó por HTTPS', function () {
    $this->get('http://localhost/acceso')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/acceso')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('entiende que la petición es HTTPS cuando el proxy de Railway lo dice', function () {
    // Así llega todo en producción: por HTTP desde el borde de Railway, con la
    // cabecera que cuenta cómo entró el usuario.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://localhost/acceso')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('no deja falsificar la IP del cliente con un X-Forwarded-For inventado', function () {
    // El usuario manda «1.2.3.4» para hacerse pasar por otro; el proxy agrega
    // la IP real con la que lo vio llegar. La que cuenta es la del proxy: de
    // ella dependen el límite de intentos de acceso y su registro.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 203.0.113.9'])
        ->post('/acceso', ['correo' => 'nadie@empresa.test', 'contrasena' => 'equivocada']);

    expect(Acceso::query()->latest('id')->firstOrFail()->ip)->toBe('203.0.113.9');
});
