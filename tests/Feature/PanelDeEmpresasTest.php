<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Rol;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\Auditoria;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Notificaciones\InvitacionAlSistema;
use App\Notificaciones\RestablecerContrasena;
use App\Proveedor\ListadoDeEmpresas;
use App\Proveedor\SaludDeUso;
use Illuminate\Support\Facades\Notification;

/**
 * El listado y la ficha de empresas del panel del proveedor (documento 4, 4.1 y
 * 4.3).
 *
 * Lo que aquí se comprueba, en el orden en que importa:
 *
 * 1. **El listado enseña datos reales**, no un contador que nadie actualiza: los
 *    empleados se cuentan dentro del contexto de cada empresa.
 * 2. **La salud de uso reparte las tres franjas** según el último ingreso, y el
 *    filtro devuelve exactamente lo que la tabla pinta.
 * 3. **Un parámetro se edita desde la ficha** y el cambio queda en la auditoría
 *    **de esa empresa**, que es lo que el cliente puede ver.
 * 4. **Nadie ve una contraseña.** Las dos acciones de la pestaña de usuarios
 *    mandan enlaces al buzón del usuario y nada más.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php` y `usuarioDe()` en
 * `RolesYPermisosTest.php`; Pest comparte las funciones de todos los archivos.
 */

/**
 * Hace algo dentro del contexto de una empresa, como hace el panel.
 */
function enEmpresa(Tenant $empresa, Closure $accion): mixed
{
    return app(ContextoTenant::class)->paraTenant($empresa, $accion);
}

/**
 * Una empresa con su plan, sus empleados y su último ingreso.
 */
function empresaConEmpleados(string $nombre, int $empleados, int $incluidos = 50, int $diasSinEntrar = 0): Tenant
{
    $empresa = Tenant::factory()
        ->conPlan('Pequeña', $incluidos)
        ->conUltimoIngresoHace($diasSinEntrar)
        ->create(['nombre' => $nombre]);

    enEmpresa($empresa, function () use ($empleados): void {
        Vinculacion::factory()->count($empleados)->create();
    });

    return $empresa;
}

