<?php

use App\Models\AccesoDePrueba;
use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Models\Tenant;
use App\Proveedor\AccesosDePrueba;

/**
 * La pantalla «Demostración» del panel del proveedor (documento 4, capítulo
 * 10): el estado del ambiente, el botón de restaurar y la generación y el
 * listado de accesos de prueba.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php`; Pest comparte las
 * funciones de todos los archivos de prueba. Los catálogos del `beforeEach`
 * son los mismos que pide `AmbienteDeDemostracion::poblar()` al restaurar de
 * verdad, no solo al leer el estado.
 */
beforeEach(function () {
    $this->demo = Tenant::factory()->ambienteDeDemo()->create();

    Pais::query()->firstOrCreate(['codigo' => Pais::COLOMBIA], ['nombre' => 'Colombia', 'activo' => true]);
    Ciudad::factory()->create(['nombre' => 'Bogotá D.C.']);

    Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]);
    FondoPension::query()->create(['nombre' => 'Protección', 'activo' => true]);
    Arl::query()->create(['nombre' => 'ARL Sura', 'activo' => true]);
    CajaCompensacion::query()->create(['nombre' => 'Compensar', 'activo' => true]);
    Banco::query()->create(['nombre' => 'Bancolombia', 'activo' => true]);
});

it('enseña el estado del ambiente y los accesos activos', function () {
    $activo = AccesoDePrueba::factory()->for($this->demo, 'tenant')->create(['nombre_prospecto' => 'Prospecto Activo']);
    AccesoDePrueba::factory()->for($this->demo, 'tenant')->vencido()->create(['nombre_prospecto' => 'Prospecto Vencido']);

    $this->actingAs(ownerEnElPanel())
        ->get('/proveedor/demostracion')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Proveedor/Demostracion')
            ->where('personas', 0)
            ->has('accesosActivos', 1)
            ->where('accesosActivos.0.nombreProspecto', 'Prospecto Activo'));
});

it('restaura el ambiente desde el botón del panel', function () {
    $this->actingAs(ownerEnElPanel())
        ->post('/proveedor/demostracion/restaurar')
        ->assertRedirect('/proveedor/demostracion');

    expect($this->demo->refresh()->demo_restaurado_en)->not->toBeNull();
});

it('genera un acceso desde el formulario del panel y enseña el enlace una sola vez', function () {
    $this->actingAs(ownerEnElPanel())
        ->post('/proveedor/demostracion/accesos', [
            'nombre_prospecto' => 'Comercializadora Prospecto S.A.S.',
            'dias_de_vigencia' => 10,
        ])
        ->assertRedirect('/proveedor/demostracion')
        ->assertSessionHas('enlaceGenerado');

    expect(AccesoDePrueba::query()->where('nombre_prospecto', 'Comercializadora Prospecto S.A.S.')->exists())
        ->toBeTrue();
});

it('exige el nombre del prospecto y los días de vigencia', function () {
    $this->actingAs(ownerEnElPanel())
        ->post('/proveedor/demostracion/accesos', [])
        ->assertSessionHasErrors(['nombre_prospecto', 'dias_de_vigencia']);
});

it('revoca un acceso desde el panel', function () {
    $acceso = AccesoDePrueba::factory()->for($this->demo, 'tenant')->create();

    $this->actingAs(ownerEnElPanel())
        ->post("/proveedor/demostracion/accesos/{$acceso->id}/revocar")
        ->assertRedirect('/proveedor/demostracion');

    expect(app(AccesosDePrueba::class)->activos())->toHaveCount(0);
});
