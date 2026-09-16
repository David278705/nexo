<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Multiempresa\ContextoTenant;

/**
 * `HD-03`: ninguna novedad se guarda si se pisa con otra ya registrada del
 * mismo empleado. `ComprobacionDeTraslape` es el único sitio que compara
 * fechas para vacaciones, incapacidades y licencias.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe(
        $this->empresa,
        Rol::Administrador,
        Modulo::Vacaciones,
        Modulo::Incapacidades,
        Modulo::Licencias,
    );
    $this->eps = app(ContextoTenant::class)->paraTenant(
        $this->empresa,
        fn () => Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]),
    );
});

function datosDeIncapacidadParaTraslape(array $cambios = []): array
{
    return [
        'codigo_diagnostico' => 'M54.5',
        'es_prorroga' => 'no',
        'origen' => 'comun',
        ...$cambios,
    ];
}

describe('dos novedades del mismo tipo que se pisan', function () {
    it('no deja registrar unas vacaciones sobre otras ya registradas, el caso real que abrió la tarea', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->create(['fecha_inicio' => '2026-09-02', 'fecha_fin' => '2026-09-11']));

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', [
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-09-10',
            'fecha_fin' => '2026-09-24',
        ]);

        $respuesta->assertSessionHasErrors([
            'fecha_inicio' => 'Estas fechas se pisan con unas vacaciones ya registradas, del 2 al 11 de septiembre.',
        ]);
        expect(app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Vacacion::query()->where('vinculacion_id', $vinculacion->id)->count(),
        ))->toBe(1);
    });

    it('no deja registrar una incapacidad sobre otra ya registrada', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidadParaTraslape([
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-15',
            'fecha_fin' => '2026-07-20',
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertSessionHasErrors([
            'fecha_inicio' => 'Estas fechas se pisan con otra incapacidad ya registrada, del 14 al 16 de julio.',
        ]);
    });

    it('no deja registrar una licencia sobre otra ya registrada', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Licencia::factory()
            ->de($vinculacion)
            ->delJuevesAlMartes()
            ->create(['tipo' => 'suspension']));

        $respuesta = $this->actingAs($this->usuario)->post('/licencias', [
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'ausencia',
            'fecha_inicio' => '2026-07-05',
            'fecha_fin' => '2026-07-10',
        ]);

        $respuesta->assertSessionHasErrors([
            'fecha_inicio' => 'Estas fechas se pisan con una suspensión ya registrada, del 2 al 7 de julio.',
        ]);
    });
});

describe('una novedad nueva sobre una incapacidad ya registrada', function () {
    it('no deja registrar vacaciones sobre una incapacidad', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', [
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-15',
            'fecha_fin' => '2026-07-20',
        ]);

        $respuesta->assertSessionHasErrors([
            'fecha_inicio' => 'Estas fechas se pisan con una incapacidad ya registrada, del 14 al 16 de julio. '
                .'Sobre una incapacidad no se puede registrar otra novedad.',
        ]);
    });

    it('no deja registrar una licencia no remunerada sobre una incapacidad', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $respuesta = $this->actingAs($this->usuario)->post('/licencias', [
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'licencia_no_remunerada',
            'fecha_inicio' => '2026-07-15',
            'fecha_fin' => '2026-07-20',
        ]);

        $respuesta->assertSessionHasErrors('fecha_inicio');
    });
});

describe('vacaciones y suspensión a la vez', function () {
    it('no deja registrar vacaciones sobre una suspensión ya registrada', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Licencia::factory()
            ->de($vinculacion)
            ->delJuevesAlMartes()
            ->create(['tipo' => 'suspension']));

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', [
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-05',
            'fecha_fin' => '2026-07-10',
        ]);

        $respuesta->assertSessionHasErrors('fecha_inicio');
    });
});

describe('el caso contrario no se bloquea, es HD-04', function () {
    it('deja registrar una incapacidad que cae sobre vacaciones ya registradas', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->create(['fecha_inicio' => '2026-07-14', 'fecha_fin' => '2026-07-16']));

        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidadParaTraslape([
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-15',
            'fecha_fin' => '2026-07-17',
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertRedirect('/incapacidades')->assertSessionHasNoErrors();
    });
});

describe('editar una novedad', function () {
    it('falla igual que crear cuando la corrección pisa otra novedad', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->create(['fecha_inicio' => '2026-09-02', 'fecha_fin' => '2026-09-11']));

        $segunda = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->create(['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-05']));

        $respuesta = $this->actingAs($this->usuario)->put('/vacaciones/'.$segunda->id, [
            'fecha_inicio' => '2026-09-10',
            'fecha_fin' => '2026-09-24',
        ]);

        $respuesta->assertSessionHasErrors([
            'fecha_inicio' => 'Estas fechas se pisan con unas vacaciones ya registradas, del 2 al 11 de septiembre.',
        ]);
    });

    it('no se pisa consigo misma al guardar sin cambiar las fechas', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->create(['fecha_inicio' => '2026-09-02', 'fecha_fin' => '2026-09-11']));

        $respuesta = $this->actingAs($this->usuario)->put('/vacaciones/'.$vacacion->id, [
            'fecha_inicio' => '2026-09-02',
            'fecha_fin' => '2026-09-11',
        ]);

        $respuesta->assertRedirect('/vacaciones')->assertSessionHasNoErrors();
    });
});

describe('novedades borradas lógicamente', function () {
    it('una vacación eliminada ya no bloquea el mismo periodo', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->create(['fecha_inicio' => '2026-09-02', 'fecha_fin' => '2026-09-11']));

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vacacion->delete());

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', [
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-09-02',
            'fecha_fin' => '2026-09-11',
        ]);

        $respuesta->assertRedirect('/vacaciones')->assertSessionHasNoErrors();
    });
});
