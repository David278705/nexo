<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Exports\VacacionesExport;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Incapacidad;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Presentacion\Formato;
use App\Vacaciones\AdvertenciaDeSaldo;
use App\Vacaciones\ListadoDeVacaciones;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * El segundo módulo de novedades (documento 1, 6.4), el primero que descuenta
 * del saldo.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle', 'sabado_laboral' => false]);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);
});

function datosDeVacacion(array $cambios = []): array
{
    return [
        'fecha_inicio' => '2026-07-27',
        'fecha_fin' => '2026-07-31',
        ...$cambios,
    ];
}

describe('el registro de vacaciones', function () {
    it('del 27 al 31 de julio muestra 5 días hábiles en el listado', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $respuesta = $this->actingAs($this->usuario)->get('/vacaciones');

        expect($respuesta->viewData('page')['props']['vacaciones']['filas'][0]['dias'])->toBe(5);
    });

    it('calcula los días hábiles que se están digitando, sin restar fechas en el navegador', function () {
        $respuesta = $this->actingAs($this->usuario)
            ->get('/vacaciones?'.http_build_query(['fecha_inicio' => '2026-07-27', 'fecha_fin' => '2026-07-31']), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Vacaciones/Index',
                'X-Inertia-Partial-Data' => 'diasDeVacaciones',
            ]);

        expect($respuesta->json()['props']['diasDeVacaciones'])->toBe('5 días');
    });

    it('las guarda sobre un empleado activo y confirma con el texto del documento 3', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', datosDeVacacion([
            'vinculacion_id' => $vinculacion->id,
        ]));

        $respuesta->assertRedirect('/vacaciones')->assertSessionHas('estado', 'Vacaciones registradas.');

        $vacacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Vacacion::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        expect($vacacion->dias())->toBe(5);
    });

    it('no registra sobre un documento que no corresponde a ningún empleado', function () {
        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', datosDeVacacion([
            'vinculacion_id' => 999999,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
        expect(Vacacion::withoutGlobalScopes()->count())->toBe(0);
    });

    it('no registra sobre una vinculación cuya persona ya no está activa', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1017234891'], [], terminada: true);

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', datosDeVacacion([
            'vinculacion_id' => $vinculacion->id,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
    });

    it('no registra con el día final anterior al inicial', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', datosDeVacacion([
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-31',
            'fecha_fin' => '2026-07-27',
        ]));

        $respuesta->assertSessionHasErrors('fecha_fin');
    });

    it('no registra sobre el empleado de otra empresa aunque se fuerce su identificador', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra Empresa']);

        $ajeno = app(ContextoTenant::class)->paraTenant($otra, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['numero_documento' => '99999999']))
            ->create());

        $respuesta = $this->actingAs($this->usuario)->post('/vacaciones', datosDeVacacion([
            'vinculacion_id' => $ajeno->id,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
    });
});

describe('el permiso del módulo', function () {
    it('responde 403 a quien no tiene el módulo de vacaciones asignado', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)->get('/vacaciones')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/vacaciones/exportar')->assertForbidden();
        $this->actingAs($sinPermiso)->post('/vacaciones', datosDeVacacion())->assertForbidden();
        // Sobre un registro que sí existe: si no, la respuesta sería 404 y no
        // demostraría nada del permiso.
        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de(empleadoParaNovedad(['numero_documento' => '43887201']))
            ->del27al31DeJulio()
            ->create());

        $this->actingAs($sinPermiso)->put('/vacaciones/'.$vacacion->id, datosDeVacacion())->assertForbidden();
        $this->actingAs($sinPermiso)->delete('/vacaciones/'.$vacacion->id)->assertForbidden();
    });
});

