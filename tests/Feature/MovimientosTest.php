<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\FichaDeEmpleado;
use App\Exports\MovimientosExport;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Movimiento;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Movimientos\ListadoDeMovimientos;
use App\Multiempresa\ContextoTenant;
use App\Novedades\ConfirmacionDeBorrado;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Aumentos y promociones (documento 1, 8.1), con el mismo armazón de listado
 * (REV-01, REV-02) y el mismo flujo compartido de registro (F2-03) que los
 * tres módulos de novedades.
 *
 * Lo que este módulo tiene y ellos no: **guardar escribe en dos sitios**. Por
 * eso lo primero que se prueba es que el registro principal del empleado quedó
 * actualizado y que el movimiento aparece en el histórico, que es el «hecho
 * cuando» de la tarea.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Movimientos);
});

function datosDeMovimiento(array $cambios = []): array
{
    return [
        'tipo' => 'aumento_salarial',
        'cargo_nuevo' => 'Conductor',
        'salario_nuevo' => '1950000',
        'fecha_efectividad' => '2026-08-15',
        ...$cambios,
    ];
}

describe('guardar un movimiento actualiza el registro principal', function () {
    it('sube el salario del empleado y deja el movimiento en el histórico', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $respuesta = $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
        ]));

        $respuesta->assertRedirect('/movimientos')
            ->assertSessionHas('estado', 'Movimiento registrado. Salario y cargo actualizados.');

        [$vinculacion, $movimiento] = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            $vinculacion->fresh(),
            Movimiento::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        ]);

        expect((float) $vinculacion->getAttribute('salario'))->toBe(1950000.0)
            ->and((string) $vinculacion->getAttribute('cargo'))->toBe('Conductor')
            ->and((float) $movimiento->getAttribute('salario_anterior'))->toBe(1800000.0)
            ->and((float) $movimiento->getAttribute('salario_nuevo'))->toBe(1950000.0)
            ->and($movimiento->cargo_anterior)->toBe('Conductor')
            ->and($movimiento->cargo_nuevo)->toBe('Conductor');
    });

    it('cambia también el cargo cuando es una promoción', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            ['cargo' => 'Analista Senior', 'salario' => 2_700_000],
        );

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'promocion',
            'cargo_nuevo' => 'Coordinadora de Operaciones',
            'salario_nuevo' => '3200000',
        ]));

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect((string) $actualizada->getAttribute('cargo'))->toBe('Coordinadora de Operaciones')
            ->and((float) $actualizada->getAttribute('salario'))->toBe(3200000.0);
    });

    it('repite el cargo actual en un aumento salarial, aunque llegue otro', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '52998741'],
            ['cargo' => 'Analista de Compras', 'salario' => 2_300_000],
        );

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'cargo_nuevo' => 'Gerente General',
            'salario_nuevo' => '2500000',
        ]));

        [$actualizada, $movimiento] = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            $vinculacion->fresh(),
            Movimiento::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        ]);

        expect((string) $actualizada->getAttribute('cargo'))->toBe('Analista de Compras')
            ->and($movimiento->cargo_nuevo)->toBe('Analista de Compras');
    });

    it('aparece en el histórico de la ficha con el anterior frente al nuevo', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            ['cargo' => 'Analista Senior', 'salario' => 2_700_000],
        );

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'promocion',
            'cargo_nuevo' => 'Coordinadora de Operaciones',
            'salario_nuevo' => '3200000',
            'fecha_efectividad' => '2026-08-01',
        ]));

        $historico = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => FichaDeEmpleado::de($vinculacion->fresh())->movimientos(),
        );

        expect($historico)->toHaveCount(1)
            ->and($historico[0])->toBe([
                'fecha' => '01/08/2026',
                'clase' => 'Promoción',
                'cargo' => 'Analista Senior → Coordinadora de Operaciones',
                'salario' => '$ 2.700.000 → $ 3.200.000',
            ]);
    });

    it('deja los dos movimientos en el histórico cuando hay más de uno', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'salario_nuevo' => '1950000',
            'fecha_efectividad' => '2026-08-15',
        ]));

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'salario_nuevo' => '2100000',
            'fecha_efectividad' => '2026-09-01',
        ]));

        $historico = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => FichaDeEmpleado::de($vinculacion->fresh())->movimientos(),
        );

        // El más reciente arriba, y el segundo parte del salario que dejó el
        // primero: la foto del anterior no se calcula, se guardó ese día.
        expect($historico)->toHaveCount(2)
            ->and($historico[0]['salario'])->toBe('$ 1.950.000 → $ 2.100.000')
            ->and($historico[1]['salario'])->toBe('$ 1.800.000 → $ 1.950.000');
    });
});

