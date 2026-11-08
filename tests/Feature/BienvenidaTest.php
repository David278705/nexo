<?php

use App\Autorizacion\Rol;
use App\Legal\DocumentosLegales;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\PlanesSeeder;

/**
 * La página pública de Nexo (`PROD-27`).
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`.
 */
it('se ve sin sesión', function () {
    $this->get('/bienvenida')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Publico/Bienvenida')->where('conSesion', false));
});

it('enseña los planes activos de la tabla, del más barato al más caro, con su implementación', function () {
    $this->seed(PlanesSeeder::class);
    Plan::query()->create(['nombre' => 'Viejo', 'precio_mensual' => '1.00', 'empleados_incluidos' => 1, 'usuarios_incluidos' => 1, 'activo' => false]);

    $planes = $this->get('/bienvenida')->viewData('page')['props']['planes'];

    expect(array_column($planes, 'nombre'))->toBe(['Micro', 'Pequeña', 'Mediana'])
        ->and($planes[0]['precio'])->toBe('$ 250.000')
        ->and($planes[1]['implementacion'])->toBe('$ 800.000');
});

it('trae el precio del usuario adicional y el correo comercial', function () {
    config(['comercial.correo' => 'ventas@nexo.co']);

    $props = $this->get('/bienvenida')->viewData('page')['props'];

    expect($props['usuarioAdicional'])->toBe('$ 40.000')
        ->and($props['correo'])->toBe('ventas@nexo.co');
});

it('enlaza los documentos legales, que se leen sin sesión', function () {
    // Los de verdad: el entorno de pruebas usa por defecto una carpeta vacía.
    app()->instance(DocumentosLegales::class, DocumentosLegales::delProyecto());

    $documentos = $this->get('/bienvenida')->viewData('page')['props']['documentos'];

    expect($documentos)->not->toBeEmpty();

    foreach ($documentos as $documento) {
        $this->get($documento['url'])->assertOk();
    }
});

it('a quien tiene sesión le ofrece ir a su cuenta, y la raíz lo sigue llevando a su inicio', function () {
    $usuario = usuarioDe(Tenant::factory()->create(), Rol::Superadministrador);

    expect($this->actingAs($usuario)->get('/bienvenida')->viewData('page')['props']['conSesion'])->toBeTrue();

    $this->get('/')->assertOk()->assertInertia(fn ($pagina) => $pagina->component('Inicio'));
});
