<?php

use App\Autenticacion\SegundoFactor;
use App\Autorizacion\Rol;
use App\Autorizacion\SeccionDelPanel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * La base del panel del proveedor (documento 4, capítulos 1, 2 y 10).
 *
 * Tres cosas se comprueban aquí, y en este orden porque es el orden en que el
 * servidor las decide:
 *
 * 1. **Ningún usuario de empresa alcanza el panel.** Ni el administrador ni el
 *    superadministrador, que dentro de su empresa lo puede todo.
 * 2. **El segundo factor es obligatorio de verdad.** No es una pantalla que se
 *    pueda aplazar: sin él no hay panel.
 * 3. **El código se pide una vez por sesión** y los intentos se cuentan aparte
 *    de los de la contraseña.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; Pest comparte las funciones de
 * todos los archivos de prueba.
 */

/**
 * Las nueve direcciones del panel, la del inicio incluida.
 *
 * @return list<string>
 */
function direccionesDelPanel(): array
{
    return array_map(fn (SeccionDelPanel $seccion) => $seccion->url(), SeccionDelPanel::cases());
}

/**
 * Un `owner` con el segundo factor activo y su código de este momento.
 *
 * @return array{0: User, 1: string}
 */
function ownerVerificable(): array
{
    $owner = User::factory()->owner()->conSegundoFactor()->create();

    $codigo = app(SegundoFactor::class)->codigo((string) $owner->segundo_factor_secreto);

    return [$owner, $codigo];
}

it('devuelve 403 al superadministrador que intenta entrar al panel', function () {
    $empresa = Tenant::factory()->create();
    $superadministrador = usuarioDe($empresa, Rol::Superadministrador);

    foreach (direccionesDelPanel() as $direccion) {
        $this->actingAs($superadministrador)->get($direccion)->assertForbidden();
    }
});

it('devuelve 403 al administrador que intenta entrar al panel', function () {
    $empresa = Tenant::factory()->create();
    $administrador = usuarioDe($empresa, Rol::Administrador);

    foreach (direccionesDelPanel() as $direccion) {
        $this->actingAs($administrador)->get($direccion)->assertForbidden();
    }
});

it('no le enseña al usuario de empresa ni las pantallas del segundo factor', function () {
    $empresa = Tenant::factory()->create();
    $superadministrador = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($superadministrador)->get('/proveedor/segundo-factor')->assertForbidden();
    $this->actingAs($superadministrador)->get('/proveedor/verificacion')->assertForbidden();
    $this->actingAs($superadministrador)->post('/proveedor/verificacion', ['codigo' => '000000'])
        ->assertForbidden();
});

it('manda a la pantalla de acceso a quien no ha iniciado sesión', function () {
    $this->get('/proveedor')->assertRedirect('/acceso');
});

it('no comparte las secciones del panel con un usuario de empresa', function () {
    $empresa = Tenant::factory()->create();

    $this->actingAs(usuarioDe($empresa, Rol::Superadministrador))
        ->get('/')
        ->assertInertia(fn ($pagina) => $pagina->where('secciones', null));
});

it('desvía al owner sin segundo factor a activarlo, desde cualquier sección', function () {
    $owner = User::factory()->owner()->create();

    foreach (direccionesDelPanel() as $direccion) {
        $this->actingAs($owner)->get($direccion)->assertRedirect('/proveedor/segundo-factor');
    }
});

it('emite y guarda el secreto al enseñar la pantalla de activación', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get('/proveedor/segundo-factor')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Proveedor/ActivarSegundoFactor')
            ->has('clave')
            ->has('enlace'));

    $owner->refresh();

    expect($owner->segundo_factor_secreto)->not->toBeNull()
        // Emitido no es activo: hasta que no se confirme, sigue sin segundo
        // factor y sigue sin panel.
        ->and($owner->tieneSegundoFactor())->toBeFalse();
});

it('no cambia el secreto si la pantalla de activación se vuelve a abrir', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/proveedor/segundo-factor')->assertOk();
    $primero = $owner->refresh()->segundo_factor_secreto;

    $this->actingAs($owner)->get('/proveedor/segundo-factor')->assertOk();

    expect($owner->refresh()->segundo_factor_secreto)->toBe($primero);
});

it('guarda el secreto cifrado y no en claro', function () {
    $owner = User::factory()->owner()->conSegundoFactor()->create();

    $guardado = DB::table('users')->where('id', $owner->id)->value('segundo_factor_secreto');

    expect($guardado)->not->toBe($owner->segundo_factor_secreto)
        ->and($guardado)->not->toContain((string) $owner->segundo_factor_secreto);
});

it('activa el segundo factor con el primer código correcto y deja pasar al panel', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/proveedor/segundo-factor')->assertOk();

    $secreto = (string) $owner->refresh()->segundo_factor_secreto;
    $codigo = app(SegundoFactor::class)->codigo($secreto);

    $this->actingAs($owner)
        ->post('/proveedor/segundo-factor', ['codigo' => $codigo])
        ->assertRedirect('/proveedor');

    expect($owner->refresh()->tieneSegundoFactor())->toBeTrue();

    // La misma sesión ya no vuelve a pedir el código: quien acaba de demostrar
    // que tiene la aplicación no tiene que demostrarlo dos veces.
    $this->get('/proveedor')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Proveedor/Inicio'));
});

