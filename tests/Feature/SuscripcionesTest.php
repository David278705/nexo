<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Auditoria;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\EscaleraDeMora;
use App\Proveedor\EstadoDeCobro;
use App\Proveedor\EstadoDeEmpresa;
use App\Proveedor\EstadoDeFactura;
use App\Proveedor\EstadoDeSuscripcion;
use App\Proveedor\MedioDePago;
use App\Proveedor\PanelDeSuscripciones;
use App\Proveedor\Suscripciones;
use Illuminate\Support\Carbon;

/**
 * Suscripciones y cobros (F5-04, documento 4, capítulo 6).
 *
 * El «hecho cuando» de la tarea es uno y está probado de punta a punta:
 * **suspender bloquea el acceso sin borrar nada y reactivar lo restituye**. Lo
 * demás que se prueba aquí es lo que sostiene esa frase —la escalera de mora, el
 * estado de cobro, la constancia y el historial de facturas— y el catálogo de
 * planes, que es lo que permite que la tabla de precios no necesite un
 * despliegue el día que se decida.
 *
 * `ownerEnElPanel()` y `usuarioDe()` viven en otros archivos de prueba; Pest
 * comparte las funciones de todos.
 */
beforeEach(function () {
    $this->plan = Plan::factory()->create([
        'nombre' => 'Pequeña',
        'precio_mensual' => 380000,
        'empleados_incluidos' => 50,
        'usuarios_incluidos' => 2,
    ]);
});

/**
 * Una empresa con suscripción abierta, que es el punto de partida de casi todo
 * lo de aquí.
 */
function empresaConSuscripcion(Plan $plan, array $estado = []): Suscripcion
{
    $empresa = Tenant::factory()->create(['nombre' => 'Textiles Aurora Ltda.']);

    return Suscripcion::factory()
        ->for($empresa, 'tenant')
        ->for($plan, 'plan')
        ->state($estado)
        ->create();
}

/* ─────────────────────────── El «hecho cuando» ─────────────────────────── */

it('suspender bloquea el acceso sin borrar nada, y reactivar lo restituye', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $empresa = $suscripcion->tenant()->first();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Empleados);

    // Un empleado cargado, para poder comprobar después que no se borró nada.
    app(ContextoTenant::class)->paraTenant($empresa, fn () => Vinculacion::factory()->create());

    // Antes de suspender, entra y ve su módulo.
    $this->actingAs($usuario)->get('/empleados')->assertOk();

    app(Suscripciones::class)->suspender($suscripcion, 'Mora de más de treinta días, sin acuerdo de pago.');

    // Bloquea el acceso: cualquier dirección lleva a la pantalla que lo explica.
    $this->actingAs($usuario)->get('/empleados')->assertRedirect('/suspendido');
    $this->actingAs($usuario)->get('/')->assertRedirect('/suspendido');

    // Y no borró nada: el empleado sigue ahí, con su vinculación viva.
    $vivos = app(ContextoTenant::class)->paraTenant(
        $empresa,
        fn (): int => Vinculacion::query()->count(),
    );
    expect($vivos)->toBe(1);

    app(Suscripciones::class)->reactivar($suscripcion, 'Pagó las dos facturas pendientes.');

    // Reactivar lo restituye, y en la petición siguiente: no hace falta volver
    // a entrar.
    $this->actingAs($usuario)->get('/empleados')->assertOk();
});

it('la pantalla del servicio suspendido explica que la información sigue ahí y se puede pedir', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $empresa = $suscripcion->tenant()->first();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Empleados);

    app(Suscripciones::class)->suspender($suscripcion, 'Mora de más de treinta días.');

    $this->actingAs($usuario)
        ->get('/suspendido')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('ServicioSuspendido')
            ->where('empresa', 'Textiles Aurora Ltda.')
            ->has('puntos', 3));
});

it('no deja a un usuario sin suspensión en la pantalla de suspendido', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Empleados);

    $this->actingAs($usuario)->get('/suspendido')->assertRedirect('/');
});