describe('la exportación', function () {
    it('trae documento, nombre, cargo y los días hábiles', function () {
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

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $this->actingAs($this->usuario)->get('/vacaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'vacaciones-'.now()->format('Y-m-d').'.xlsx',
            function (VacacionesExport $exportacion) {
                $fila = ListadoDeVacaciones::fila($exportacion->collection()->first());

                return $fila['documento'] === '43887201'
                    && $fila['nombre'] === 'Carolina Ramírez'
                    && $fila['cargo'] === 'Analista de compras'
                    && $fila['dias'] === 5;
            }
        );
    });

    it('incluye la nota del traslape cuando una incapacidad cae sobre las vacaciones (HD-04)', function () {
        Excel::fake();

        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-24',
            ]);

            Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-19',
            ]);
        });

        $this->actingAs($this->usuario)->get('/vacaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'vacaciones-'.now()->format('Y-m-d').'.xlsx',
            function (VacacionesExport $exportacion) {
                $fila = ListadoDeVacaciones::fila($exportacion->collection()->first());

                return $fila['dias'] === 4
                    && $fila['nota'] === 'La incapacidad del 10 al 19 de septiembre interrumpió el disfrute: 7 días volvieron al saldo.';
            }
        );
    });
});

describe('el descuento del saldo (documento 1, 6.4)', function () {
    it('registrar y eliminar unas vacaciones deja el saldo exactamente como estaba', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->create(['fecha_ingreso' => Carbon::create(2025, 1, 15)]);

            $antes = SaldoDeVacaciones::de($vinculacion)->al(Carbon::create(2026, 7, 31));

            $vacacion = Vacacion::factory()->de($vinculacion)->del27al31DeJulio()->create();

            $conVacaciones = SaldoDeVacaciones::de($vinculacion)->al(Carbon::create(2026, 7, 31));
            expect($conVacaciones)->toBe(round($antes - 5, 4));

            $vacacion->delete();

            $despues = SaldoDeVacaciones::de($vinculacion)->al(Carbon::create(2026, 7, 31));
            expect($despues)->toBe($antes);
        });
    });
});

describe('el traslape con una incapacidad (HD-04)', function () {
    /**
     * El caso real que abrió la tarea: David le registró a la misma persona
     * vacaciones del 10 al 24 de septiembre y luego un accidente el mismo
     * día 10 que la incapacita hasta el 19. Once días hábiles pedidos, siete
     * que se come la incapacidad, cuatro realmente disfrutados.
     */
    it('devuelve al saldo los días que la incapacidad se comió', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->create(['fecha_ingreso' => Carbon::create(2025, 1, 15)]);

            Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-24',
            ]);

            $hoy = Carbon::create(2026, 9, 30);
            $conVacacionesSolas = SaldoDeVacaciones::de($vinculacion)->al($hoy);

            Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-19',
            ]);

            $conIncapacidad = SaldoDeVacaciones::de($vinculacion)->al($hoy);

            expect($conIncapacidad)->toBe(round($conVacacionesSolas + 7, 4));
        });
    });

    it('conserva las fechas pedidas y recalcula los días realmente disfrutados, con su nota', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        // Las tres lecturas tienen que hacerse con el contexto de la empresa
        // todavía fijado: `dias()` y `notaDeTraslape()` cargan la vinculación
        // y sus incapacidades, y las dos llevan el global scope del tenant.
        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            $vacacion = Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-24',
            ]);

            Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-19',
            ]);

            $recuperada = Vacacion::query()->findOrFail($vacacion->id);

            expect($recuperada->fecha_inicio->toDateString())->toBe('2026-09-10')
                ->and($recuperada->fecha_fin->toDateString())->toBe('2026-09-24')
                ->and($recuperada->dias())->toBe(4)
                ->and($recuperada->notaDeTraslape())->toBe(
                    'La incapacidad del 10 al 19 de septiembre interrumpió el disfrute: 7 días volvieron al saldo.'
                );
        });
    });

    it('el listado enseña la nota, y una vacación sin traslape enseña una raya', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-24',
            ]);

            Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-19',
            ]);
        });

        $conTraslape = empleadoParaNovedad(['numero_documento' => '1017234891']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($conTraslape)
            ->del27al31DeJulio()
            ->create());

        $filas = $this->actingAs($this->usuario)->get('/vacaciones')
            ->viewData('page')['props']['vacaciones']['filas'];

        $notas = collect($filas)->pluck('nota', 'documento');

        expect($notas['43887201'])->toBe(
            'La incapacidad del 10 al 19 de septiembre interrumpió el disfrute: 7 días volvieron al saldo.'
        )->and($notas['1017234891'])->toBe('—');
    });

    it('eliminar la incapacidad devuelve las vacaciones a como estaban', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->create(['fecha_ingreso' => Carbon::create(2025, 1, 15)]);

            $vacacion = Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-24',
            ]);

            $incapacidad = Incapacidad::factory()->de($vinculacion)->create([
                'fecha_inicio' => '2026-09-10',
                'fecha_fin' => '2026-09-19',
            ]);

            expect(Vacacion::query()->findOrFail($vacacion->id)->dias())->toBe(4);

            $incapacidad->delete();

            $recuperada = Vacacion::query()->findOrFail($vacacion->id);

            expect($recuperada->dias())->toBe(11)
                ->and($recuperada->notaDeTraslape())->toBe('');
        });
    });
});

