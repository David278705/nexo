<?php

use App\Autenticacion\Invitaciones;
use App\Autenticacion\ResultadoDeAcceso;
use App\Autorizacion\Modulo;
use App\Models\Acceso;
use App\Models\Invitacion;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\InvitacionAlSistema;
use App\Notificaciones\RestablecerContrasena;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Los cuatro flujos de acceso, el bloqueo por intentos y el registro.
 *
 * La contraseña de la factory es siempre `password`.
 */
const CONTRASENA = 'password';

const CONTRASENA_NUEVA = 'Contrasena9nueva';

function usuarioDeEmpresa(?Tenant $empresa = null, ?string $correo = null): User
{
    $empresa ??= Tenant::factory()->create();

    return User::factory()->create([
        'tenant_id' => $empresa->id,
        'email' => $correo ?? 'ana@empresa.test',
    ]);
}

/**
 * Un usuario con módulos asignados. La asignación va dentro de `paraTenant()`
 * porque `user_modulos` es tabla de negocio y su `tenant_id` lo pone el trait.
 */
function usuarioConModulos(Tenant $empresa, Modulo ...$modulos): User
{
    return app(ContextoTenant::class)->paraTenant($empresa, function () use ($empresa, $modulos) {
        $usuario = User::factory()->create(['tenant_id' => $empresa->id]);

        foreach ($modulos as $modulo) {
            $usuario->modulos()->create(['modulo' => $modulo->value]);
        }

        return $usuario;
    });
}

it('manda a la pantalla de acceso a quien no ha iniciado sesión', function () {
    $this->get('/')->assertRedirect('/acceso');
});

it('muestra la pantalla de iniciar sesión', function () {
    $this->get('/acceso')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Acceso/IniciarSesion'));
});

