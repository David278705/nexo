<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Exports\VacacionesExport;
use App\Http\Middleware\HandleInertiaRequests;
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
