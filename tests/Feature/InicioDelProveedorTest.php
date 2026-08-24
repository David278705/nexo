<?php

use App\Autorizacion\Rol;
use App\Dominio\Alertas\EstadoDeAlerta;
use App\Models\Acceso;
use App\Models\EjecucionDeAlertas;
use App\Models\Festivo;
use App\Models\MetricaMensual;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\PanelDeInicio;
use Illuminate\Support\Carbon;

/**
 * El inicio del panel del proveedor (F5-06, documento 4, capítulo 3): las
 * seis tarjetas de atención y los siete indicadores de negocio.
 *
 * Las seis tarjetas van siempre en el mismo orden —`PanelDeInicio::atencion()`
 * las devuelve así— y cada prueba lee la suya por índice. Igual con las siete
 * de negocio.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php`; `usuarioDe()` en
 * `RolesYPermisosTest.php`. Pest comparte las funciones de todos los archivos.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function atencion(): array
{
    return PanelDeInicio::paraLaPantalla()['atencion'];
}

function negocio(): array
{
    return PanelDeInicio::paraLaPantalla()['negocio'];
}

describe('la tarjeta de clientes en riesgo', function () {
    it('cuenta una empresa cuyo uso cayó a la mitad o menos frente al mes anterior', function () {
        $empresa = Tenant::factory()->create();

        // Diez accesos hace entre 40 y 50 días (la ventana anterior).
        Acceso::factory()->count(10)->el(Carbon::now()->subDays(45))->create(['tenant_id' => $empresa->getKey()]);
        // Uno solo en los últimos 30 días: cayó a la décima parte.
        Acceso::factory()->el(Carbon::now()->subDays(5))->create(['tenant_id' => $empresa->getKey()]);

        expect(atencion()[0]['valor'])->toBe('1')
            ->and(atencion()[0]['tono'])->toBe('error');
    });

    it('no cuenta una empresa cuyo uso se mantuvo', function () {
        $empresa = Tenant::factory()->create();

        Acceso::factory()->count(5)->el(Carbon::now()->subDays(45))->create(['tenant_id' => $empresa->getKey()]);
        Acceso::factory()->count(5)->el(Carbon::now()->subDays(5))->create(['tenant_id' => $empresa->getKey()]);

        expect(atencion()[0]['valor'])->toBe('0')->and(atencion()[0]['tono'])->toBe('');
    });

    it('no cuenta una empresa que nunca ha tenido accesos, porque no hay nada que perder', function () {
        Tenant::factory()->create();

        expect(atencion()[0]['valor'])->toBe('0');
    });

    it('deja fuera el ambiente de demostración', function () {
        $demo = Tenant::factory()->ambienteDeDemo()->create();

        Acceso::factory()->count(10)->el(Carbon::now()->subDays(45))->create(['tenant_id' => $demo->getKey()]);
        Acceso::factory()->el(Carbon::now()->subDays(5))->create(['tenant_id' => $demo->getKey()]);

        expect(atencion()[0]['valor'])->toBe('0');
    });
});

describe('la tarjeta de clientes sin arrancar', function () {
    it('cuenta una empresa creada hace más de una semana con menos de cinco empleados', function () {
        $empresa = Tenant::factory()->create(['created_at' => Carbon::now()->subDays(10)]);

        app(ContextoTenant::class)->paraTenant($empresa, fn () => Vinculacion::factory()->count(2)->create());

        expect(atencion()[1]['valor'])->toBe('1');
    });

    it('no cuenta una empresa creada hace más de una semana con cinco empleados o más', function () {
        $empresa = Tenant::factory()->create(['created_at' => Carbon::now()->subDays(10)]);

        app(ContextoTenant::class)->paraTenant($empresa, fn () => Vinculacion::factory()->count(5)->create());

        expect(atencion()[1]['valor'])->toBe('0');
    });

    it('no cuenta una empresa creada hace menos de una semana', function () {
        Tenant::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        expect(atencion()[1]['valor'])->toBe('0');
    });
});

describe('la tarjeta de pagos vencidos', function () {
    it('cuenta las suscripciones en mora y enseña el atraso más antiguo', function () {
        $plan = Plan::factory()->create();
        Suscripcion::factory()->for(Tenant::factory(), 'tenant')->for($plan, 'plan')->conMoraDe(22)->create();
        Suscripcion::factory()->for(Tenant::factory(), 'tenant')->for($plan, 'plan')->conMoraDe(5)->create();

        expect(atencion()[2]['valor'])->toBe('2')
            ->and(atencion()[2]['tono'])->toBe('error')
            ->and(atencion()[2]['etiqueta'])->toContain('22 días');
    });

    it('no marca nada cuando ninguna suscripción está en mora', function () {
        $plan = Plan::factory()->create();
        Suscripcion::factory()->for(Tenant::factory(), 'tenant')->for($plan, 'plan')->create();

        expect(atencion()[2]['valor'])->toBe('0')->and(atencion()[2]['tono'])->toBe('');
    });
});

describe('la tarjeta de alertas fallidas', function () {
    it('cuenta las alertas fallidas de las últimas 24 horas, de todas las empresas', function () {
        $empresa = Tenant::factory()->create();

        alertaDeLaEmpresa($empresa, ['estado' => EstadoDeAlerta::Fallida, 'clave' => 'una']);

        expect(atencion()[3]['valor'])->toBe('1');
    });

    it('no cuenta una alerta fallida de hace más de 24 horas', function () {
        $empresa = Tenant::factory()->create();

        $alerta = alertaDeLaEmpresa($empresa, ['estado' => EstadoDeAlerta::Fallida, 'clave' => 'vieja']);
        app(ContextoTenant::class)->paraTenant(
            $empresa,
            fn () => $alerta->forceFill(['updated_at' => Carbon::now()->subDays(2)])->save(),
        );

        expect(atencion()[3]['valor'])->toBe('0');
    });
});

describe('la tarjeta de festivos sin cargar', function () {
    it('avisa en rojo cuando falta el calendario del año en curso', function () {
        expect(atencion()[4]['tono'])->toBe('error')->and(atencion()[4]['valor'])->toBe('2026');
    });

    it('no avisa cuando el calendario está completo', function () {
        Festivo::registrar(Carbon::parse('2026-01-01'), 'Año Nuevo');
        Festivo::registrar(Carbon::parse('2027-01-01'), 'Año Nuevo');

        expect(atencion()[4]['tono'])->toBe('exito');
    });
});

describe('la tarjeta de procesos fallidos', function () {
    it('avisa en rojo cuando el proceso no ha corrido hoy', function () {
        expect(atencion()[5]['valor'])->toBe('Sin ejecutar')->and(atencion()[5]['tono'])->toBe('error');
    });

    it('avisa en rojo cuando el proceso de hoy tuvo empresas con error', function () {
        EjecucionDeAlertas::factory()->conFallos()->create(['fecha' => Carbon::today()]);

        expect(atencion()[5]['tono'])->toBe('error');
    });

    it('enseña la hora cuando el proceso de hoy corrió bien', function () {
        EjecucionDeAlertas::factory()->create([
            'fecha' => Carbon::today(),
            'ejecutada_en' => Carbon::parse('2026-08-24 06:02:00'),
        ]);

        expect(atencion()[5]['tono'])->toBe('exito')->and(atencion()[5]['valor'])->toContain('6:02');
    });
});

describe('los indicadores de negocio', function () {
    it('cuenta las empresas activas y compara contra el mes anterior', function () {
        Tenant::factory()->create();
        Tenant::factory()->create();
        MetricaMensual::factory()->create([
            'mes' => '2026-07',
            'empresas_activas' => 1,
        ]);

        expect(negocio()[0]['valor'])->toBe('2')
            ->and(negocio()[0]['variacion'])->toContain('+ 1')
            ->and(negocio()[0]['tendencia'])->toBe('sube');
    });

    it('no compara cuando no hay foto del mes anterior', function () {
        Tenant::factory()->create();

        expect(negocio()[0]['variacion'])->toBe('')->and(negocio()[0]['nota'])->not->toBe('');
    });

    it('suma los empleados de todas las empresas, recorriéndolas una por una', function () {
        $progreso = Tenant::factory()->create();
        $aurora = Tenant::factory()->create();

        app(ContextoTenant::class)->paraTenant($progreso, fn () => Vinculacion::factory()->count(3)->create());
        app(ContextoTenant::class)->paraTenant($aurora, fn () => Vinculacion::factory()->count(2)->create());

        expect(negocio()[2]['valor'])->toBe('5');
    });

    it('cuenta los usuarios administradores de todas las empresas', function () {
        $empresa = Tenant::factory()->create();
        usuarioDe($empresa, Rol::Superadministrador);
        usuarioDe($empresa, Rol::Administrador);

        expect((int) negocio()[3]['valor'])->toBeGreaterThanOrEqual(2);
    });

    it('cuenta las altas del mes y dice cero bajas mientras no exista la acción de cancelar', function () {
        Tenant::factory()->create(['created_at' => Carbon::now()]);

        expect(negocio()[4]['valor'])->toBe('1 / 0');
    });

    it('deja fuera el ambiente de demostración de las cifras de negocio', function () {
        Tenant::factory()->ambienteDeDemo()->create();

        expect(negocio()[2]['valor'])->toBe('0')
            ->and(negocio()[4]['valor'])->toBe('0 / 0');
    });
});

describe('el acceso a la pantalla', function () {
    it('renderiza el inicio del panel con sus dos bloques', function () {
        $owner = ownerEnElPanel();

        $respuesta = test()->actingAs($owner)->get('/proveedor');

        $respuesta->assertOk();

        $props = $respuesta->viewData('page')['props'];

        expect($props['atencion'])->toHaveCount(6)
            ->and($props['negocio'])->toHaveCount(7);
    });
});
