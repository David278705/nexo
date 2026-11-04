<?php

use App\Http\Controllers\Acceso\AccesoDePruebaController;
use App\Http\Middleware\CerrarAccesoDePruebaNoVigente;
use App\Models\AccesoDePrueba;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
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

    // Lo que pide la planta de muestra para poblar una cuenta de prueba, igual
    // que en `DemostracionDelPanelTest`.
    Pais::query()->firstOrCreate(['codigo' => Pais::COLOMBIA], ['nombre' => 'Colombia', 'activo' => true]);
    Ciudad::factory()->create(['nombre' => 'Bogotá D.C.']);
    Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]);
    FondoPension::query()->create(['nombre' => 'Protección', 'activo' => true]);
    Arl::query()->create(['nombre' => 'ARL Sura', 'activo' => true]);
    CajaCompensacion::query()->create(['nombre' => 'Compensar', 'activo' => true]);
    Banco::query()->create(['nombre' => 'Bancolombia', 'activo' => true]);
});

/**
 * Cuántas personas tiene una empresa, contadas dentro de su contexto.
 */
function personasDe(Tenant $empresa): int
{
    return app(ContextoTenant::class)->paraTenant($empresa, fn (): int => Persona::query()->count());
}

it('genera un acceso y su enlace deja entrar a una cuenta de prueba propia, no a la demo compartida', function () {
    $token = app(AccesosDePrueba::class)->generar('Comercializadora Prospecto S.A.S.', 7);

    $respuesta = $this->get('/prueba/'.$token);

    $respuesta->assertRedirect(route('inicio'));

    $cuenta = Auth::user()->tenant;

    expect(Auth::check())->toBeTrue()
        ->and($cuenta->getKey())->not->toBe($this->demo->getKey())
        ->and($cuenta->es_cuenta_de_prueba)->toBeTrue()
        ->and($cuenta->nombre)->toBe('Demostración · Comercializadora Prospecto S.A.S.');
});

describe('la cuenta de prueba propia (PROD-17)', function () {
    it('llega con la planta de muestra ya cargada', function () {
        app(AccesosDePrueba::class)->generar('Prospecto con datos', 7);

        $cuenta = Tenant::query()->where('es_cuenta_de_prueba', true)->sole();

        expect(personasDe($cuenta))->toBeGreaterThan(0);
    });

    it('cada prospecto tiene la suya: dos accesos son dos empresas distintas', function () {
        app(AccesosDePrueba::class)->generar('Primer prospecto', 7);
        app(AccesosDePrueba::class)->generar('Segundo prospecto', 7);

        $cuentas = Tenant::query()->where('es_cuenta_de_prueba', true)->get();

        expect($cuentas)->toHaveCount(2)
            ->and(AccesoDePrueba::query()->pluck('tenant_id')->unique())->toHaveCount(2);
    });

    it('lo que cambia un prospecto no lo ve el otro', function () {
        app(AccesosDePrueba::class)->generar('Primer prospecto', 7);
        app(AccesosDePrueba::class)->generar('Segundo prospecto', 7);
        [$primera, $segunda] = Tenant::query()->where('es_cuenta_de_prueba', true)->orderBy('id')->get()->all();
        $antes = personasDe($segunda);

        app(ContextoTenant::class)->paraTenant($primera, fn () => Persona::factory()->create());

        expect(personasDe($primera))->toBe($antes + 1)
            ->and(personasDe($segunda))->toBe($antes);
    });

    it('no toca la demostración compartida', function () {
        $antes = personasDe($this->demo);

        app(AccesosDePrueba::class)->generar('Prospecto', 7);

        expect(personasDe($this->demo))->toBe($antes);
    });

    it('es ficticia: no recibe alertas ni exige aceptar los documentos legales', function () {
        $token = app(AccesosDePrueba::class)->generar('Prospecto', 7);
        $cuenta = Tenant::query()->where('es_cuenta_de_prueba', true)->sole();

        expect($cuenta->esFicticia())->toBeTrue()
            ->and($cuenta->estado->value)->toBe(Tenant::ESTADO_EN_PRUEBA);

        $this->get('/prueba/'.$token);
        $this->get('/')->assertOk();
    });

    it('no cuenta como cliente', function () {
        app(AccesosDePrueba::class)->generar('Prospecto', 7);

        expect(Tenant::query()->deClientes()->where('es_cuenta_de_prueba', true)->count())->toBe(0);
    });

    it('un acceso antiguo que apunta a la demostración compartida sigue entrando a ella', function () {
        $token = Str::random(64);
        AccesoDePrueba::factory()->for($this->demo, 'tenant')->create([
            'token' => hash('sha256', $token),
        ]);

        $this->get('/prueba/'.$token)->assertRedirect(route('inicio'));

        expect(Auth::user()->tenant_id)->toBe($this->demo->getKey());
    });
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
