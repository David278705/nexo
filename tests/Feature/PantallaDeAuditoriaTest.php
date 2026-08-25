<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Multiempresa\ContextoTenant;

/**
 * La pantalla de auditoría (REV-04, documento 1, 8.2 y documento 3, 5.6).
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Auditoria, Modulo::Vacaciones);
});

/**
 * Las filas que la pantalla le enseña a este usuario.
 *
 * @return array<int, array<string, mixed>>
 */
function filasDeAuditoria(string $consulta = ''): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/auditoria'.($consulta === '' ? '' : '?'.$consulta));

    $respuesta->assertOk();

    /** @var array<string, mixed> $props */
    $props = $respuesta->viewData('page')['props'];

    return $props['auditoria']['filas'];
}

describe('el «hecho cuando» de la tarea', function () {
    it('editar y eliminar una vacación se ven en la pantalla con su autor, su fecha y el valor anterior', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $this->actingAs($this->usuario)->put('/vacaciones/'.$vacacion->id, [
            'fecha_inicio' => '2026-07-27',
            'fecha_fin' => '2026-07-29',
        ])->assertRedirect('/vacaciones');

        $this->actingAs($this->usuario)->delete('/vacaciones/'.$vacacion->id)
            ->assertRedirect('/vacaciones');

        // Se filtra por módulo y acción en vez de fiarse del orden: dos
        // acciones seguidas en la misma prueba pueden compartir el mismo
        // segundo, y entonces el desempate por `id` decide el orden.
        $editada = filasDeAuditoria('modulo=vacaciones&accion=editado')[0];
        $eliminada = filasDeAuditoria('modulo=vacaciones&accion=eliminado')[0];

        expect($eliminada['accion'])->toBe('Eliminado')
            ->and($eliminada['usuario'])->toBe($this->usuario->name)
            ->and($eliminada['fecha'])->not->toBe('—')
            ->and($editada['accion'])->toBe('Editado')
            ->and($editada['usuario'])->toBe($this->usuario->name)
            ->and($editada['cambios'])->toContain(['campo' => 'fecha fin', 'antes' => '31/07/2026', 'despues' => '29/07/2026']);
    });

    it('el código de diagnóstico de una incapacidad no aparece en ninguna parte de la pantalla', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '80112334']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::create([
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-14',
            'fecha_fin' => '2026-07-16',
            'codigo_diagnostico' => 'J06.9',
            'es_prorroga' => false,
            'eps_id' => Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true])->id,
            'origen' => 'comun',
        ]));

        $respuesta = $this->actingAs($this->usuario)->get('/auditoria');

        expect($respuesta->getContent())->not->toContain('J06.9');

        $filas = filasDeAuditoria();
        $cambios = array_merge(...array_column($filas, 'cambios'));

        expect(array_column($cambios, 'despues'))->not->toContain('J06.9')
            ->and(array_column($cambios, 'antes'))->not->toContain('J06.9');
    });
});

describe('los filtros', function () {
    it('filtra por módulo', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        // Nada de este escenario toca Movimientos.
        expect(filasDeAuditoria('modulo=movimientos'))->toBe([]);

        $filas = filasDeAuditoria('modulo=vacaciones');

        expect($filas)->not->toBe([])
            ->and(array_values(array_unique(array_column($filas, 'modulo'))))->toBe(['Vacaciones']);
    });

    it('filtra por acción', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $this->actingAs($this->usuario)->delete('/vacaciones/'.$vacacion->id);

        $filas = filasDeAuditoria('accion=eliminado');

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['accion'])->toBe('Eliminado');
    });

    it('filtra por usuario', function () {
        $otro = usuarioDe($this->empresa, Rol::Administrador, Modulo::Auditoria, Modulo::Vacaciones);
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        // Solo la edición queda atribuida a alguien: `$this->usuario` la hace
        // por la ruta, así que el filtro por `$otro` no debe traerla.
        $this->actingAs($this->usuario)->put('/vacaciones/'.$vacacion->id, [
            'fecha_inicio' => '2026-07-27',
            'fecha_fin' => '2026-07-29',
        ]);

        expect(array_column(filasDeAuditoria('usuario='.$otro->id), 'modulo'))->not->toContain('Vacaciones');

        $filas = filasDeAuditoria('usuario='.$this->usuario->id);

        expect(array_column($filas, 'modulo'))->toContain('Vacaciones')
            ->and(array_values(array_unique(array_column($filas, 'usuario'))))->toBe([$this->usuario->name]);
    });
});

describe('acceso y aislamiento', function () {
    it('devuelve 403 a quien no tiene el módulo, aunque escriba la dirección', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $this->actingAs($sinPermiso)->get('/auditoria')->assertForbidden();
    });

    it('no enseña la auditoría de otra empresa', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        // La empresa B tiene su propia auditoría legítima —su alta y la de su
        // administrador—, así que la prueba no espera una pantalla vacía: lo
        // que defiende es que nada de la vacación de la empresa A se cuele.
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);
        $usuarioB = usuarioDe($otra, Rol::Administrador, Modulo::Auditoria);

        $respuesta = $this->actingAs($usuarioB)->get('/auditoria');

        $respuesta->assertOk();

        /** @var array<string, mixed> $props */
        $props = $respuesta->viewData('page')['props'];
        expect(array_column($props['auditoria']['filas'], 'modulo'))->not->toContain('Vacaciones');
    });
});