it('entra con las credenciales correctas', function () {
    $usuario = usuarioDeEmpresa();

    $this->post('/acceso', [
        'correo' => $usuario->email,
        'contrasena' => CONTRASENA,
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($usuario);
});

it('responde lo mismo ante un correo inexistente que ante una contraseña equivocada', function () {
    $usuario = usuarioDeEmpresa();

    $mensaje = ['correo' => 'Correo o contraseña incorrectos.'];

    $this->post('/acceso', [
        'correo' => 'nadie@empresa.test',
        'contrasena' => CONTRASENA,
    ])->assertSessionHasErrors($mensaje);

    $this->post('/acceso', [
        'correo' => $usuario->email,
        'contrasena' => 'la-que-no-es',
    ])->assertSessionHasErrors($mensaje);

    $this->assertGuest();
});

it('registra el acceso exitoso con su empresa y su dirección IP', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDeEmpresa($empresa);

    $this->post('/acceso', [
        'correo' => $usuario->email,
        'contrasena' => CONTRASENA,
    ]);

    $acceso = Acceso::query()->firstOrFail();

    expect($acceso->resultado)->toBe(ResultadoDeAcceso::Exitoso)
        ->and($acceso->user_id)->toBe($usuario->id)
        ->and($acceso->tenant_id)->toBe($empresa->id)
        ->and($acceso->ip)->not->toBeNull();
});

it('registra también los intentos fallidos, exista o no el correo', function () {
    $usuario = usuarioDeEmpresa();

    $this->post('/acceso', ['correo' => $usuario->email, 'contrasena' => 'mala']);
    $this->post('/acceso', ['correo' => 'fantasma@empresa.test', 'contrasena' => 'mala']);

    $fallidos = Acceso::query()->where('resultado', ResultadoDeAcceso::Fallido->value)->get();

    expect($fallidos)->toHaveCount(2)
        ->and($fallidos->firstWhere('correo', 'fantasma@empresa.test')->user_id)->toBeNull();
});

it('hace esperar tras tres intentos fallidos aunque la contraseña sea la correcta', function () {
    $usuario = usuarioDeEmpresa();

    foreach (range(1, 3) as $intento) {
        $this->post('/acceso', ['correo' => $usuario->email, 'contrasena' => 'mala']);
    }

    $this->post('/acceso', ['correo' => $usuario->email, 'contrasena' => CONTRASENA]);

    $this->assertGuest();

    expect(session('errors')->getBag('default')->first('correo'))
        ->toContain('Demasiados intentos fallidos')
        ->and(Acceso::query()->where('resultado', ResultadoDeAcceso::Bloqueado->value)->count())
        ->toBe(1);
});

it('cierra la sesión', function () {
    $usuario = usuarioDeEmpresa();

    $this->actingAs($usuario)
        ->post('/salir')
        ->assertRedirect('/acceso');

    $this->assertGuest();
});

it('responde con el mensaje neutro y sin enviar nada cuando el correo no existe', function () {
    Notification::fake();

    $this->post('/acceso/recuperar', ['correo' => 'nadie@empresa.test'])
        ->assertSessionHas('estado', 'Si el correo está registrado, recibirás instrucciones.');

    Notification::assertNothingSent();
});

it('envía el enlace de recuperación con el mismo mensaje neutro cuando el correo sí existe', function () {
    Notification::fake();

    $usuario = usuarioDeEmpresa();

    $this->post('/acceso/recuperar', ['correo' => $usuario->email])
        ->assertSessionHas('estado', 'Si el correo está registrado, recibirás instrucciones.');

    Notification::assertSentTo($usuario, RestablecerContrasena::class);
});

it('restablece la contraseña y deja la sesión iniciada', function () {
    $usuario = usuarioDeEmpresa();
    $token = Password::createToken($usuario);

    $this->post('/acceso/restablecer', [
        'token' => $token,
        'correo' => $usuario->email,
        'contrasena' => CONTRASENA_NUEVA,
        'contrasena_confirmation' => CONTRASENA_NUEVA,
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($usuario);

    expect(Hash::check(CONTRASENA_NUEVA, $usuario->fresh()->password))->toBeTrue();
});

it('no restablece con un enlace vencido', function () {
    $usuario = usuarioDeEmpresa();
    $token = Password::createToken($usuario);

    // El enlace vale una hora, según la sección 5.1 del documento de diseño.
    $this->travel(61)->minutes();

    $this->post('/acceso/restablecer', [
        'token' => $token,
        'correo' => $usuario->email,
        'contrasena' => CONTRASENA_NUEVA,
        'contrasena_confirmation' => CONTRASENA_NUEVA,
    ])->assertSessionHasErrors('correo');

    $this->assertGuest();

    expect(Hash::check(CONTRASENA_NUEVA, $usuario->fresh()->password))->toBeFalse();
});

it('muestra en la invitación el resumen de los módulos del invitado', function () {
    Notification::fake();

    $empresa = Tenant::factory()->create(['nombre' => 'Panadería del Centro']);
    $invitado = usuarioConModulos($empresa, Modulo::Vacaciones);

    $token = tokenDeInvitacion($invitado);

    $this->get("/invitacion/{$token}")
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Acceso/AceptarInvitacion')
            ->where('empresa', 'Panadería del Centro')
            ->where('modulos', ['Inicio', 'Vacaciones'])
            ->etc()
        );
});

it('acepta la invitación, define la contraseña y entra', function () {
    Notification::fake();

    $invitado = usuarioDeEmpresa();
    $token = tokenDeInvitacion($invitado);

    $this->post("/invitacion/{$token}", [
        'contrasena' => CONTRASENA_NUEVA,
        'contrasena_confirmation' => CONTRASENA_NUEVA,
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($invitado);

    expect(Hash::check(CONTRASENA_NUEVA, $invitado->fresh()->password))->toBeTrue()
        ->and(Invitacion::query()->firstOrFail()->aceptada_en)->not->toBeNull()
        ->and(Acceso::query()->where('resultado', ResultadoDeAcceso::Exitoso->value)->count())->toBe(1);
});

it('manda el correo de invitación al emitirla', function () {
    Notification::fake();

    $invitado = usuarioDeEmpresa();

    app(Invitaciones::class)->emitir($invitado);

    Notification::assertSentTo($invitado, InvitacionAlSistema::class);
});

it('no deja usar dos veces la misma invitación', function () {
    Notification::fake();

    $invitado = usuarioDeEmpresa();
    $token = tokenDeInvitacion($invitado);

    $this->post("/invitacion/{$token}", [
        'contrasena' => CONTRASENA_NUEVA,
        'contrasena_confirmation' => CONTRASENA_NUEVA,
    ]);

    $this->post('/salir');

    $this->post("/invitacion/{$token}", [
        'contrasena' => 'OtraCosa9distinta',
        'contrasena_confirmation' => 'OtraCosa9distinta',
    ])->assertRedirect('/acceso');

    expect(Hash::check(CONTRASENA_NUEVA, $invitado->fresh()->password))->toBeTrue();
});

it('rechaza una contraseña corta al aceptar la invitación', function () {
    Notification::fake();

    $invitado = usuarioDeEmpresa();
    $token = tokenDeInvitacion($invitado);

    $this->post("/invitacion/{$token}", [
        'contrasena' => 'corta1',
        'contrasena_confirmation' => 'corta1',
    ])->assertSessionHasErrors('contrasena');

    $this->assertGuest();
});

it('deja exactamente una línea de registro por intento', function () {
    $usuario = usuarioDeEmpresa();

    $this->post('/acceso', ['correo' => $usuario->email, 'contrasena' => 'mala']);
    $this->post('/acceso', ['correo' => $usuario->email, 'contrasena' => CONTRASENA]);

    expect(Acceso::query()->count())->toBe(2);
});

/**
 * Emite una invitación y devuelve el token en claro, que es lo único que no
 * queda guardado: en la tabla vive su hash.
 */
function tokenDeInvitacion(User $invitado): string
{
    return app(Invitaciones::class)->emitir($invitado);
}
