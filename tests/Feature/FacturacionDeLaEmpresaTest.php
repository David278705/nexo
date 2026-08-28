<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Proveedor\EstadoDeFactura;
use App\Proveedor\EstadoDeSuscripcion;
use App\Proveedor\MedioDePago;

/**
 * La pestaña «Facturación» de Configuración, REV-11: la mitad de la sección
 * 5.7 que quedó pendiente tras F1-12 y REV-10. El documento la resume en una
 * frase: «Plan contratado, número de empleados y usuarios incluidos,
 * historial de pagos y medio de pago. Solo el superadministrador.»
 *
 * Es la misma información que F5-04 ya calculó y ya probó del lado del
 * proveedor —`PanelDeSuscripciones`, `FichaDeEmpresa::suscripcion()`—, leída
 * aquí de solo lectura y acotada a la empresa del superadministrador que
 * entra. Lo que se defiende:
 *
 * 1. **El plan, sus cupos, el medio de pago y el historial de pagos** llegan
 *    completos y con el formato que ya usan las pantallas del proveedor.
 * 2. **Es del superadministrador**: el módulo `Configuracion` ya lo exige
 *    para toda la pantalla, y aquí se prueba puntualmente para esta pestaña.
 * 3. **Una empresa sin suscripción ve un estado vacío que lo dice**, no una
 *    pantalla rota ni un hueco en blanco.
 * 4. **Aislamiento**: la suscripción y los pagos de otra empresa no aparecen.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`: Pest carga las funciones de
 * todos los archivos de prueba.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso',
        'nit' => '900123456-7',
    ]);

    $this->superadmin = usuarioDe($this->empresa, Rol::Superadministrador);
});

function pantallaDeFacturacion(): array
{
    $respuesta = test()->actingAs(test()->superadmin)->get('/configuracion');

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props']['facturacion'];
}

it('responde 403 a un administrador que no es superadministrador', function () {
    $administrador = usuarioDe($this->empresa, Rol::Administrador, ...Modulo::asignables());

    $this->actingAs($administrador)->get('/configuracion')->assertForbidden();
});

it('dice que no hay plan contratado cuando la empresa no tiene suscripción', function () {
    $facturacion = pantallaDeFacturacion();

    expect($facturacion['existe'])->toBeFalse()
        ->and($facturacion['titulo'])->toContain('no hay un plan contratado');
});

it('trae el plan, sus cupos y el medio de pago', function () {
    $plan = Plan::factory()->create([
        'nombre' => 'Pequeña',
        'precio_mensual' => 380000,
        'empleados_incluidos' => 50,
        'usuarios_incluidos' => 2,
    ]);

    Suscripcion::factory()
        ->for($this->empresa, 'tenant')
        ->for($plan, 'plan')
        ->create(['medio_de_pago' => MedioDePago::Transferencia->value]);

    $facturacion = pantallaDeFacturacion();

    expect($facturacion['existe'])->toBeTrue()
        ->and($facturacion['plan'])->toBe('Pequeña')
        ->and($facturacion['estado'])->toBe('Activa');

    $datos = collect($facturacion['datos'])->pluck('valor', 'etiqueta');

    expect($datos['Empleados incluidos'])->toBe('50')
        ->and($datos['Usuarios incluidos'])->toBe('2')
        ->and($datos['Medio de pago'])->toBe('Transferencia');
});

it('trae el historial de pagos, del más reciente al más antiguo, con su estado', function () {
    $suscripcion = Suscripcion::factory()
        ->for($this->empresa, 'tenant')
        ->for(Plan::factory())
        ->create();

    $antiguo = Pago::factory()->for($suscripcion)->create([
        'fecha' => '2026-06-15',
        'numero_factura' => 'FE-0001',
    ]);
    $reciente = Pago::factory()->for($suscripcion)->create([
        'fecha' => '2026-08-15',
        'numero_factura' => 'FE-0002',
        'estado' => EstadoDeFactura::Anulada->value,
    ]);

    $facturacion = pantallaDeFacturacion();

    expect($facturacion['historial'])->toHaveCount(2)
        ->and($facturacion['historial'][0]['numeroFactura'])->toBe('FE-0002')
        ->and($facturacion['historial'][0]['estado'])->toBe('Anulada')
        ->and($facturacion['historial'][1]['numeroFactura'])->toBe('FE-0001')
        ->and($facturacion['historial'][1]['estado'])->toBe('Pagada');
});

it('no trae la suscripción ni el historial de pagos de otra empresa', function () {
    $otra = Tenant::factory()->create(['nombre' => 'Otra empresa', 'nit' => '800999111-2']);
    $plan = Plan::factory()->create();

    $suscripcionAjena = Suscripcion::factory()->for($otra, 'tenant')->for($plan, 'plan')->create();
    Pago::factory()->for($suscripcionAjena)->create();

    $facturacion = pantallaDeFacturacion();

    expect($facturacion['existe'])->toBeFalse();
});

it('marca cada estado de suscripción con el tono correcto', function () {
    Suscripcion::factory()
        ->for($this->empresa, 'tenant')
        ->for(Plan::factory())
        ->suspendida()
        ->create();

    $facturacion = pantallaDeFacturacion();

    expect($facturacion['estado'])->toBe(EstadoDeSuscripcion::Suspendida->etiqueta())
        ->and($facturacion['estadoTono'])->toBe('error');
});