it('deja cerrar sesión a quien está suspendido', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $usuario = usuarioDe($suscripcion->tenant()->first(), Rol::Administrador, Modulo::Empleados);

    app(Suscripciones::class)->suspender($suscripcion, 'Mora de más de treinta días.');

    $this->actingAs($usuario)->post('/salir')->assertRedirect('/acceso');
});

it('no bloquea el panel del proveedor cuando hay empresas suspendidas', function () {
    $suscripcion = empresaConSuscripcion($this->plan);

    app(Suscripciones::class)->suspender($suscripcion, 'Mora de más de treinta días.');

    // Si el owner quedara bloqueado, la suspensión no tendría marcha atrás.
    $this->actingAs(ownerEnElPanel())->get('/proveedor/suscripciones')->assertOk();
});

/* ───────────────────── Suspensión, motivo y constancia ───────────────────── */

it('suspender mueve la suscripción y la empresa a la vez', function () {
    $suscripcion = empresaConSuscripcion($this->plan);

    app(Suscripciones::class)->suspender($suscripcion, 'Mora de más de treinta días.');

    expect($suscripcion->refresh()->estado)->toBe(EstadoDeSuscripcion::Suspendida)
        ->and($suscripcion->tenant()->first()->estado)->toBe(EstadoDeEmpresa::Suspendida);
});

it('guarda el motivo de la suspensión y deja constancia en la auditoría de esa empresa', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $empresa = $suscripcion->tenant()->first();

    $this->actingAs(ownerEnElPanel())
        ->post("/proveedor/suscripciones/{$suscripcion->id}/suspender", [
            'motivo' => 'Mora de 34 días y dos acuerdos de pago incumplidos.',
        ])
        ->assertRedirect("/proveedor/empresas/{$empresa->id}");

    expect($suscripcion->refresh()->getAttribute('motivo_de_suspension'))
        ->toBe('Mora de 34 días y dos acuerdos de pago incumplidos.');

    // La línea es de la empresa cliente, que es la que ella puede ver.
    $linea = Auditoria::query()
        ->where('modelo', Suscripcion::class)
        ->where('registro_id', (string) $suscripcion->id)
        ->where('accion', AccionAuditada::Editado->value)
        ->latest('id')
        ->first();

    expect($linea)->not->toBeNull()
        ->and($linea->getAttribute('tenant_id'))->toBe($empresa->id);
});

it('exige un motivo que se entienda para suspender', function () {
    $suscripcion = empresaConSuscripcion($this->plan);

    $this->actingAs(ownerEnElPanel())
        ->post("/proveedor/suscripciones/{$suscripcion->id}/suspender", ['motivo' => 'ok'])
        ->assertSessionHasErrors('motivo');

    expect($suscripcion->refresh()->estado)->toBe(EstadoDeSuscripcion::Activa);
});

it('reactivar borra el rastro de la suspensión anterior y no el de la reactivación', function () {
    $suscripcion = empresaConSuscripcion($this->plan, ['estado' => EstadoDeSuscripcion::Suspendida->value]);

    app(Suscripciones::class)->reactivar($suscripcion, 'Pagó las dos facturas pendientes.');

    $suscripcion->refresh();

    expect($suscripcion->estado)->toBe(EstadoDeSuscripcion::Activa)
        ->and($suscripcion->reactivada_en)->not->toBeNull()
        ->and($suscripcion->getAttribute('motivo_de_reactivacion'))
        ->toBe('Pagó las dos facturas pendientes.');
});

/* ──────────────────────────── Cobro y mora ──────────────────────────── */

it('lee el estado de cobro de la próxima fecha, no de una columna', function () {
    expect(EstadoDeCobro::segunElProximoCobro(Carbon::today()->addMonth()))
        ->toBe(EstadoDeCobro::AlDia)
        ->and(EstadoDeCobro::segunElProximoCobro(Carbon::today()->addDays(3)))
        ->toBe(EstadoDeCobro::PorVencer)
        ->and(EstadoDeCobro::segunElProximoCobro(Carbon::today()))
        ->toBe(EstadoDeCobro::PorVencer)
        ->and(EstadoDeCobro::segunElProximoCobro(Carbon::today()->subDay()))
        ->toBe(EstadoDeCobro::EnMora);
});

