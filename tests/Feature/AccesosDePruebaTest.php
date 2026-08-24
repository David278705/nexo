<?php

use App\Http\Controllers\Acceso\AccesoDePruebaController;
use App\Http\Middleware\CerrarAccesoDePruebaNoVigente;
use App\Models\AccesoDePrueba;
use App\Models\Tenant;
use App\Models\User;
use App\Proveedor\AccesosDePrueba;
use App\Proveedor\AmbienteDeDemostracion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Los accesos de prueba con vigencia limitada (F5-03, documento 4, capítulo
 * 10 y 12.1): «se le entrega un acceso de prueba con vigencia limitada para
 * que lo explore solo. David genera el acceso».
 *
 * Lo que se comprueba: el enlace deja entrar mientras es vigente, deja de
 * servir en cuanto vence o se revoca, y una visita ya abierta se cierra sola en
 * los dos casos —la vigencia no se comprueba solo al entrar—.
 */
beforeEach(function () {
    $this->demo = Tenant::factory()->ambienteDeDemo()->create();
});

it('genera un acceso y su enlace deja entrar al ambiente de demostración', function () {
    $token = app(AccesosDePrueba::class)->generar('Comercializadora Prospecto S.A.S.', 7);

    $respuesta = $this->get('/prueba/'.$token);

    $respuesta->assertRedirect(route('inicio'));
    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->tenant_id)->toBe($this->demo->getKey());
});

it('un acceso se puede volver a usar mientras siga vigente', function () {
    $token = app(AccesosDePrueba::class)->generar('Prospecto que vuelve', 7);

    $this->get('/prueba/'.$token)->assertRedirect(route('inicio'));
    Auth::logout();
    $this->get('/prueba/'.$token)->assertRedirect(route('inicio'));

    expect(Auth::check())->toBeTrue();
});

it('un acceso vencido no deja entrar', function () {
    $acceso = AccesoDePrueba::factory()->for($this->demo, 'tenant')->vencido()->create();
    $token = tokenDePrueba($acceso);

    $respuesta = $this->get('/prueba/'.$token);

    $respuesta->assertRedirect(route('acceso'));
    $respuesta->assertSessionHas('estado', AccesoDePruebaController::MENSAJE_NO_VIGENTE);
    expect(Auth::check())->toBeFalse();
});

it('un acceso revocado no deja entrar aunque no haya vencido', function () {
    $acceso = AccesoDePrueba::factory()->for($this->demo, 'tenant')->revocado()->create();
    $token = tokenDePrueba($acceso);

    $respuesta = $this->get('/prueba/'.$token);

    $respuesta->assertRedirect(route('acceso'));
    expect(Auth::check())->toBeFalse();
});

it('un token que no corresponde a ningún acceso no deja entrar', function () {
    $respuesta = $this->get('/prueba/'.str_repeat('a', 64));

    $respuesta->assertRedirect(route('acceso'));
    expect(Auth::check())->toBeFalse();
});

it('cierra la sesión de una visita ya abierta cuando el acceso vence a la mitad', function () {
    $acceso = AccesoDePrueba::factory()->for($this->demo, 'tenant')->vencido()->create();

    $respuesta = visitaAbiertaCon($acceso)->get('/');

    $respuesta->assertRedirect(route('acceso'));
    $respuesta->assertSessionHas('estado', CerrarAccesoDePruebaNoVigente::VENCIDO);
    expect(Auth::check())->toBeFalse();
});

/*
 * La otra mitad de la revocación. Cortar el enlace no sirve de nada si el
 * prospecto que ya está dentro sigue navegando: el panel diría «revocado»
 * mientras él mira el ambiente de demostración.
 */
it('cierra la sesión de una visita ya abierta en cuanto se revoca el acceso', function () {
    $acceso = AccesoDePrueba::factory()->for($this->demo, 'tenant')->create();

    visitaAbiertaCon($acceso)->get('/')->assertOk();

    app(AccesosDePrueba::class)->revocar($acceso);

    $respuesta = visitaAbiertaCon($acceso)->get('/');

    $respuesta->assertRedirect(route('acceso'));
    $respuesta->assertSessionHas('estado', CerrarAccesoDePruebaNoVigente::REVOCADO);
    expect(Auth::check())->toBeFalse();
});

it('no cierra la sesión de un usuario normal, que nunca trae esa marca', function () {
    $empresa = Tenant::factory()->create();
    $usuario = User::factory()->superadministrador()->create(['tenant_id' => $empresa->getKey()]);

    $respuesta = $this->actingAs($usuario)->get('/');

    $respuesta->assertOk();
});

it('revocar corta el acceso antes de su vencimiento', function () {
    $acceso = AccesoDePrueba::factory()->for($this->demo, 'tenant')->create();
    $token = tokenDePrueba($acceso);

    app(AccesosDePrueba::class)->revocar($acceso);

    $respuesta = $this->get('/prueba/'.$token);

    $respuesta->assertRedirect(route('acceso'));
});

/**
 * Una visita ya abierta con ese acceso: la sesión trae su marca y el usuario
 * autenticado es el que comparten todos los accesos de prueba, que es
 * exactamente lo que deja `AccesoDePruebaController::entrar()`.
 */
function visitaAbiertaCon(AccesoDePrueba $acceso): TestCase
{
    return test()
        ->withSession([CerrarAccesoDePruebaNoVigente::CLAVE => (int) $acceso->getKey()])
        ->actingAs(app(AmbienteDeDemostracion::class)->usuarioDeDemostracion());
}

/**
 * El token en claro no se puede reconstruir desde el modelo —solo se guarda
 * su hash—, así que las pruebas que arman un `AccesoDePrueba` con la fábrica
 * necesitan su propio token de vuelta. Se genera igual que en producción y se
 * fuerza el mismo hash en la fila ya creada.
 */
function tokenDePrueba(AccesoDePrueba $acceso): string
{
    $token = Str::random(64);

    $acceso->forceFill(['token' => hash('sha256', $token)])->save();

    return $token;
}
