<?php

use App\Autorizacion\Rol;
use App\Dominio\Alertas\EstadoDeAlerta;
use App\Dominio\Alertas\TipoDeAlerta;
use App\Jobs\EnviarAlerta;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * La consola de alertas del proveedor (documento 4, capítulo 7).
 *
 * Es la primera pantalla del panel que lee una tabla con el trait
 * `PerteneceATenant` y su política de RLS **a través de todas las empresas a
 * la vez**: la política no tiene excepción para el `owner`, así que no existe
 * una sola consulta posible y `ConsolaDeAlertas` recorre las empresas una por
 * una, como el proceso diario, y combina el resultado en memoria. Lo que aquí
 * se comprueba es que ese recorrido no pierde nada, no mezcla empresas y
 * responde exactamente al «hecho cuando» de la tarea.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php`; `alertaDeLaEmpresa()` en
 * `PanelDeAlertasTest.php`; `usuarioDe()` en `RolesYPermisosTest.php`. Pest
 * comparte las funciones de todos los archivos de prueba.
 */
function filasDeLaConsola(string $consulta = ''): array
{
    $respuesta = test()->get('/proveedor/alertas'.($consulta === '' ? '' : '?'.$consulta));

    $respuesta->assertOk();

    /** @var array<string, mixed> $props */
    $props = $respuesta->viewData('page')['props'];

    return $props['alertas']['filas'];
}

describe('el «hecho cuando» de la tarea', function () {
    it('responde a quién y cuándo se envió una alerta, cruzando empresas', function () {
        ownerEnElPanel();

        $progreso = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
        $aurora = Tenant::factory()->create(['nombre' => 'Textiles Aurora']);

        alertaDeLaEmpresa($progreso, [
            'destinatarios' => ['gestion@elprogreso.co'],
            'estado' => EstadoDeAlerta::Enviada,
            'enviada_en' => Carbon::parse('2026-08-17 06:02:00'),
        ]);
        alertaDeLaEmpresa($aurora, [
            'destinatarios' => ['admin@aurora.co'],
            'estado' => EstadoDeAlerta::Fallida,
            'clave' => 'otra',
        ]);

        $filas = filasDeLaConsola();

        expect($filas)->toHaveCount(2)
            ->and(array_column($filas, 'empresa'))->toEqualCanonicalizing([
                'Distribuidora El Progreso',
                'Textiles Aurora',
            ]);

        $deProgreso = array_values(array_filter($filas, fn (array $fila) => $fila['empresa'] === 'Distribuidora El Progreso'))[0];

        expect($deProgreso['destinatarios'])->toBe(['gestion@elprogreso.co'])
            ->and($deProgreso['enviada'])->toContain('17/08/2026');
    });
});

describe('los filtros', function () {
    it('filtra por empresa', function () {
        ownerEnElPanel();

        $progreso = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
        $aurora = Tenant::factory()->create(['nombre' => 'Textiles Aurora']);

        alertaDeLaEmpresa($progreso);
        alertaDeLaEmpresa($aurora, ['clave' => 'otra']);

        $filas = filasDeLaConsola('empresa='.$progreso->getKey());

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['empresa'])->toBe('Distribuidora El Progreso');
    });

    it('filtra por tipo y por estado', function () {
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();

        alertaDeLaEmpresa($empresa, [
            'tipo' => TipoDeAlerta::PlazoDeRenovacion,
            'clave' => 'una',
            'estado' => EstadoDeAlerta::Enviada,
        ]);
        alertaDeLaEmpresa($empresa, [
            'tipo' => TipoDeAlerta::AcumulacionDeQuinceDias,
            'clave' => 'otra',
            'estado' => EstadoDeAlerta::Fallida,
        ]);

        expect(filasDeLaConsola('tipo='.TipoDeAlerta::PlazoDeRenovacion->value))->toHaveCount(1)
            ->and(filasDeLaConsola('estado='.EstadoDeAlerta::Fallida->value))->toHaveCount(1);
    });

    it('filtra por rango de fechas programadas', function () {
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();

        alertaDeLaEmpresa($empresa, ['fecha_programada' => Carbon::parse('2026-07-15'), 'clave' => 'julio']);
        alertaDeLaEmpresa($empresa, ['fecha_programada' => Carbon::parse('2026-08-21'), 'clave' => 'agosto']);

        $filas = filasDeLaConsola('desde=2026-08-01');

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['programada'])->toBe('21/08/2026');
    });

    it('abre por lo más reciente entre todas las empresas', function () {
        ownerEnElPanel();
        $progreso = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
        $aurora = Tenant::factory()->create(['nombre' => 'Textiles Aurora']);

        alertaDeLaEmpresa($progreso, ['fecha_programada' => Carbon::parse('2026-07-15'), 'clave' => 'vieja']);
        alertaDeLaEmpresa($aurora, ['fecha_programada' => Carbon::parse('2026-08-21'), 'clave' => 'nueva']);

        expect(array_column(filasDeLaConsola(), 'programada'))->toBe(['21/08/2026', '15/07/2026']);
    });

    it('arma la forma de paginación aunque todo quepa en una sola página', function () {
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();

        alertaDeLaEmpresa($empresa, ['clave' => 'una']);
        alertaDeLaEmpresa($empresa, ['clave' => 'otra']);

        $respuesta = test()->get('/proveedor/alertas');

        /** @var array<string, mixed> $props */
        $props = $respuesta->viewData('page')['props'];

        expect($props['alertas']['total'])->toBe(2)
            ->and($props['alertas']['pagina'])->toBe(1)
            ->and($props['alertas']['paginas'])->toBe(1)
            ->and($props['alertas']['desde'])->toBe(1)
            ->and($props['alertas']['hasta'])->toBe(2);
    });

    it('no mezcla las alertas de una empresa con las de otra', function () {
        ownerEnElPanel();

        $progreso = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
        Tenant::factory()->create(['nombre' => 'Textiles Aurora']);

        alertaDeLaEmpresa($progreso, ['asunto' => 'Solo de El Progreso']);

        $filas = filasDeLaConsola();

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['asunto'])->toBe('Solo de El Progreso')
            ->and($filas[0]['empresa'])->toBe('Distribuidora El Progreso');
    });
});

describe('el reenvío puntual', function () {
    it('reencola una alerta fallida y la deja pendiente, sin el motivo anterior', function () {
        Queue::fake();
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();

        $alerta = alertaDeLaEmpresa($empresa, [
            'estado' => EstadoDeAlerta::Fallida,
            'motivo_del_fallo' => 'El proveedor rechazó la dirección',
        ]);

        test()->post("/proveedor/alertas/{$empresa->getKey()}/{$alerta->getKey()}/reenviar")
            ->assertSessionHas('estado');

        $actualizada = app(ContextoTenant::class)->paraTenant($empresa, fn () => $alerta->fresh());

        expect($actualizada->estado)->toBe(EstadoDeAlerta::Pendiente)
            ->and($actualizada->motivo_del_fallo)->toBeNull();

        Queue::assertPushed(
            EnviarAlerta::class,
            fn (EnviarAlerta $job) => $job->alertaId === $alerta->getKey() && $job->empresaId === $empresa->getKey(),
        );
    });

    it('también deja reenviar una que ya se marcó como enviada', function () {
        Queue::fake();
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();

        $alerta = alertaDeLaEmpresa($empresa, ['estado' => EstadoDeAlerta::Enviada]);

        test()->post("/proveedor/alertas/{$empresa->getKey()}/{$alerta->getKey()}/reenviar")
            ->assertSessionHas('estado');

        Queue::assertPushed(EnviarAlerta::class, 1);
    });

    it('no deja reenviar una que ya está pendiente de envío', function () {
        Queue::fake();
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();

        $alerta = alertaDeLaEmpresa($empresa, ['estado' => EstadoDeAlerta::Pendiente]);

        test()->post("/proveedor/alertas/{$empresa->getKey()}/{$alerta->getKey()}/reenviar")
            ->assertStatus(422);

        Queue::assertNotPushed(EnviarAlerta::class);
    });

    it('responde 404 si la alerta no es de la empresa que viene en la dirección', function () {
        ownerEnElPanel();
        $empresa = Tenant::factory()->create();
        $otra = Tenant::factory()->create();

        $alerta = alertaDeLaEmpresa($empresa, ['estado' => EstadoDeAlerta::Fallida]);

        test()->post("/proveedor/alertas/{$otra->getKey()}/{$alerta->getKey()}/reenviar")
            ->assertNotFound();
    });
});

describe('la entregabilidad del mes', function () {
    it('cuenta enviadas y fallidas de todas las empresas dentro del mes en curso', function () {
        ownerEnElPanel();
        $progreso = Tenant::factory()->create();
        $aurora = Tenant::factory()->create();

        alertaDeLaEmpresa($progreso, [
            'estado' => EstadoDeAlerta::Enviada,
            'fecha_programada' => Carbon::now()->startOfMonth()->addDay(),
        ]);
        alertaDeLaEmpresa($aurora, [
            'estado' => EstadoDeAlerta::Fallida,
            'fecha_programada' => Carbon::now()->startOfMonth()->addDays(2),
            'clave' => 'otra',
        ]);
        // Del mes anterior: no debe contar en la cifra de este mes.
        alertaDeLaEmpresa($progreso, [
            'estado' => EstadoDeAlerta::Enviada,
            'fecha_programada' => Carbon::now()->subMonths(2),
            'clave' => 'vieja',
        ]);

        $respuesta = test()->get('/proveedor/alertas');

        /** @var array<string, mixed> $props */
        $entregabilidad = $respuesta->viewData('page')['props']['entregabilidad'];

        expect($entregabilidad['enviadas'])->toBe(1)
            ->and($entregabilidad['fallidas'])->toBe(1)
            ->and($entregabilidad['porcentaje'])->toBe('50,0%');
    });

    it('no revienta cuando todavía no hay ninguna alerta resuelta este mes', function () {
        ownerEnElPanel();
        Tenant::factory()->create();

        $respuesta = test()->get('/proveedor/alertas');

        /** @var array<string, mixed> $entregabilidad */
        $entregabilidad = $respuesta->viewData('page')['props']['entregabilidad'];

        expect($entregabilidad['porcentaje'])->toBeNull();
    });
});

describe('la puerta del panel', function () {
    it('devuelve 403 a quien no es el proveedor', function () {
        $empresa = Tenant::factory()->create();
        $usuario = usuarioDe($empresa, Rol::Superadministrador);

        test()->actingAs($usuario)->get('/proveedor/alertas')->assertForbidden();
    });
});