describe('editar y eliminar (documento 1, 6.2)', function () {
    /**
     * El «hecho cuando» de F2-10: eliminar unas vacaciones muestra el saldo
     * antes y después en la confirmación.
     */
    it('la confirmación de borrado dice cuántos días se devuelven y de cuánto a cuánto pasa el saldo', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $persona = Persona::factory()->create([
                'primer_nombre' => 'Carlos',
                'segundo_nombre' => null,
                'primer_apellido' => 'Ramírez',
                'segundo_apellido' => null,
            ]);

            $vinculacion = Vinculacion::factory()
                ->de($persona)
                ->create(['fecha_ingreso' => Carbon::create(2025, 1, 15)]);

            $vacacion = Vacacion::factory()->de($vinculacion)->del27al31DeJulio()->create();

            $saldo = SaldoDeVacaciones::de($vinculacion);
            $antes = $saldo->al(Carbon::today());
            $despues = $saldo->sin($vacacion)->al(Carbon::today());

            // Lo que la frase promete tiene que ser lo que de verdad pasa: los
            // cinco días hábiles vuelven al saldo.
            expect($despues)->toBe(round($antes + 5, 4));

            expect($vacacion->confirmacionDeBorrado())->toBe(
                'Vas a eliminar las vacaciones del 27 al 31 de julio de Carlos Ramírez. '
                .'Se eliminarán 5 días de vacaciones y el saldo de Carlos Ramírez pasará de '
                .Formato::numeroDecimal($antes).' a '.Formato::diasDecimales($despues).'. '
                .'Esta acción queda registrada en la auditoría.'
            );
        });
    });

    it('no anuncia un cambio de saldo que no va a ocurrir, como el de unas vacaciones futuras', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->create(['fecha_ingreso' => Carbon::create(2025, 1, 15)]);

            $vacacion = Vacacion::factory()->de($vinculacion)->create([
                'fecha_inicio' => Carbon::today()->addMonth(),
                'fecha_fin' => Carbon::today()->addMonth()->addDays(4),
            ]);

            expect($vacacion->confirmacionDeBorrado())->not->toContain('pasará de');
        });
    });

    it('la pantalla trae el detalle de la novedad solo cuando se le pide', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $respuesta = $this->actingAs($this->usuario)
            ->get('/vacaciones?'.http_build_query(['novedad' => $vacacion->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Vacaciones/Index',
                'X-Inertia-Partial-Data' => 'novedad',
            ]);

        $detalle = $respuesta->json()['props']['novedad'];

        expect($detalle['id'])->toBe($vacacion->id)
            ->and($detalle['valores']['fecha_inicio'])->toBe('2026-07-27')
            ->and($detalle['valores']['fecha_fin'])->toBe('2026-07-31')
            ->and($detalle['confirmacion'])->toStartWith('Vas a eliminar las vacaciones del 27 al 31 de julio')
            ->and($detalle['datos'])->not->toBeEmpty();
    });

    it('editar las fechas recalcula los días sin tocar ninguna columna', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $respuesta = $this->actingAs($this->usuario)->put('/vacaciones/'.$vacacion->id, [
            'fecha_inicio' => '2026-07-27',
            'fecha_fin' => '2026-07-29',
        ]);

        $respuesta->assertRedirect('/vacaciones')->assertSessionHas('estado', 'Vacaciones actualizadas.');

        $corregida = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Vacacion::query()->findOrFail($vacacion->id),
        );

        expect($corregida->dias())->toBe(3);
    });

    it('deja corregir la novedad de alguien que ya no está activo, sin límite de antigüedad', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1017234891'], [], terminada: true);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $this->actingAs($this->usuario)
            ->put('/vacaciones/'.$vacacion->id, ['fecha_inicio' => '2026-07-27', 'fecha_fin' => '2026-07-29'])
            ->assertRedirect('/vacaciones');
    });

    it('eliminar es borrado lógico y devuelve los días al saldo', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $vacacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($vinculacion)
            ->del27al31DeJulio()
            ->create());

        $respuesta = $this->actingAs($this->usuario)->delete('/vacaciones/'.$vacacion->id);

        $respuesta->assertRedirect('/vacaciones')->assertSessionHas('estado', 'Vacaciones eliminadas.');

        // La fila sigue ahí, marcada: nunca hay borrado físico (regla 4).
        expect(Vacacion::withoutGlobalScopes()->count())->toBe(1)
            ->and(Vacacion::withoutGlobalScopes()->whereNotNull('deleted_at')->count())->toBe(1);
    });
});

