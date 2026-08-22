<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\TipoContrato;
use App\Dominio\Calculo\MotorDeCalculo;
use App\Exports\LicenciasExport;
use App\Http\Middleware\HandleInertiaRequests;
use App\Licencias\ListadoDeLicencias;
use App\Models\Licencia;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * El tercer módulo de novedades (documento 1, 6.5): licencia no remunerada,
 * ausencia y suspensión, con el mismo armazón de listado (REV-01, REV-02) y
 * el mismo flujo compartido de novedades (F2-03) que incapacidades y
 * vacaciones.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Licencias);
});

function datosDeLicencia(array $cambios = []): array
{
    return [
        'tipo' => 'ausencia',
        'fecha_inicio' => '2026-07-02',
        'fecha_fin' => '2026-07-07',
        ...$cambios,
    ];
}

describe('el caso obligatorio de la sección 6.5', function () {
    it('cuenta seis días en una licencia de jueves a martes, con el domingo dentro', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Licencia::factory()
            ->de($vinculacion)
            ->delJuevesAlMartes()
            ->create());

        $respuesta = $this->actingAs($this->usuario)->get('/licencias');

        expect($respuesta->viewData('page')['props']['licencias']['filas'][0]['dias'])->toBe(6);
    });

    it('calcula los días de la novedad que se está digitando, sin restar fechas en el navegador', function () {
        $respuesta = $this->actingAs($this->usuario)
            ->get('/licencias?'.http_build_query(['fecha_inicio' => '2026-07-02', 'fecha_fin' => '2026-07-07']), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Licencias/Index',
                'X-Inertia-Partial-Data' => 'diasDeLicencia',
            ]);

        expect($respuesta->json()['props']['diasDeLicencia'])->toBe('6 días');
    });
});

describe('registrar una licencia', function () {
    it('la guarda sobre un empleado activo y confirma con un texto', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/licencias', datosDeLicencia([
            'vinculacion_id' => $vinculacion->id,
        ]));

        $respuesta->assertRedirect('/licencias')->assertSessionHas('estado', 'Novedad registrada.');

        $licencia = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Licencia::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        expect($licencia->dias())->toBe(6)
            ->and($licencia->tipo->value)->toBe('ausencia');
    });

    it('no registra sobre un documento que no corresponde a ningún empleado', function () {
        $respuesta = $this->actingAs($this->usuario)->post('/licencias', datosDeLicencia([
            'vinculacion_id' => 999999,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
        expect(Licencia::withoutGlobalScopes()->count())->toBe(0);
    });

    it('no registra sobre una vinculación cuya persona ya no está activa', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1017234891'], [], terminada: true);

        $respuesta = $this->actingAs($this->usuario)->post('/licencias', datosDeLicencia([
            'vinculacion_id' => $vinculacion->id,
        ]));

        $respuesta->assertSessionHasErrors('vinculacion_id');
    });

    it('no registra con la fecha final anterior a la inicial', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/licencias', datosDeLicencia([
            'vinculacion_id' => $vinculacion->id,
            'fecha_inicio' => '2026-07-07',
            'fecha_fin' => '2026-07-02',
        ]));

        $respuesta->assertSessionHasErrors('fecha_fin');
    });

    it('no registra con un tipo que no es ninguno de los tres', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '43887201']);

        $respuesta = $this->actingAs($this->usuario)->post('/licencias', datosDeLicencia([
            'vinculacion_id' => $vinculacion->id,
            'tipo' => 'vacaciones',
        ]));

        $respuesta->assertSessionHasErrors('tipo');
    });
});

describe('el permiso del módulo', function () {
    it('responde 403 a quien no tiene el módulo de licencias asignado', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)->get('/licencias')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/licencias/exportar')->assertForbidden();
        $this->actingAs($sinPermiso)->post('/licencias', datosDeLicencia())->assertForbidden();
    });
});

describe('la exportación', function () {
    it('trae documento, nombre, cargo y el tipo de novedad', function () {
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

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Licencia::factory()
            ->de($vinculacion)
            ->delJuevesAlMartes()
            ->create(['tipo' => 'suspension']));

        $this->actingAs($this->usuario)->get('/licencias/exportar')->assertOk();

        Excel::assertDownloaded(
            'licencias-'.now()->format('Y-m-d').'.xlsx',
            function (LicenciasExport $exportacion) {
                $fila = ListadoDeLicencias::fila($exportacion->collection()->first());

                return $fila['documento'] === '43887201'
                    && $fila['nombre'] === 'Carolina Ramírez'
                    && $fila['cargo'] === 'Analista de compras'
                    && $fila['tipo'] === 'Suspensión'
                    && $fila['dias'] === 6;
            }
        );
    });
});

describe('el efecto sobre la acumulación de vacaciones (documento 1, 6.5)', function () {
    it('reduce a la mitad la acumulación de marzo y de abril cuando la empresa tiene el parámetro activo', function () {
        $empresa = Tenant::factory()->create(['suspensiones_afectan_vacaciones' => true]);

        $vinculacion = app(ContextoTenant::class)->paraTenant($empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->conIngreso('2025-01-01')
                ->create(['tipo_contrato' => TipoContrato::Indefinido->value, 'fecha_fin' => null]);

            Licencia::factory()->de($vinculacion)->del16DeMarzoAl15DeAbril()->create();

            return $vinculacion;
        });

        $acumulacionDeMarzo = app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn () => MotorDeCalculo::acumulacionMes($vinculacion, Carbon::parse('2026-03-01')),
        );
        $acumulacionDeAbril = app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn () => MotorDeCalculo::acumulacionMes($vinculacion, Carbon::parse('2026-04-01')),
        );

        expect($acumulacionDeMarzo)->toEqualWithDelta(0.625, 0.0001)
            ->and($acumulacionDeAbril)->toEqualWithDelta(0.625, 0.0001);
    });

    it('no reduce nada cuando la empresa no tiene el parámetro activo', function () {
        $empresa = Tenant::factory()->create(['suspensiones_afectan_vacaciones' => false]);

        $vinculacion = app(ContextoTenant::class)->paraTenant($empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->conIngreso('2025-01-01')
                ->create(['tipo_contrato' => TipoContrato::Indefinido->value, 'fecha_fin' => null]);

            Licencia::factory()->de($vinculacion)->del16DeMarzoAl15DeAbril()->create();

            return $vinculacion;
        });

        $acumulacionDeMarzo = app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn () => MotorDeCalculo::acumulacionMes($vinculacion, Carbon::parse('2026-03-01')),
        );

        expect($acumulacionDeMarzo)->toEqualWithDelta(1.25, 0.0001);
    });

    it('elimina el efecto al eliminar la licencia, sin ninguna operación inversa', function () {
        $empresa = Tenant::factory()->create(['suspensiones_afectan_vacaciones' => true]);

        $vinculacion = app(ContextoTenant::class)->paraTenant($empresa, fn () => Vinculacion::factory()
            ->conIngreso('2025-01-01')
            ->create(['tipo_contrato' => TipoContrato::Indefinido->value, 'fecha_fin' => null]));

        $licencia = app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn () => Licencia::factory()->de($vinculacion)->del16DeMarzoAl15DeAbril()->create(),
        );

        app(ContextoTenant::class)->paraTenant($empresa, fn () => $licencia->delete());

        $acumulacionDeMarzo = app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn () => MotorDeCalculo::acumulacionMes($vinculacion, Carbon::parse('2026-03-01')),
        );

        expect($acumulacionDeMarzo)->toEqualWithDelta(1.25, 0.0001);
    });
});
