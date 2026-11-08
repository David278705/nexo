<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;

/**
 * La leyenda «reclamo en trámite» (`PROD-14`, Ley 1581, artículo 18, literal g):
 * mientras se decide el reclamo de un titular, la base de datos tiene que decirlo.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create();
    $this->superadmin = usuarioDe($this->empresa, Rol::Superadministrador);
    $this->empleado = app(ContextoTenant::class)->paraTenant($this->empresa, fn (): Vinculacion => Vinculacion::factory()
        ->de(Persona::factory()->create())
        ->create());
});

function personaDelEmpleado(): Persona
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, fn (): Persona => test()->empleado->persona()->firstOrFail());
}

it('el superadministrador marca a un empleado con su motivo y fecha', function () {
    $this->actingAs($this->superadmin)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => 'Pide corregir su fecha de nacimiento.'])
        ->assertRedirect("/empleados/{$this->empleado->id}");

    $persona = personaDelEmpleado();

    expect($persona->tieneReclamoEnTramite())->toBeTrue()
        ->and($persona->reclamo_en_tramite_motivo)->toBe('Pide corregir su fecha de nacimiento.');
});

it('exige el motivo', function () {
    $this->actingAs($this->superadmin)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
});

it('la marca se ve en la ficha y en el listado', function () {
    $this->actingAs($this->superadmin)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => 'Pide corregir su dirección.']);

    $ficha = $this->get("/empleados/{$this->empleado->id}")->viewData('page')['props']['empleado'];
    $listado = $this->get('/empleados')->viewData('page')['props']['empleados']['filas'][0];

    expect($ficha['reclamo']['motivo'])->toBe('Pide corregir su dirección.')
        ->and($ficha['puedeMarcarReclamo'])->toBeTrue()
        ->and($listado['reclamo'])->toBe(1);
});

it('el superadministrador la quita cuando el reclamo se decide', function () {
    $this->actingAs($this->superadmin)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => 'Pide corregir su dirección.']);

    $this->delete("/empleados/{$this->empleado->id}/reclamo")->assertRedirect();

    expect(personaDelEmpleado()->tieneReclamoEnTramite())->toBeFalse();
});

it('un administrador ve la marca pero no la pone ni la quita', function () {
    $administrador = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

    $this->actingAs($administrador)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => 'x'])
        ->assertForbidden();
    $this->actingAs($administrador)
        ->delete("/empleados/{$this->empleado->id}/reclamo")
        ->assertForbidden();

    expect($this->actingAs($administrador)->get("/empleados/{$this->empleado->id}")
        ->viewData('page')['props']['empleado']['puedeMarcarReclamo'])->toBeFalse();
});

it('poner y quitar la marca queda en la auditoría', function () {
    $this->actingAs($this->superadmin)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => 'Pide corregir su dirección.']);
    $this->delete("/empleados/{$this->empleado->id}/reclamo");

    $lineas = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Auditoria::query()
        ->where('modelo', Persona::class)
        ->where('accion', 'editado')
        ->count());

    expect($lineas)->toBeGreaterThanOrEqual(2);
});

it('no deja marcar al empleado de otra empresa', function () {
    $otra = Tenant::factory()->create();
    $ajeno = usuarioDe($otra, Rol::Superadministrador);

    $this->actingAs($ajeno)
        ->post("/empleados/{$this->empleado->id}/reclamo", ['motivo' => 'x'])
        ->assertNotFound();

    expect(personaDelEmpleado()->tieneReclamoEnTramite())->toBeFalse();
});