describe('el listado de empresas', function () {
    it('cuenta los empleados de cada empresa dentro de su propio contexto', function () {
        ownerEnElPanel();

        empresaConEmpleados('Distribuidora El Progreso', 3);
        empresaConEmpleados('Textiles Aurora', 1);

        $this->get('/proveedor/empresas')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Proveedor/Empresas')
                ->has('empresas.filas', 2)
                ->where('empresas.filas.0.empresa', 'Distribuidora El Progreso')
                ->where('empresas.filas.0.empleados', '3 de 50')
                ->where('empresas.filas.1.empleados', '1 de 50'));
    });

    it('resalta a la empresa que ya superó los empleados de su plan', function () {
        ownerEnElPanel();

        empresaConEmpleados('Textiles Aurora', 3, incluidos: 2);
        empresaConEmpleados('Zapatos del Sur', 1, incluidos: 2);

        $this->get('/proveedor/empresas')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('empresas.filas.0.excedePlan', true)
                ->where('empresas.filas.1.excedePlan', false));
    });

    it('declara que el pago todavía no tiene de dónde salir en vez de inventarlo', function () {
        ownerEnElPanel();

        empresaConEmpleados('Distribuidora El Progreso', 1);

        $this->get('/proveedor/empresas')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('empresas.filas.0.pago', ListadoDeEmpresas::PAGO_PENDIENTE)
                ->where('pagoPendiente', ListadoDeEmpresas::PAGO_PENDIENTE));
    });

    it('reparte las tres franjas de salud de uso según el último ingreso', function () {
        ownerEnElPanel();

        $reciente = Tenant::factory()->conUltimoIngresoHace(1)->create();
        $tibia = Tenant::factory()->conUltimoIngresoHace(15)->create();
        $fria = Tenant::factory()->conUltimoIngresoHace(60)->create();
        $nunca = Tenant::factory()->create();

        expect(SaludDeUso::segunElUltimoIngreso($reciente->ultimo_acceso_en))->toBe(SaludDeUso::Activa)
            ->and(SaludDeUso::segunElUltimoIngreso($tibia->ultimo_acceso_en))->toBe(SaludDeUso::UsoBajo)
            ->and(SaludDeUso::segunElUltimoIngreso($fria->ultimo_acceso_en))->toBe(SaludDeUso::SinUso)
            // La empresa en la que no ha entrado nadie es «sin uso», y la
            // columna de al lado dice «Nunca».
            ->and(SaludDeUso::segunElUltimoIngreso($nunca->ultimo_acceso_en))->toBe(SaludDeUso::SinUso)
            ->and(SaludDeUso::textoDelUltimoIngreso($nunca->ultimo_acceso_en))->toBe('Nunca');
    });

    it('filtra por salud de uso devolviendo lo mismo que la tabla pinta', function () {
        ownerEnElPanel();

        Tenant::factory()->conUltimoIngresoHace(1)->create(['nombre' => 'Al día']);
        Tenant::factory()->conUltimoIngresoHace(60)->create(['nombre' => 'Abandonada']);
        Tenant::factory()->create(['nombre' => 'Nunca entró']);

        $this->get('/proveedor/empresas?salud='.SaludDeUso::SinUso->value)
            ->assertInertia(fn ($pagina) => $pagina
                ->has('empresas.filas', 2)
                ->where('empresas.filas.0.uso', 'Sin uso')
                ->where('empresas.filas.1.uso', 'Sin uso'));
    });

    it('filtra por estado y por plan, y busca por nombre o por NIT', function () {
        ownerEnElPanel();

        Tenant::factory()->conPlan('Micro', 10)->create(['nombre' => 'Panadería La Espiga', 'nit' => '900111111-1']);
        Tenant::factory()->conPlan('Mediana', 200)->suspendida()->create(['nombre' => 'Textiles Aurora', 'nit' => '900222222-2']);
        Tenant::factory()->conPlan('Micro', 10)->enPrueba()->create(['nombre' => 'Logística del Valle', 'nit' => '900333333-3']);

        $this->get('/proveedor/empresas?estado=suspendida')
            ->assertInertia(fn ($pagina) => $pagina
                ->has('empresas.filas', 1)
                ->where('empresas.filas.0.empresa', 'Textiles Aurora'));

        $this->get('/proveedor/empresas?plan=Micro')
            ->assertInertia(fn ($pagina) => $pagina->has('empresas.filas', 2));

        $this->get('/proveedor/empresas?buscar=900222222')
            ->assertInertia(fn ($pagina) => $pagina
                ->has('empresas.filas', 1)
                ->where('empresas.filas.0.empresa', 'Textiles Aurora'));

        $this->get('/proveedor/empresas?buscar=espiga')
            ->assertInertia(fn ($pagina) => $pagina
                ->has('empresas.filas', 1)
                ->where('empresas.filas.0.empresa', 'Panadería La Espiga'));
    });

    it('cuenta las empresas por estado en el encabezado', function () {
        ownerEnElPanel();

        Tenant::factory()->count(2)->create();
        Tenant::factory()->suspendida()->create();

        $this->get('/proveedor/empresas')
            ->assertInertia(fn ($pagina) => $pagina
                // El singular importa: «1 suspendidas» delata una frase armada
                // con una concatenación.
                ->where('conteos.0.texto', '2 activas')
                ->where('conteos.1.texto', '1 suspendida')
                ->where('conteos.2.texto', '0 en prueba'));
    });
});

describe('la ficha de la empresa', function () {
    it('enseña las siete pestañas del documento y quién dio de alta a la empresa', function () {
        Notification::fake();

        $owner = ownerEnElPanel();

        $this->post('/proveedor/empresas', datosDeEmpresa());

        $empresa = Tenant::query()->where('nit', '900123456-7')->firstOrFail();

        $this->get('/proveedor/empresas/'.$empresa->getKey())
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Proveedor/FichaDeEmpresa')
                ->has('pestanas', 7)
                ->where('empresa.nombre', 'Distribuidora El Progreso S.A.S.')
                ->where('resumen.alta.1', ['etiqueta' => 'Creada por', 'valor' => $owner->name])
                // Las tres pestañas que dependen de tareas futuras lo dicen.
                ->has('pendientes.suscripcion')
                ->has('pendientes.alertas')
                ->has('pendientes.bitacora'));
    });

    it('deja al owner editar un parámetro y lo anota en la auditoría de esa empresa', function () {
        ownerEnElPanel();

        $empresa = Tenant::factory()->create(['sabado_laboral' => false]);

        $this->put('/proveedor/empresas/'.$empresa->getKey().'/parametros', [
            'sabado_laboral' => ParametrosDeLaEmpresa::SI,
            'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::NO,
            'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::NO,
        ])->assertRedirect('/proveedor/empresas/'.$empresa->getKey());

        expect($empresa->fresh()->getAttribute('sabado_laboral'))->toBeTrue();

        // La constancia que pide el documento: la línea es de la empresa tocada
        // —el cliente la ve en su propia auditoría— y el autor es el proveedor.
        $linea = Auditoria::query()
            ->where('modelo', Tenant::class)
            ->where('registro_id', (string) $empresa->getKey())
            ->where('accion', AccionAuditada::Editado->value)
            ->firstOrFail();

        expect($linea->getAttribute('tenant_id'))->toBe($empresa->getKey())
            ->and($linea->valores_nuevos)->toHaveKey('sabado_laboral');
    });

    it('enseña el último acceso de cada usuario de la empresa', function () {
        ownerEnElPanel();

        $empresa = Tenant::factory()->create();
        $usuario = usuarioDe($empresa, Rol::Superadministrador);

        $this->get('/proveedor/empresas/'.$empresa->getKey())
            ->assertInertia(fn ($pagina) => $pagina
                ->has('usuarios', 1)
                ->where('usuarios.0.correo', $usuario->email)
                ->where('usuarios.0.ultimoAcceso', 'Nunca'));
    });
});