it('escribe los días de atraso en la celda de pago', function () {
    $suscripcion = empresaConSuscripcion($this->plan, [
        'proxima_fecha_cobro' => Carbon::today()->subDays(22),
    ]);

    expect($suscripcion->textoDelCobro())->toBe('Mora de 22 días')
        ->and($suscripcion->diasDeAtraso())->toBe(22);
});

it('avisa de la mora nombrando la empresa y el peldaño de la suspensión', function () {
    empresaConSuscripcion($this->plan, ['proxima_fecha_cobro' => Carbon::today()->subDays(22)]);

    expect(PanelDeSuscripciones::avisoDeMora())
        ->toContain('Textiles Aurora Ltda. lleva 22 días de mora')
        ->toContain('la suspensión es a los '.EscaleraDeMora::diasParaSuspender().' días')
        ->toContain('Conviene llamar antes');
});

it('dice que ya le corresponde la suspensión al pasar el último peldaño', function () {
    empresaConSuscripcion($this->plan, [
        'proxima_fecha_cobro' => Carbon::today()->subDays(EscaleraDeMora::diasParaSuspender() + 4),
    ]);

    expect(PanelDeSuscripciones::avisoDeMora())->toContain('ya le corresponde');
});

it('no suspende a nadie por llegar al último peldaño de la escalera', function () {
    $suscripcion = empresaConSuscripcion($this->plan, [
        'proxima_fecha_cobro' => Carbon::today()->subDays(EscaleraDeMora::diasParaSuspender() + 10),
    ]);

    // Mirar la pantalla no puede cortarle el servicio a nadie: el corte lo
    // ejecuta una persona, con su motivo escrito.
    $this->actingAs(ownerEnElPanel())->get('/proveedor/suscripciones')->assertOk();

    expect($suscripcion->refresh()->estado)->toBe(EstadoDeSuscripcion::Activa)
        ->and($suscripcion->tenant()->first()->estado)->toBe(EstadoDeEmpresa::Activa);
});

it('no cuenta como ingreso recurrente lo que está suspendido', function () {
    empresaConSuscripcion($this->plan);
    empresaConSuscripcion($this->plan, ['estado' => EstadoDeSuscripcion::Suspendida->value]);

    expect(PanelDeSuscripciones::ingresoRecurrente())->toBe('$ 380.000');
});

/* ─────────────────────── Facturas y pagos ─────────────────────── */

it('registrar un pago adelanta el cobro un mes desde la fecha que tenía', function () {
    $suscripcion = empresaConSuscripcion($this->plan, ['proxima_fecha_cobro' => '2026-08-01']);

    app(Suscripciones::class)->registrarPago($suscripcion, 380000, Carbon::parse('2026-08-05'), 'FE-1001');

    expect($suscripcion->refresh()->proxima_fecha_cobro->toDateString())->toBe('2026-09-01');
});

it('anular un pago lo deja en el historial y devuelve el cobro al mes anterior', function () {
    $suscripcion = empresaConSuscripcion($this->plan, ['proxima_fecha_cobro' => '2026-08-01']);
    $servicio = app(Suscripciones::class);

    $pago = $servicio->registrarPago($suscripcion, 380000, Carbon::parse('2026-08-05'), 'FE-1001');
    $servicio->anularPago($pago);

    expect($suscripcion->refresh()->proxima_fecha_cobro->toDateString())->toBe('2026-08-01')
        ->and($pago->refresh()->estado)->toBe(EstadoDeFactura::Anulada)
        // No se borra: la regla 4 del proyecto no hace excepciones con la
        // facturación, y un número de factura que desaparece no se explica.
        ->and(Pago::query()->count())->toBe(1);
});