describe('el formulario del movimiento', function () {
    it('comparte el cargo y el salario vigentes para enseñarlos de solo lectura', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            ['cargo' => 'Analista Senior', 'salario' => 3_200_000],
        );

        $respuesta = $this->actingAs($this->usuario)
            ->get('/movimientos?'.http_build_query(['vinculacion_id' => $vinculacion->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Movimientos/Index',
                'X-Inertia-Partial-Data' => 'situacionActual',
            ]);

        expect($respuesta->json()['props']['situacionActual'])->toBe([
            'cargo' => 'Analista Senior',
            'salario' => '$ 3.200.000',
        ]);
    });

    it('no resuelve la situación actual en la carga de la pantalla', function () {
        $props = $this->actingAs($this->usuario)->get('/movimientos')->viewData('page')['props'];

        expect($props)->not->toHaveKey('situacionActual');
    });

    it('ofrece los dos tipos con su consecuencia escrita', function () {
        $props = $this->actingAs($this->usuario)->get('/movimientos')->viewData('page')['props'];

        expect($props['opciones']['tiposDelFormulario'])->toBe([
            ['valor' => 'aumento_salarial', 'texto' => 'Aumento salarial — mismo cargo'],
            ['valor' => 'promocion', 'texto' => 'Promoción — cambia de cargo'],
        ]);
    });
});

describe('las validaciones del registro', function () {
    it('no registra sobre un documento que no corresponde a ningún empleado', function () {
        $respuesta = $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => 999999,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
        expect(Movimiento::withoutGlobalScopes()->count())->toBe(0);
    });

    it('no registra sobre una vinculación que ya no está activa', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1017234891'], [], terminada: true);

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
        ]))->assertSessionHasErrors('vinculacion_id');
    });

    it('exige el nuevo cargo cuando el movimiento es una promoción', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'promocion',
            'cargo_nuevo' => '',
        ]));

        $respuesta->assertSessionHasErrors([
            'cargo_nuevo' => 'Una promoción cambia de cargo: escribe el nuevo.',
        ]);
    });

    it('no registra un salario que no es dinero', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'salario_nuevo' => 'dos millones',
        ]))->assertSessionHasErrors('salario_nuevo');
    });

    it('no registra con un tipo que no es ninguno de los dos', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'traslado',
        ]))->assertSessionHasErrors('tipo');
    });

    it('no deja al empleado tocado cuando la petición no pasa la validación', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $this->actingAs($this->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            'salario_nuevo' => 'dos millones',
        ]));

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect((float) $actualizada->getAttribute('salario'))->toBe(1800000.0);
    });
});

describe('el listado', function () {
    it('enseña el cargo y el salario anteriores frente a los nuevos', function () {
        $vinculacion = empleadoParaNovedad(
            [
                'numero_documento' => '43887201',
                'primer_nombre' => 'Carolina',
                'segundo_nombre' => null,
                'primer_apellido' => 'Restrepo',
                'segundo_apellido' => null,
            ],
            ['cargo' => 'Coordinadora de Operaciones'],
        );

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Movimiento::factory()
            ->de($vinculacion)
            ->promocion('Analista Senior', 'Coordinadora de Operaciones')
            ->create([
                'salario_anterior' => 2_700_000,
                'salario_nuevo' => 3_200_000,
                'fecha_efectividad' => '2026-08-01',
            ]));

        $fila = $this->actingAs($this->usuario)
            ->get('/movimientos')
            ->viewData('page')['props']['movimientos']['filas'][0];

        expect($fila['nombre'])->toBe('Carolina Restrepo')
            ->and($fila['tipo'])->toBe('Promoción')
            ->and($fila['cargoAnterior'])->toBe('Analista Senior')
            ->and($fila['cargoNuevo'])->toBe('Coordinadora de Operaciones')
            ->and($fila['salarioAnterior'])->toBe('$ 2.700.000')
            ->and($fila['salarioNuevo'])->toBe('$ 3.200.000')
            ->and($fila['efectivo'])->toBe('01/08/2026');
    });

    it('filtra por tipo de movimiento y lo dice en la barra', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Movimiento::factory()->de($vinculacion)->create();
            Movimiento::factory()->de($vinculacion)->promocion('Auxiliar', 'Analista')->create();
        });

        $props = $this->actingAs($this->usuario)
            ->get('/movimientos?tipo=promocion')
            ->viewData('page')['props'];

        expect($props['movimientos']['total'])->toBe(1)
            ->and($props['movimientos']['filas'][0]['tipo'])->toBe('Promoción')
            ->and($props['aplicados'])->toContain(['clave' => 'tipo', 'texto' => 'Tipo: Promoción']);
    });

    it('filtra por rango de fecha de efectividad', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Movimiento::factory()->de($vinculacion)->create(['fecha_efectividad' => '2026-08-01']);
            Movimiento::factory()->de($vinculacion)->create(['fecha_efectividad' => '2026-09-15']);
        });

        $props = $this->actingAs($this->usuario)
            ->get('/movimientos?desde=2026-09-01')
            ->viewData('page')['props'];

        expect($props['movimientos']['total'])->toBe(1)
            ->and($props['movimientos']['filas'][0]['efectivo'])->toBe('15/09/2026');
    });
});

