<?php

use App\Autorizacion\Rol;
use App\Configuracion\ParametrosDeLaEmpresa;
use App\Models\Ciudad;
use App\Models\Tenant;
use App\Models\User;
use App\Notificaciones\InvitacionAlSistema;
use App\Proveedor\CreacionDeEmpresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Crear una empresa desde el panel del proveedor (documento 4, sección 4.2).
 *
 * Es la pantalla sin la cual no hay cliente posible, y lo que aquí se comprueba
 * es exactamente eso: que una empresa entera —datos, parámetros, plan y la
 * cuenta de quien va a entrar— se crea desde la interfaz **sin tocar la base de
 * datos**, y que si algo falla no queda nada a medias.
 *
 * `ownerVerificable()` vive en `PanelDelProveedorTest.php`; Pest comparte las
 * funciones de todos los archivos de prueba.
 */

/**
 * Un `owner` con el código del segundo factor ya escrito en su sesión, que es
 * el único estado desde el que se ve el panel.
 */
function ownerEnElPanel(): User
{
    [$owner, $codigo] = ownerVerificable();

    test()->actingAs($owner)->post('/proveedor/verificacion', ['codigo' => $codigo]);

    return $owner;
}

/**
 * Los datos de una empresa completa, tal como los manda el formulario.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosDeEmpresa(array $cambios = []): array
{
    $ciudad = Ciudad::factory()->create();

    return [
        'nombre' => 'Distribuidora El Progreso S.A.S.',
        'nit' => '900123456-7',
        'telefono' => '604 444 5566',
        'ciudad_id' => $ciudad->getKey(),

        'sabado_laboral' => ParametrosDeLaEmpresa::SI,
        'suspensiones_afectan_vacaciones' => ParametrosDeLaEmpresa::NO,
        'perfil_sociodemografico_habilitado' => ParametrosDeLaEmpresa::SI,

        'plan_nombre' => 'Pequeña — hasta 50 empleados',
        'plan_empleados_incluidos' => 50,
        'plan_usuarios_incluidos' => 2,
        'plan_inicia_el' => '2026-09-01',

        'superadministrador' => [
            'nombre' => 'Ana María Gil',
            'correo' => 'ana@elprogreso.com',
        ],

        ...$cambios,
    ];
}

it('enseña el asistente de los tres pasos del documento', function () {
    ownerEnElPanel();

    $this->get('/proveedor/empresas/nueva')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Proveedor/CrearEmpresa')
            ->where('pasos', CreacionDeEmpresa::PASOS)
            ->has('parametros', 3)
            ->has('ubicaciones.departamentos'));
});

it('ofrece crear empresa desde la sección de empresas', function () {
    ownerEnElPanel();

    $this->get('/proveedor/empresas')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('accion.url', '/proveedor/empresas/nueva'));
});

it('crea la empresa y su superadministrador, sin enviarle todavía la invitación (PROD-22)', function () {
    Notification::fake();

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa())->assertRedirect();

    $empresa = Tenant::query()->where('nit', '900123456-7')->firstOrFail();

    expect($empresa->getAttribute('nombre'))->toBe('Distribuidora El Progreso S.A.S.')
        ->and($empresa->estaActiva())->toBeTrue()
        // Los tres parámetros llegaron como `si` y `no` y quedaron booleanos.
        ->and($empresa->getAttribute('sabado_laboral'))->toBeTrue()
        ->and($empresa->getAttribute('suspensiones_afectan_vacaciones'))->toBeFalse()
        ->and($empresa->getAttribute('perfil_sociodemografico_habilitado'))->toBeTrue()
        ->and($empresa->getAttribute('plan_empleados_incluidos'))->toBe(50);

    $cuenta = CreacionDeEmpresa::superadministradorDe($empresa);

    expect($cuenta)->not->toBeNull()
        ->and($cuenta->getAttribute('email'))->toBe('ana@elprogreso.com')
        ->and($cuenta->rol)->toBe(Rol::Superadministrador);

    // La invitación sale desde la ficha cuando la carga inicial esté lista:
    // un cliente que entra a un sistema vacío concluye que no sirve.
    Notification::assertNotSentTo($cuenta, InvitacionAlSistema::class);
});

it('envía la invitación desde la ficha cuando el proveedor decide', function () {
    Notification::fake();

    ownerEnElPanel();
    $this->post('/proveedor/empresas', datosDeEmpresa());
    $empresa = Tenant::query()->where('nit', '900123456-7')->firstOrFail();
    $cuenta = CreacionDeEmpresa::superadministradorDe($empresa);

    $this->post("/proveedor/empresas/{$empresa->getKey()}/usuarios/{$cuenta->getKey()}/invitacion")
        ->assertRedirect()
        ->assertSessionHas('estado', fn (string $estado) => str_contains($estado, 'Se envió la invitación a ana@elprogreso.com'));

    Notification::assertSentTo($cuenta, InvitacionAlSistema::class);
});

it('no deja a nadie con una contraseña que el proveedor conozca', function () {
    Notification::fake();

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa());

    $cuenta = User::query()->where('email', 'ana@elprogreso.com')->firstOrFail();

    // La contraseña es azar puro y nadie la escribió: se entra con el enlace de
    // la invitación, que es lo que promete la ficha de la empresa.
    expect($cuenta->getAttribute('password'))->not->toBeEmpty();

    foreach (['ana@elprogreso.com', 'Ana María Gil', '900123456-7'] as $adivinable) {
        expect(auth()->attempt(['email' => 'ana@elprogreso.com', 'password' => $adivinable]))
            ->toBeFalse();
    }
});

it('lleva al resumen que se le lee al cliente en voz alta', function () {
    Notification::fake();

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa())->assertRedirect();

    $creada = Tenant::query()->where('nit', '900123456-7')->firstOrFail();

    $this->get("/proveedor/empresas/{$creada->getKey()}/resumen")
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Proveedor/ResumenDeEmpresa')
            ->where('titulo', CreacionDeEmpresa::TITULO_DEL_RESUMEN)
            ->where('empresa.nombre', 'Distribuidora El Progreso S.A.S.'));

    // Los parámetros se leen como acuerdo, no como `true` y `false`: es lo que
    // evita la discusión de «yo nunca dije que el sábado fuera laboral».
    $texto = CreacionDeEmpresa::textoParaCopiar($creada);

    expect($texto)->toContain('Sí, el sábado es día laboral')
        ->toContain('No, no la afectan')
        ->toContain('ana@elprogreso.com')
        ->toContain('Pequeña — hasta 50 empleados');
});

it('no escribe nada si el correo del superadministrador ya está en uso', function () {
    Notification::fake();

    $otra = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $otra->getKey(), 'email' => 'ana@elprogreso.com']);

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa())
        ->assertSessionHasErrors('superadministrador.correo');

    expect(Tenant::query()->where('nit', '900123456-7')->exists())->toBeFalse();
});

it('no admite dos empresas con el mismo NIT', function () {
    Notification::fake();

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa())->assertRedirect();

    $this->post('/proveedor/empresas', datosDeEmpresa([
        'superadministrador' => ['nombre' => 'Otro', 'correo' => 'otro@elprogreso.com'],
    ]))->assertSessionHasErrors('nit');

    expect(Tenant::query()->where('nit', '900123456-7')->count())->toBe(1);
});

it('guarda el certificado de cámara de comercio en el disco privado', function () {
    Notification::fake();
    Storage::fake(CreacionDeEmpresa::discoDeCertificados());

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa([
        'certificado' => UploadedFile::fake()->create('camara.pdf', 12, 'application/pdf'),
    ]))->assertRedirect();

    $empresa = Tenant::query()->where('nit', '900123456-7')->firstOrFail();

    expect($empresa->tieneCertificadoDeCamara())->toBeTrue()
        ->and($empresa->getAttribute('certificado_camara_nombre'))->toBe('camara.pdf');

    Storage::disk(CreacionDeEmpresa::discoDeCertificados())
        ->assertExists((string) $empresa->getAttribute('certificado_camara_ruta'));

    $this->get("/proveedor/empresas/{$empresa->getKey()}/certificado")->assertOk();
});

it('crea la empresa aunque el certificado todavía no esté, y lo dice en el resumen', function () {
    Notification::fake();

    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa())->assertRedirect();

    $empresa = Tenant::query()->where('nit', '900123456-7')->firstOrFail();

    expect($empresa->tieneCertificadoDeCamara())->toBeFalse()
        ->and(CreacionDeEmpresa::textoParaCopiar($empresa))
        ->toContain(CreacionDeEmpresa::SIN_CERTIFICADO);

    $this->get("/proveedor/empresas/{$empresa->getKey()}/certificado")->assertNotFound();
});

it('no deja crear empresas a un usuario de empresa', function () {
    $empresa = Tenant::factory()->create();
    $superadministrador = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($superadministrador)->get('/proveedor/empresas/nueva')->assertForbidden();
    $this->actingAs($superadministrador)->post('/proveedor/empresas', datosDeEmpresa())
        ->assertForbidden();

    expect(Tenant::query()->where('nit', '900123456-7')->exists())->toBeFalse();
});

it('no deja crear empresas al owner que todavía no escribió su código', function () {
    [$owner] = ownerVerificable();

    $this->actingAs($owner)->get('/proveedor/empresas/nueva')
        ->assertRedirect('/proveedor/verificacion');
});

it('deja constancia en la auditoría de la empresa recién creada', function () {
    Notification::fake();

    $owner = ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa())->assertRedirect();

    $empresa = Tenant::query()->where('nit', '900123456-7')->firstOrFail();

    $this->assertDatabaseHas('auditoria', [
        'tenant_id' => $empresa->getKey(),
        'user_id' => $owner->getKey(),
        'modelo' => Tenant::class,
        'accion' => 'creado',
    ]);
});

it('exige los datos de los tres pasos', function () {
    ownerEnElPanel();

    $this->post('/proveedor/empresas', [])->assertSessionHasErrors([
        'nombre',
        'nit',
        'telefono',
        'ciudad_id',
        'sabado_laboral',
        'plan_nombre',
        'plan_empleados_incluidos',
        'plan_usuarios_incluidos',
        'plan_inicia_el',
        'superadministrador.nombre',
        'superadministrador.correo',
    ]);
});

it('rechaza un certificado que no es un documento', function () {
    ownerEnElPanel();

    $this->post('/proveedor/empresas', datosDeEmpresa([
        'certificado' => UploadedFile::fake()->create('lista.exe', 4),
    ]))->assertSessionHasErrors('certificado');

    expect(Tenant::query()->where('nit', '900123456-7')->exists())->toBeFalse();
});
