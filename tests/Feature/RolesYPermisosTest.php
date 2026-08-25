<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\PoliticaDeModulos;
use App\Autorizacion\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Facades\Route;

/**
 * Roles, módulos y el 403 del servidor.
 *
 * Lo que se prueba aquí no es que el menú esconda una opción —eso es diseño—,
 * sino que pedir la dirección a mano tampoco sirva. Por eso cada caso hace una
 * petición HTTP real a una ruta protegida con el middleware `modulo:`.
 */

/**
 * Crea un usuario de la empresa indicada, con su rol y sus módulos.
 *
 * La asignación va dentro de `paraTenant()` porque `user_modulos` es tabla de
 * negocio: su `tenant_id` lo pone el trait desde el contexto, nunca a mano.
 */
function usuarioDe(Tenant $empresa, Rol $rol, Modulo ...$modulos): User
{
    return app(ContextoTenant::class)->paraTenant($empresa, function () use ($empresa, $rol, $modulos) {
        $usuario = User::factory()->create([
            'tenant_id' => $empresa->id,
            'rol' => $rol->value,
        ]);

        foreach ($modulos as $modulo) {
            $usuario->modulos()->create(['modulo' => $modulo->value]);
        }

        return $usuario;
    });
}

/**
 * Registra una ruta de prueba protegida por el middleware del módulo y
 * devuelve su dirección.
 *
 * Es de juguete en el contenido, no en el camino: pasa por el grupo `web` y
 * por el mismo middleware que llevarán las rutas reales cuando F0-10 monte la
 * estructura de la aplicación.
 */
function rutaDelModulo(Modulo $modulo): string
{
    $direccion = "/pruebas/{$modulo->value}";

    Route::middleware(['web', "modulo:{$modulo->value}"])
        ->get($direccion, fn () => response("contenido de {$modulo->value}"));

    return $direccion;
}

it('devuelve 403 al pedir directamente la ruta de un módulo no asignado', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Contratos);

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Vacaciones))
        ->assertForbidden();
});

it('deja entrar al administrador al módulo que sí tiene asignado', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Vacaciones);

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Vacaciones))
        ->assertOk()
        ->assertSee('contenido de vacaciones');
});

it('deja entrar al superadministrador a un módulo sin asignárselo', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Terminaciones))
        ->assertOk();
});

it('deja entrar a inicio a cualquier usuario de la empresa', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador);

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Inicio))
        ->assertOk();
});

it('reserva configuración al superadministrador aunque el módulo esté asignado', function () {
    // Se le asigna a la fuerza, que es lo que podría colarse por una carga o
    // por un formulario mal validado. El rol manda por encima de la tabla.
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Configuracion);

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Configuracion))
        ->assertForbidden();
});

it('deja entrar al superadministrador a configuración', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Configuracion))
        ->assertOk();
});

it('no deja al owner entrar a los módulos del entorno del cliente', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(rutaDelModulo(Modulo::Empleados))
        ->assertForbidden();
});

it('no deja al owner entrar al panel de inicio del cliente', function () {
    // Inicio no lleva middleware de módulo —es de todo usuario de una
    // empresa—, así que es la única ruta que `PoliticaDeModulos` no protege
    // sola. Sin `SoloCliente`, el owner vería una pantalla vacía en vez de
    // un 403.
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('inicio'))
        ->assertForbidden();
});

it('devuelve 403 a quien no ha iniciado sesión', function () {
    $this->get(rutaDelModulo(Modulo::Empleados))->assertForbidden();
});

it('quita el acceso al revocar el módulo', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Vacaciones);

    app(ContextoTenant::class)->paraTenant($empresa, function () use ($usuario) {
        $usuario->modulos()->where('modulo', Modulo::Vacaciones->value)->delete();
    });

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Vacaciones))
        ->assertForbidden();
});

it('permite volver a conceder un módulo revocado', function () {
    // El índice único de `user_modulos` es parcial: solo mira las filas
    // vigentes. Sin eso, revocar dejaría el módulo inconcedible para siempre.
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Vacaciones);

    app(ContextoTenant::class)->paraTenant($empresa, function () use ($usuario) {
        $usuario->modulos()->where('modulo', Modulo::Vacaciones->value)->delete();
        $usuario->modulos()->create(['modulo' => Modulo::Vacaciones->value]);
    });

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Vacaciones))
        ->assertOk();
});

it('no da acceso con un módulo asignado en otra empresa', function () {
    // La fila existe y apunta a este usuario, pero pertenece a otra empresa.
    // El filtro del trait la deja fuera, así que el permiso no cuenta.
    $empresaA = Tenant::factory()->create();
    $empresaB = Tenant::factory()->create();
    $usuario = usuarioDe($empresaA, Rol::Administrador);

    app(ContextoTenant::class)->paraTenant($empresaB, function () use ($usuario) {
        $usuario->modulos()->create(['modulo' => Modulo::Vacaciones->value]);
    });

    $this->actingAs($usuario)
        ->get(rutaDelModulo(Modulo::Vacaciones))
        ->assertForbidden();
});

it('comparte con la interfaz solo los módulos a los que se puede entrar', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Vacaciones, Modulo::Contratos);

    $permitidos = app(ContextoTenant::class)->paraTenant(
        $empresa,
        fn () => app(PoliticaDeModulos::class)->modulosPermitidos($usuario)
    );

    expect($permitidos)->toEqualCanonicalizing([
        Modulo::Inicio,
        Modulo::Contratos,
        Modulo::Vacaciones,
    ]);
});

it('da al superadministrador todos los módulos y al owner ninguno', function () {
    $empresa = Tenant::factory()->create();
    $politica = app(PoliticaDeModulos::class);

    $superadministrador = usuarioDe($empresa, Rol::Superadministrador);
    $owner = User::factory()->owner()->create();

    expect($politica->modulosPermitidos($superadministrador))->toHaveCount(count(Modulo::cases()))
        ->and($politica->modulosPermitidos($owner))->toBe([]);
});

it('deja fuera de los módulos asignables a inicio y configuración', function () {
    expect(Modulo::valoresAsignables())
        ->not->toContain('inicio')
        ->not->toContain('configuracion')
        ->toContain('vacaciones');
});