describe('la advertencia de saldo insuficiente (documento 1, 6.4)', function () {
    it('el caso de los 14 días para el 28 de agosto muestra el texto exacto con «faltan 1,25 días»', function () {
        $vinculacion = vinculacionConSaldoDeCorte();

        $franja = enLaEmpresaDelMotor(
            fn () => AdvertenciaDeSaldo::paraLaPantalla($vinculacion, 14, Carbon::parse('2026-08-28'))
        );

        $saldoDeHoy = enLaEmpresaDelMotor(
            fn () => Formato::diasDecimales(SaldoDeVacaciones::de($vinculacion)->al(Carbon::today()))
        );

        expect($franja)->toBe([
            'saldoActual' => $saldoDeHoy,
            'saldoProyectado' => '12,75 días',
            'advertencia' => 'Al 28 de agosto tendrá 12,75 días disponibles. Estás registrando 14 días: faltan 1,25 días.',
        ]);
    });

    it('no avisa nada cuando el saldo proyectado alcanza para los días solicitados', function () {
        $vinculacion = vinculacionConSaldoDeCorte();

        $franja = enLaEmpresaDelMotor(
            fn () => AdvertenciaDeSaldo::paraLaPantalla($vinculacion, 10, Carbon::parse('2026-08-28'))
        );

        expect($franja['advertencia'])->toBeNull();
    });

    it('la franja llega con solo el saldo de hoy en cuanto se resuelve el empleado, antes de tener fechas', function () {
        $vinculacion = vinculacionConSaldoDeCorte();

        $respuesta = $this->actingAs($this->usuario)
            ->get('/vacaciones?'.http_build_query(['vinculacion_id' => $vinculacion->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Vacaciones/Index',
                'X-Inertia-Partial-Data' => 'saldoDeVacaciones',
            ]);

        expect($respuesta->json()['props']['saldoDeVacaciones'])->toBe([
            'saldoActual' => enLaEmpresaDelMotor(
                fn () => Formato::diasDecimales(SaldoDeVacaciones::de($vinculacion)->al(Carbon::today()))
            ),
            'saldoProyectado' => null,
            'advertencia' => null,
        ]);
    });

    it('conecta el periodo digitado con la franja, sin volver a contar los días en la petición', function () {
        $vinculacion = vinculacionConSaldoDeCorte();

        $respuesta = $this->actingAs($this->usuario)
            ->get('/vacaciones?'.http_build_query([
                'vinculacion_id' => $vinculacion->id,
                'fecha_inicio' => '2026-08-24',
                'fecha_fin' => '2026-08-28',
            ]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Vacaciones/Index',
                'X-Inertia-Partial-Data' => 'saldoDeVacaciones',
            ]);

        $esperado = enLaEmpresaDelMotor(function () use ($vinculacion) {
            $dias = MotorDeCalculo::diasHabilesVacaciones(
                Carbon::parse('2026-08-24'),
                Carbon::parse('2026-08-28'),
                test()->empresa,
            );

            return AdvertenciaDeSaldo::paraLaPantalla($vinculacion, $dias, Carbon::parse('2026-08-28'));
        });

        expect($respuesta->json()['props']['saldoDeVacaciones'])->toBe($esperado);
    });
});