/**
 * REV-03, la decisión de David: «eliminar es volver al pasado». Borrar un
 * movimiento deja al empleado exactamente como estaba antes de crearlo, y solo
 * se puede deshacer el último de la lista.
 */
describe('corregir y eliminar (REV-03)', function () {
    /**
     * Registra un aumento por la ruta de verdad y devuelve la fila creada, que
     * es como llegan a existir los movimientos que estas pruebas deshacen.
     */
    function registrarMovimientoDe(Vinculacion $vinculacion, array $cambios = []): Movimiento
    {
        test()->actingAs(test()->usuario)->post('/movimientos', datosDeMovimiento([
            'vinculacion_id' => $vinculacion->id,
            ...$cambios,
        ]))->assertRedirect('/movimientos');

        return app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn () => Movimiento::query()->orderByDesc('id')->firstOrFail(),
        );
    }

    /**
     * El «hecho cuando» de la tarea: el empleado queda indistinguible de como
     * estaba antes de que el movimiento existiera.
     */
    it('eliminar el movimiento devuelve el cargo y el salario que había justo antes', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            ['cargo' => 'Analista Senior', 'salario' => 2_700_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, [
            'tipo' => 'promocion',
            'cargo_nuevo' => 'Coordinadora de Operaciones',
            'salario_nuevo' => '3200000',
        ]);

        $this->actingAs($this->usuario)
            ->delete('/movimientos/'.$movimiento->id)
            ->assertRedirect('/movimientos')
            ->assertSessionHas('estado', 'Movimiento eliminado. Salario y cargo volvieron a como estaban.');

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect((string) $actualizada->getAttribute('cargo'))->toBe('Analista Senior')
            ->and((float) $actualizada->getAttribute('salario'))->toBe(2700000.0)
            // La fila sigue ahí, marcada: nunca hay borrado físico (regla 4).
            ->and(Movimiento::withoutGlobalScopes()->count())->toBe(1)
            ->and(Movimiento::withoutGlobalScopes()->whereNotNull('deleted_at')->count())->toBe(1);
    });

    it('deshacer los dos de una cadena, de arriba abajo, deja el salario de partida', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $primero = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '1950000']);
        $segundo = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '2100000']);

        $this->actingAs($this->usuario)->delete('/movimientos/'.$segundo->id);
        $this->actingAs($this->usuario)->delete('/movimientos/'.$primero->id);

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect((float) $actualizada->getAttribute('salario'))->toBe(1800000.0);
    });

    it('no deja eliminar un movimiento que tiene otro encima, y lo dice', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $primero = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '1950000']);
        registrarMovimientoDe($vinculacion, ['salario_nuevo' => '2100000']);

        $this->actingAs($this->usuario)
            ->delete('/movimientos/'.$primero->id)
            ->assertForbidden()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Error')
                ->where('mensaje', ConfirmacionDeBorrado::HAY_UNO_POSTERIOR));

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        // Ni se borró la fila ni se le tocó el salario al empleado.
        expect((float) $actualizada->getAttribute('salario'))->toBe(2100000.0)
            ->and(Movimiento::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(2);
    });

    it('tampoco deja corregir uno que tiene otro encima', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $primero = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '1950000']);
        registrarMovimientoDe($vinculacion, ['salario_nuevo' => '2100000']);

        $this->actingAs($this->usuario)
            ->put('/movimientos/'.$primero->id, datosDeMovimiento(['salario_nuevo' => '9000000']))
            ->assertForbidden();

        $sinTocar = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Movimiento::query()->findOrFail($primero->id),
        );

        expect((float) $sinTocar->getAttribute('salario_nuevo'))->toBe(1950000.0);
    });

    it('corregir el más reciente arrastra el cargo y el salario del empleado', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            ['cargo' => 'Analista Senior', 'salario' => 2_700_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, [
            'tipo' => 'promocion',
            'cargo_nuevo' => 'Coordinadora de Operaciones',
            'salario_nuevo' => '3200000',
        ]);

        $this->actingAs($this->usuario)
            ->put('/movimientos/'.$movimiento->id, datosDeMovimiento([
                'tipo' => 'promocion',
                'cargo_nuevo' => 'Coordinadora de Operaciones',
                'salario_nuevo' => '3300000',
                'fecha_efectividad' => '2026-09-01',
            ]))
            ->assertRedirect('/movimientos')
            ->assertSessionHas('estado', 'Movimiento actualizado. Salario y cargo corregidos.');

        [$actualizada, $corregido] = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            $vinculacion->fresh(),
            Movimiento::query()->findOrFail($movimiento->id),
        ]);

        expect((float) $actualizada->getAttribute('salario'))->toBe(3300000.0)
            ->and((string) $actualizada->getAttribute('cargo'))->toBe('Coordinadora de Operaciones')
            ->and($corregido->fecha_efectividad->toDateString())->toBe('2026-09-01')
            // La foto de lo anterior es un hecho de aquel día y no se corrige.
            ->and((float) $corregido->getAttribute('salario_anterior'))->toBe(2700000.0)
            ->and($corregido->cargo_anterior)->toBe('Analista Senior');
    });

    it('corregir un aumento salarial no deja colar un cargo nuevo por la puerta de atrás', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '52998741'],
            ['cargo' => 'Analista de Compras', 'salario' => 2_300_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '2500000']);

        $this->actingAs($this->usuario)->put('/movimientos/'.$movimiento->id, datosDeMovimiento([
            'cargo_nuevo' => 'Gerente General',
            'salario_nuevo' => '2600000',
        ]));

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect((string) $actualizada->getAttribute('cargo'))->toBe('Analista de Compras');
    });

    it('la fila dice cuál de los movimientos del empleado se puede deshacer', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        registrarMovimientoDe($vinculacion, ['salario_nuevo' => '1950000', 'fecha_efectividad' => '2026-08-15']);
        registrarMovimientoDe($vinculacion, ['salario_nuevo' => '2100000', 'fecha_efectividad' => '2026-09-01']);

        $filas = $this->actingAs($this->usuario)
            ->get('/movimientos')
            ->viewData('page')['props']['movimientos']['filas'];

        // El listado abre por fecha de efectividad ascendente, así que el de
        // agosto va primero; el que se puede deshacer es el otro.
        expect($filas[0]['efectivo'])->toBe('15/08/2026')
            ->and($filas[0]['reversible'])->toBeFalse()
            ->and($filas[1]['efectivo'])->toBe('01/09/2026')
            ->and($filas[1]['reversible'])->toBeTrue();
    });

    it('la confirmación dice a qué cargo y a qué salario vuelve el empleado', function () {
        $vinculacion = empleadoParaNovedad(
            [
                'numero_documento' => '43887201',
                'primer_nombre' => 'Carolina',
                'segundo_nombre' => null,
                'primer_apellido' => 'Restrepo',
                'segundo_apellido' => null,
            ],
            ['cargo' => 'Analista Senior', 'salario' => 2_700_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, [
            'tipo' => 'promocion',
            'cargo_nuevo' => 'Coordinadora de Operaciones',
            'salario_nuevo' => '3200000',
            'fecha_efectividad' => '2026-08-15',
        ]);

        $frase = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $movimiento->confirmacionDeBorrado(),
        );

        expect($frase)->toBe(
            'Vas a eliminar la promoción del 15 de agosto de 2026 de Carolina Restrepo. '
            .'El cargo de Carolina Restrepo volverá a Analista Senior y su salario a $ 2.700.000. '
            .'Esta acción queda registrada en la auditoría.'
        );
    });

    it('un aumento salarial no anuncia un cambio de cargo que no va a ocurrir', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '71654322'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '1950000']);

        $frase = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $movimiento->confirmacionDeBorrado(),
        );

        expect($frase)->toContain('el aumento salarial del 15 de agosto de 2026')
            ->and($frase)->toContain('volverá a $ 1.800.000')
            ->and($frase)->not->toContain('El cargo de');
    });

    it('el detalle trae el punto de partida del propio movimiento, no el del empleado', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            ['cargo' => 'Analista Senior', 'salario' => 2_700_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, [
            'tipo' => 'promocion',
            'cargo_nuevo' => 'Coordinadora de Operaciones',
            'salario_nuevo' => '3200000',
        ]);

        $detalle = $this->actingAs($this->usuario)
            ->get('/movimientos?'.http_build_query(['novedad' => $movimiento->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Movimientos/Index',
                'X-Inertia-Partial-Data' => 'novedad',
            ])
            ->json()['props']['novedad'];

        expect($detalle['id'])->toBe($movimiento->id)
            ->and($detalle['valores']['tipo'])->toBe('promocion')
            ->and($detalle['valores']['cargo_nuevo'])->toBe('Coordinadora de Operaciones')
            ->and($detalle['valores']['fecha_efectividad'])->toBe('2026-08-15')
            // El empleado ya lleva encima los valores nuevos: el formulario
            // enseña como punto de partida los que guardó el movimiento.
            ->and($detalle['contexto'])->toBe([
                'cargo' => 'Analista Senior',
                'salario' => '$ 2.700.000',
            ])
            ->and($detalle['datos'])->not->toBeEmpty();
    });

    it('no resuelve el detalle en la carga de la pantalla', function () {
        $props = $this->actingAs($this->usuario)->get('/movimientos')->viewData('page')['props'];

        expect($props)->not->toHaveKey('novedad');
    });

    it('deja corregir el movimiento de alguien que ya salió de la empresa', function () {
        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '1017234891'],
            ['cargo' => 'Conductor', 'salario' => 1_800_000],
        );

        $movimiento = registrarMovimientoDe($vinculacion, ['salario_nuevo' => '1950000']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            $vinculacion->fresh()->registrarTerminacion(
                TipoDeSalida::Renuncia,
                'Estudios',
                Carbon::parse('2026-09-30'),
            );
        });

        $this->actingAs($this->usuario)
            ->put('/movimientos/'.$movimiento->id, datosDeMovimiento(['salario_nuevo' => '1960000']))
            ->assertRedirect('/movimientos');
    });
});