it('no activa el segundo factor con un código equivocado', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/proveedor/segundo-factor')->assertOk();

    $this->post('/proveedor/segundo-factor', ['codigo' => '000000'])
        ->assertSessionHasErrors('codigo');

    expect($owner->refresh()->tieneSegundoFactor())->toBeFalse();
});

it('pide el código en cada sesión nueva del owner que ya lo tiene activo', function () {
    [$owner] = ownerVerificable();

    foreach (direccionesDelPanel() as $direccion) {
        $this->actingAs($owner)->get($direccion)->assertRedirect('/proveedor/verificacion');
    }

    $this->actingAs($owner)
        ->get('/proveedor/verificacion')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Proveedor/VerificarSegundoFactor'));
});

it('abre el panel con el código correcto de la sesión', function () {
    [$owner, $codigo] = ownerVerificable();

    $this->actingAs($owner)
        ->post('/proveedor/verificacion', ['codigo' => $codigo])
        ->assertRedirect('/proveedor');

    $this->get('/proveedor')->assertOk();
    // Cualquier sección sirve para comprobar que el panel quedó abierto; se
    // toma una de las que todavía no tienen contenido para que esta prueba no
    // se rompa cada vez que una sección estrene el suyo.
    $this->get('/proveedor/empresas')->assertOk();
    $this->get('/proveedor/salud')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Proveedor/SeccionPendiente')
            ->where('titulo', 'Salud del sistema'));
});

it('no abre el panel con un código equivocado', function () {
    [$owner] = ownerVerificable();

    $this->actingAs($owner)
        ->post('/proveedor/verificacion', ['codigo' => '000000'])
        ->assertSessionHasErrors('codigo');

    $this->get('/proveedor')->assertRedirect('/proveedor/verificacion');
});

it('hace esperar tras varios códigos equivocados', function () {
    [$owner, $codigo] = ownerVerificable();

    for ($intento = 1; $intento <= 3; $intento++) {
        $this->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => '000000']);
    }

    // Con la espera encima, ni el código bueno entra: es lo que hace inútil
    // probar de a tres.
    $this->actingAs($owner)
        ->post('/proveedor/verificacion', ['codigo' => $codigo])
        ->assertSessionHasErrors('codigo');

    $this->get('/proveedor')->assertRedirect('/proveedor/verificacion');
});

it('manda al owner al panel y no al inicio del cliente cuando entra', function () {
    [$owner] = ownerVerificable();

    $this->post('/acceso', [
        'correo' => $owner->email,
        'contrasena' => 'password',
    ])->assertRedirect('/proveedor');
});

it('cuenta aparte los fallos del código y los de la contraseña', function () {
    [$owner, $codigo] = ownerVerificable();

    // Tres códigos fallidos dejan al segundo factor en espera, pero volver a
    // entrar con la contraseña no la borra: si la borrara, quien tuviera la
    // contraseña podría probar códigos sin límite.
    for ($intento = 1; $intento <= 3; $intento++) {
        $this->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => '000000']);
    }

    $this->post('/salir');

    $this->post('/acceso', [
        'correo' => $owner->email,
        'contrasena' => 'password',
    ])->assertRedirect('/proveedor');

    $this->post('/proveedor/verificacion', ['codigo' => $codigo])
        ->assertSessionHasErrors('codigo');
});

it('comparte con el menú del panel las nueve secciones con su grupo', function () {
    [$owner, $codigo] = ownerVerificable();

    $this->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => $codigo]);

    $this->get('/proveedor')->assertInertia(function ($pagina) {
        $secciones = $pagina->toArray()['props']['secciones'];

        expect($secciones)->toHaveCount(9)
            ->and($secciones[0])->toBe([
                'clave' => 'inicio',
                'etiqueta' => 'Inicio',
                'grupo' => null,
                'url' => '/proveedor',
            ])
            ->and($secciones[2])->toBe([
                'clave' => 'puesta-en-marcha',
                'etiqueta' => 'Puesta en marcha',
                'grupo' => 'Clientes',
                'url' => '/proveedor/puesta-en-marcha',
            ]);
    });
});

it('deja fuera del panel a quien cierra la sesión', function () {
    [$owner, $codigo] = ownerVerificable();

    $this->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => $codigo]);
    $this->get('/proveedor')->assertOk();

    $this->post('/salir');

    $this->get('/proveedor')->assertRedirect('/acceso');
});

it('vuelve a pedir el código en la sesión siguiente', function () {
    [$owner, $codigo] = ownerVerificable();

    $this->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => $codigo]);
    $this->post('/salir');

    $this->post('/acceso', [
        'correo' => $owner->email,
        'contrasena' => 'password',
    ]);

    $this->get('/proveedor')->assertRedirect('/proveedor/verificacion');
});
