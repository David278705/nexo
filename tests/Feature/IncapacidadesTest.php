<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Exports\IncapacidadesExport;
use App\Http\Middleware\HandleInertiaRequests;
use App\Incapacidades\ListadoDeIncapacidades;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Maatwebsite\Excel\Facades\Excel;

/**
 * El módulo de incapacidades (documento 1, 6.3), el primero que estrena el
 * armazón de listado (REV-01, REV-02) y el flujo compartido de novedades
 * (F2-03).
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Incapacidades);
    $this->eps = app(ContextoTenant::class)->paraTenant(
        $this->empresa,
        fn () => Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]),
    );
});

function datosDeIncapacidad(array $cambios = []): array
{
    return [
        'fecha_inicio' => '2026-07-14',
        'fecha_fin' => '2026-07-16',
        'codigo_diagnostico' => 'J06.9',
        'es_prorroga' => 'no',
        'origen' => 'comun',
        ...$cambios,
    ];
}

describe('el caso obligatorio de la sección 6.3', function () {
    it('del 14 al 16 de julio muestra 3 días en el listado', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $respuesta = $this->actingAs($this->usuario)->get('/incapacidades');

        expect($respuesta->viewData('page')['props']['incapacidades']['filas'][0]['dias'])->toBe(3);
    });

    it('calcula los días de la incapacidad que se está digitando, sin restar fechas en el navegador', function () {
        $respuesta = $this->actingAs($this->usuario)
            ->get('/incapacidades?'.http_build_query(['fecha_inicio' => '2026-07-14', 'fecha_fin' => '2026-07-16']), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Incapacidades/Index',
                'X-Inertia-Partial-Data' => 'diasDeIncapacidad',
            ]);

        expect($respuesta->json()['props']['diasDeIncapacidad'])->toBe('3 días');
    });
});

describe('registrar una incapacidad', function () {
    it('la guarda sobre un empleado activo y confirma con el texto del documento 3', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidad([
            'vinculacion_id' => $vinculacion->id,
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertRedirect('/incapacidades')->assertSessionHas('estado', 'Incapacidad registrada.');

        $incapacidad = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Incapacidad::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        expect($incapacidad->dias())->toBe(3)
            ->and($incapacidad->codigo_diagnostico)->toBe('J06.9')
            ->and($incapacidad->es_prorroga)->toBeFalse()
            ->and($incapacidad->origen->value)->toBe('comun');
    });

    it('no registra sobre un documento que no corresponde a ningún empleado', function () {
        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidad([
            'vinculacion_id' => 999999,
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
        expect(Incapacidad::withoutGlobalScopes()->count())->toBe(0);
    });

    it('no registra sobre una vinculación cuya persona ya no está activa', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1017234891'], [], terminada: true);

        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidad([
            'vinculacion_id' => $vinculacion->id,
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
    });

    it('no registra con la fecha de terminación anterior a la de inicio', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidad([
            'vinculacion_id' => $vinculacion->id,
            'eps_id' => $this->eps->id,
            'fecha_inicio' => '2026-07-16',
            'fecha_fin' => '2026-07-14',
        ]));

        $respuesta->assertSessionHasErrors('fecha_fin');
    });

    it('no registra sobre el empleado de otra empresa aunque se fuerce su identificador', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra Empresa']);

        $ajeno = app(ContextoTenant::class)->paraTenant($otra, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['numero_documento' => '99999999']))
            ->create());

        $respuesta = $this->actingAs($this->usuario)->post('/incapacidades', datosDeIncapacidad([
            'vinculacion_id' => $ajeno->id,
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
    });
});

describe('el permiso del módulo', function () {
    it('responde 403 a quien no tiene el módulo de incapacidades asignado', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)->get('/incapacidades')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/incapacidades/exportar')->assertForbidden();
        $this->actingAs($sinPermiso)->post('/incapacidades', datosDeIncapacidad())->assertForbidden();
        // Sobre un registro que sí existe: si no, la respuesta sería 404 y no
        // demostraría nada del permiso.
        $incapacidad = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de(empleadoParaNovedad(['numero_documento' => '43887201']))
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $this->actingAs($sinPermiso)->put('/incapacidades/'.$incapacidad->id, datosDeIncapacidad())->assertForbidden();
        $this->actingAs($sinPermiso)->delete('/incapacidades/'.$incapacidad->id)->assertForbidden();
    });

    it('el diagnóstico se ve en el listado de quien sí tiene el módulo', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->create(['eps_id' => $this->eps->id, 'codigo_diagnostico' => 'M54.5']));

        $respuesta = $this->actingAs($this->usuario)->get('/incapacidades');

        expect($respuesta->viewData('page')['props']['incapacidades']['filas'][0]['diagnostico'])->toBe('M54.5');
    });
});

describe('editar y eliminar (documento 1, 6.2)', function () {
    it('la confirmación de borrado es la frase literal del documento 3, 2.5', function () {
        $vinculacion = empleadoParaNovedad([
            'numero_documento' => '43887201',
            'primer_nombre' => 'Ana',
            'segundo_nombre' => null,
            'primer_apellido' => 'Torres',
            'segundo_apellido' => null,
        ]);

        $incapacidad = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $frase = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $incapacidad->confirmacionDeBorrado(),
        );

        expect($frase)->toBe(
            'Vas a eliminar la incapacidad del 14 al 16 de julio de Ana Torres. '
            .'Esta acción queda registrada en la auditoría.'
        );
    });

    it('editar corrige los campos y recalcula los días', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $incapacidad = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $respuesta = $this->actingAs($this->usuario)->put('/incapacidades/'.$incapacidad->id, datosDeIncapacidad([
            'fecha_fin' => '2026-07-20',
            'codigo_diagnostico' => 'M54.5',
            'es_prorroga' => 'si',
            'eps_id' => $this->eps->id,
        ]));

        $respuesta->assertRedirect('/incapacidades')->assertSessionHas('estado', 'Incapacidad actualizada.');

        $corregida = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Incapacidad::query()->findOrFail($incapacidad->id),
        );

        expect($corregida->dias())->toBe(7)
            ->and($corregida->codigo_diagnostico)->toBe('M54.5')
            ->and($corregida->es_prorroga)->toBeTrue();
    });

    it('eliminar es borrado lógico y deja constancia en la auditoría', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $incapacidad = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id]));

        $this->actingAs($this->usuario)
            ->delete('/incapacidades/'.$incapacidad->id)
            ->assertRedirect('/incapacidades')
            ->assertSessionHas('estado', 'Incapacidad eliminada.');

        expect(Incapacidad::withoutGlobalScopes()->whereNotNull('deleted_at')->count())->toBe(1);
    });
});

describe('la exportación', function () {
    it('trae documento, nombre, cargo y el código de diagnóstico', function () {
        Excel::fake();

        $vinculacion = empleadoParaNovedad(
            [
                'numero_documento' => '43887201',
                'primer_nombre' => 'Carolina',
                'segundo_nombre' => null,
                'primer_apellido' => 'Ramírez',
                'segundo_apellido' => null,
            ],
            ['cargo' => 'Analista de compras'],
        );

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Incapacidad::factory()
            ->de($vinculacion)
            ->del14al16DeJulio()
            ->create(['eps_id' => $this->eps->id, 'codigo_diagnostico' => 'J06.9']));

        $this->actingAs($this->usuario)->get('/incapacidades/exportar')->assertOk();

        Excel::assertDownloaded(
            'incapacidades-'.now()->format('Y-m-d').'.xlsx',
            function (IncapacidadesExport $exportacion) {
                $fila = ListadoDeIncapacidades::fila($exportacion->collection()->first());

                return $fila['documento'] === '43887201'
                    && $fila['nombre'] === 'Carolina Ramírez'
                    && $fila['cargo'] === 'Analista de compras'
                    && $fila['diagnostico'] === 'J06.9'
                    && $fila['dias'] === 3;
            }
        );
    });
});