describe('el permiso del módulo', function () {
    it('responde 403 a quien no tiene el módulo de movimientos asignado', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)->get('/movimientos')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/movimientos/exportar')->assertForbidden();
        $this->actingAs($sinPermiso)->post('/movimientos', datosDeMovimiento())->assertForbidden();
    });
});

describe('la exportación', function () {
    it('trae documento, nombre, cargo y los dos valores de cada movimiento', function () {
        Excel::fake();

        $vinculacion = empleadoParaNovedad(
            [
                'numero_documento' => '43887201',
                'primer_nombre' => 'Carolina',
                'segundo_nombre' => null,
                'primer_apellido' => 'Restrepo',
                'segundo_apellido' => null,
            ],
            ['cargo' => 'Coordinadora de Operaciones'],
        );

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Movimiento::factory()
            ->de($vinculacion)
            ->promocion('Analista Senior', 'Coordinadora de Operaciones')
            ->create(['salario_anterior' => 2_700_000, 'salario_nuevo' => 3_200_000]));

        $this->actingAs($this->usuario)->get('/movimientos/exportar')->assertOk();

        Excel::assertDownloaded(
            'movimientos-'.now()->format('Y-m-d').'.xlsx',
            function (MovimientosExport $exportacion) {
                $fila = ListadoDeMovimientos::fila($exportacion->collection()->first());

                return $fila['documento'] === '43887201'
                    && $fila['nombre'] === 'Carolina Restrepo'
                    && $fila['cargo'] === 'Coordinadora de Operaciones'
                    && $fila['tipo'] === 'Promoción'
                    && $fila['salarioAnterior'] === '$ 2.700.000'
                    && $fila['salarioNuevo'] === '$ 3.200.000';
            }
        );
    });
});