it('no acepta dos pagos con el mismo número de factura', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    Pago::factory()->for($suscripcion, 'suscripcion')->create(['numero_factura' => 'FE-1001']);

    $this->actingAs(ownerEnElPanel())
        ->post("/proveedor/suscripciones/{$suscripcion->id}/pagos", [
            'monto' => 380000,
            'fecha' => '2026-08-05',
            'numero_factura' => 'FE-1001',
        ])
        ->assertSessionHasErrors('numero_factura');
});

it('no anula un pago de otra suscripción', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $ajena = empresaConSuscripcion($this->plan);
    $pago = Pago::factory()->for($ajena, 'suscripcion')->create();

    $this->actingAs(ownerEnElPanel())
        ->post("/proveedor/suscripciones/{$suscripcion->id}/pagos/{$pago->id}/anular")
        ->assertNotFound();

    expect($pago->refresh()->estado)->toBe(EstadoDeFactura::Pagada);
});

/* ─────────────────────── Planes y cambio de plan ─────────────────────── */

it('da de alta un plan desde el panel, sin desplegar código', function () {
    $this->actingAs(ownerEnElPanel())
        ->post('/proveedor/suscripciones/planes', [
            'nombre' => 'Mediana',
            'precio_mensual' => 720000,
            'empleados_incluidos' => 200,
            'usuarios_incluidos' => 4,
        ])
        ->assertRedirect('/proveedor/suscripciones');

    expect(Plan::query()->where('nombre', 'Mediana')->exists())->toBeTrue();
});

it('desactiva un plan en vez de borrarlo', function () {
    $this->actingAs(ownerEnElPanel())
        ->post("/proveedor/suscripciones/planes/{$this->plan->id}/estado")
        ->assertRedirect('/proveedor/suscripciones');

    expect($this->plan->refresh()->activo)->toBeFalse()
        ->and(Plan::query()->count())->toBe(1);
});

it('no ofrece un plan desactivado para contratar', function () {
    $this->plan->forceFill(['activo' => false])->save();

    expect(PanelDeSuscripciones::planesParaElegir())->toHaveCount(0);
});

it('abre la suscripción de una empresa y le copia el plan del catálogo', function () {
    $empresa = Tenant::factory()->create(['nombre' => 'Panadería La Espiga S.A.S.']);

    $this->actingAs(ownerEnElPanel())
        ->post('/proveedor/suscripciones', [
            'tenant_id' => $empresa->id,
            'plan_id' => $this->plan->id,
            'inicia_el' => '2026-08-01',
            'proxima_fecha_cobro' => '2026-09-01',
            'medio_de_pago' => MedioDePago::DebitoAutomatico->value,
        ])
        ->assertRedirect('/proveedor/suscripciones');

    $empresa->refresh();

    expect($empresa->suscripcion)->not->toBeNull()
        // El plan del catálogo pasa a ser el plan de la empresa: dos sitios que
        // digan planes distintos es justo lo que hay que evitar.
        ->and($empresa->getAttribute('plan_nombre'))->toBe('Pequeña')
        ->and($empresa->getAttribute('plan_empleados_incluidos'))->toBe(50);
});

it('no le abre una segunda suscripción a la misma empresa', function () {
    $suscripcion = empresaConSuscripcion($this->plan);

    $this->actingAs(ownerEnElPanel())
        ->post('/proveedor/suscripciones', [
            'tenant_id' => $suscripcion->getAttribute('tenant_id'),
            'plan_id' => $this->plan->id,
            'inicia_el' => '2026-08-01',
            'proxima_fecha_cobro' => '2026-09-01',
            'medio_de_pago' => MedioDePago::Transferencia->value,
        ])
        ->assertSessionHasErrors('tenant_id');

    expect(Suscripcion::query()->count())->toBe(1);
});

