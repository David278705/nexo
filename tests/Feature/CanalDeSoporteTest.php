<?php

use App\Autorizacion\Rol;
use App\Models\Tenant;
use App\Models\User;

/**
 * El canal de soporte que el cliente ve en la barra lateral (`PROD-12`, paso 16
 * del plan de salida al mercado).
 *
 * Lo que se prueba es a quién le llega la prop compartida `soporte`: a todo
 * usuario de una empresa cuando hay correo configurado, y a nadie más.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create();

    config([
        'soporte.correo' => 'soporte@nexo.co',
        'soporte.horario' => 'Lunes a viernes, de 8:00 a. m. a 5:00 p. m.',
    ]);
});

/**
 * La prop `soporte` que recibe la pantalla de inicio de ese usuario.
 *
 * @return array<string, mixed>|null
 */
function soporteQueVe(User $usuario): ?array
{
    return test()->actingAs($usuario)->get('/')->assertOk()->viewData('page')['props']['soporte'];
}

it('le enseña el canal a un usuario de la empresa', function () {
    expect(soporteQueVe(usuarioDe($this->empresa, Rol::Superadministrador)))->toBe([
        'correo' => 'soporte@nexo.co',
        'horario' => 'Lunes a viernes, de 8:00 a. m. a 5:00 p. m.',
    ]);
});

it('se lo enseña también a un administrador sin módulos de configuración', function () {
    expect(soporteQueVe(usuarioDe($this->empresa, Rol::Administrador)))->not->toBeNull();
});

it('no enseña nada mientras no haya correo configurado', function () {
    config(['soporte.correo' => '']);

    expect(soporteQueVe(usuarioDe($this->empresa, Rol::Superadministrador)))->toBeNull();
});

it('no se lo enseña al proveedor, que no se escribe a sí mismo', function () {
    [$owner, $codigo] = ownerVerificable();
    test()->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => $codigo]);

    $props = test()->get('/proveedor')->assertOk()->viewData('page')['props'];

    expect($props['soporte'])->toBeNull();
});