describe('las dos acciones sobre un usuario del cliente', function () {
    it('reenvía la invitación sin enseñar ninguna contraseña', function () {
        Notification::fake();

        ownerEnElPanel();

        $empresa = Tenant::factory()->create();
        $usuario = usuarioDe($empresa, Rol::Superadministrador);

        $respuesta = $this->post(
            '/proveedor/empresas/'.$empresa->getKey().'/usuarios/'.$usuario->getKey().'/invitacion'
        );

        $respuesta->assertRedirect('/proveedor/empresas/'.$empresa->getKey());

        Notification::assertSentTo($usuario, InvitacionAlSistema::class);

        expect(session('estado'))->not->toContain($usuario->getAttribute('password'));
    });

    it('manda el enlace de contraseña al buzón del usuario y no al proveedor', function () {
        Notification::fake();

        ownerEnElPanel();

        $empresa = Tenant::factory()->create();
        $usuario = usuarioDe($empresa, Rol::Superadministrador);

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/usuarios/'.$usuario->getKey().'/contrasena')
            ->assertRedirect('/proveedor/empresas/'.$empresa->getKey());

        // La del sistema, con los textos del documento 3, no la de Laravel.
        Notification::assertSentTo($usuario, RestablecerContrasena::class);
    });

    it('no deja tocar a un usuario que no es de esa empresa', function () {
        Notification::fake();

        ownerEnElPanel();

        $empresa = Tenant::factory()->create();
        $otra = Tenant::factory()->create();
        $ajeno = usuarioDe($otra, Rol::Superadministrador);

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/usuarios/'.$ajeno->getKey().'/invitacion')
            ->assertNotFound();

        $this->post('/proveedor/empresas/'.$empresa->getKey().'/usuarios/'.$ajeno->getKey().'/contrasena')
            ->assertNotFound();

        Notification::assertNothingSent();
    });
});

describe('la puerta del panel', function () {
    it('devuelve 403 al superadministrador que intenta ver la ficha de su propia empresa', function () {
        $empresa = Tenant::factory()->create();
        $superadministrador = usuarioDe($empresa, Rol::Superadministrador);

        $this->actingAs($superadministrador)
            ->get('/proveedor/empresas/'.$empresa->getKey())
            ->assertForbidden();

        $this->actingAs($superadministrador)
            ->put('/proveedor/empresas/'.$empresa->getKey().'/parametros', [
                'sabado_laboral' => ParametrosDeLaEmpresa::SI,
                'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::SI,
                'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::SI,
            ])
            ->assertForbidden();

        expect($empresa->fresh()->getAttribute('sabado_laboral'))->toBeFalse();
    });
});

describe('el último ingreso de una empresa', function () {
    it('lo anota el propio inicio de sesión, que es cuando ocurre', function () {
        $empresa = Tenant::factory()->create();
        $usuario = usuarioDe($empresa, Rol::Superadministrador);

        expect($empresa->getAttribute('ultimo_acceso_en'))->toBeNull();

        $this->post('/acceso', [
            'correo' => $usuario->email,
            'contrasena' => 'password',
        ]);

        expect($empresa->fresh()->getAttribute('ultimo_acceso_en'))->not->toBeNull();
    });

    it('no anota nada cuando quien entra es el proveedor', function () {
        [$owner] = ownerVerificable();

        $this->post('/acceso', ['correo' => $owner->email, 'contrasena' => 'password']);

        // El `owner` no tiene empresa: entrar al panel no es uso del producto
        // por parte de ningún cliente.
        expect(Tenant::query()->whereNotNull('ultimo_acceso_en')->count())->toBe(0);
    });
});