it('cambia de plan guardando desde cuándo aplica', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $mediana = Plan::factory()->create(['nombre' => 'Mediana', 'empleados_incluidos' => 200]);

    $this->actingAs(ownerEnElPanel())
        ->put("/proveedor/suscripciones/{$suscripcion->id}/plan", [
            'plan_id' => $mediana->id,
            'aplica_desde' => '2026-10-01',
        ])
        ->assertRedirect('/proveedor/empresas/'.$suscripcion->getAttribute('tenant_id'));

    $suscripcion->refresh();

    expect($suscripcion->getAttribute('plan_id'))->toBe($mediana->id)
        ->and($suscripcion->plan_aplica_desde->toDateString())->toBe('2026-10-01')
        ->and($suscripcion->tenant()->first()->getAttribute('plan_empleados_incluidos'))->toBe(200);
});

/* ─────────────────────── Las dos pantallas ─────────────────────── */

it('enseña la sección de suscripciones con sus tres tarjetas', function () {
    empresaConSuscripcion($this->plan, ['proxima_fecha_cobro' => Carbon::today()->subDays(22)]);

    $this->actingAs(ownerEnElPanel())
        ->get('/proveedor/suscripciones')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Proveedor/Suscripciones')
            ->where('ingresoRecurrente', '$ 380.000')
            ->has('suscripciones', 1)
            ->where('suscripciones.0.cobro', 'Mora de 22 días')
            ->has('planes', 1)
            ->has('distribucion', 1)
            ->has('escalera', 3));
});

it('enseña la pestaña de suscripción de la ficha con su historial', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    Pago::factory()->for($suscripcion, 'suscripcion')->create(['numero_factura' => 'FE-1001']);

    $this->actingAs(ownerEnElPanel())
        ->get('/proveedor/empresas/'.$suscripcion->getAttribute('tenant_id'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('suscripcion.existe', true)
            ->where('suscripcion.estado', 'Activa')
            ->has('suscripcion.historial', 1)
            ->where('suscripcion.historial.0.numeroFactura', 'FE-1001'));
});

it('dice en la ficha que a una empresa no se le cobra todavía', function () {
    $empresa = Tenant::factory()->create();

    $this->actingAs(ownerEnElPanel())
        ->get("/proveedor/empresas/{$empresa->id}")
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('suscripcion.existe', false));
});

it('filtra el listado de empresas por estado de pago', function () {
    empresaConSuscripcion($this->plan, ['proxima_fecha_cobro' => Carbon::today()->subDays(22)]);
    $alDia = Tenant::factory()->create(['nombre' => 'Constructora Vertical']);
    Suscripcion::factory()->for($alDia, 'tenant')->for($this->plan, 'plan')->create();

    $this->actingAs(ownerEnElPanel())
        ->get('/proveedor/empresas?pago='.EstadoDeCobro::EnMora->value)
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('empresas.filas', 1)
            ->where('empresas.filas.0.empresa', 'Textiles Aurora Ltda.')
            ->where('empresas.filas.0.pago', 'Mora de 22 días'));
});

it('no deja a un usuario de empresa ver ni tocar las suscripciones', function () {
    $suscripcion = empresaConSuscripcion($this->plan);
    $superadmin = usuarioDe(Tenant::factory()->create(), Rol::Superadministrador);

    // Ni la pantalla del negocio…
    $this->actingAs($superadmin)->get('/proveedor/suscripciones')->assertForbidden();

    // …ni la acción que le quitaría el servicio a otra empresa.
    $this->actingAs($superadmin)
        ->post("/proveedor/suscripciones/{$suscripcion->id}/suspender", [
            'motivo' => 'Un usuario de empresa no puede suspender a nadie.',
        ])
        ->assertForbidden();

    expect($suscripcion->refresh()->estado)->toBe(EstadoDeSuscripcion::Activa);
});

it('dice en el listado que a una empresa sin suscripción no se le cobra', function () {
    Tenant::factory()->create(['nombre' => 'Panadería La Espiga S.A.S.']);

    $this->actingAs(ownerEnElPanel())
        ->get('/proveedor/empresas')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('empresas.filas.0.pago', EstadoDeCobro::SIN_SUSCRIPCION));
});
