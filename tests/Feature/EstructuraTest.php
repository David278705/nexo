<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Licencias\TipoDeLicencia;
use App\Models\Tenant;
use App\Novedades\TipoDeNovedad;

/**
 * El armazón de la aplicación: barra lateral, encabezado y navegación entre
 * secciones (documento 3, capítulo 4).
 *
 * La regla de la sección 4.2 tiene dos mitades y aquí se prueban las dos: un
 * módulo sin permiso **no aparece en el menú** —no llega en la prop compartida—
 * y además **no se alcanza escribiendo la dirección**, que es el control de
 * verdad.
 *
 * `usuarioDe()` y `rutaDelModulo()` viven en `RolesYPermisosTest.php`; Pest
 * carga las funciones de todos los archivos de prueba.
 */

/**
 * Las claves de los módulos que el menú recibiría para este usuario.
 *
 * @param  array<int, array<string, mixed>>  $modulos
 * @return list<string>
 */
function clavesDelMenu(array $modulos): array
{
    return array_values(array_map(fn (array $modulo) => $modulo['clave'], $modulos));
}

it('comparte con el menú solo los módulos que el usuario puede ver', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Vacaciones, Modulo::Empleados);

    $this->actingAs($usuario)
        ->get('/')
        ->assertOk()
        ->assertInertia(function ($pagina) {
            $claves = clavesDelMenu($pagina->toArray()['props']['usuario']['modulos']);

            expect($claves)->toBe(['inicio', 'empleados', 'vacaciones']);
        });
});

it('no le muestra configuración a quien no es superadministrador', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Empleados);

    $this->actingAs($usuario)
        ->get('/')
        ->assertInertia(function ($pagina) {
            expect(clavesDelMenu($pagina->toArray()['props']['usuario']['modulos']))
                ->not->toContain('configuracion');
        });
});

it('le muestra al superadministrador las once secciones del menú', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get('/')
        ->assertInertia(function ($pagina) {
            expect(clavesDelMenu($pagina->toArray()['props']['usuario']['modulos']))
                ->toBe(array_map(fn (Modulo $modulo) => $modulo->value, Modulo::cases()));
        });
});

it('manda al menú la etiqueta, el grupo y la dirección de cada módulo', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Licencias);

    $this->actingAs($usuario)
        ->get('/')
        ->assertInertia(function ($pagina) {
            $modulos = $pagina->toArray()['props']['usuario']['modulos'];

            expect($modulos[0])->toBe([
                'clave' => 'inicio',
                'etiqueta' => 'Inicio',
                'grupo' => null,
                'url' => '/',
            ])->and($modulos[1])->toBe([
                'clave' => 'licencias',
                'etiqueta' => 'Licencias, ausencias y suspensiones',
                'grupo' => 'Novedades',
                'url' => '/licencias',
            ]);
        });
});

it('nombra las tres figuras de licencias en el menú y en los títulos del modal', function () {
    // La raíz de cada figura sale del propio selector, así que una cuarta
    // figura pone esta prueba en rojo hasta que los tres nombres la mencionen.
    // Ocho letras bastan para que «licencia» valga por «licencias» y
    // «suspensi» por «suspensión» y «suspensiones».
    $raices = array_map(
        fn (TipoDeLicencia $tipo): string => mb_substr(mb_strtolower($tipo->etiqueta()), 0, 8),
        TipoDeLicencia::cases(),
    );

    foreach ([
        Modulo::Licencias->etiqueta(),
        TipoDeNovedad::Licencia->tituloDelModal(),
        TipoDeNovedad::Licencia->tituloDeEdicion(),
    ] as $texto) {
        foreach ($raices as $raiz) {
            expect(mb_strtolower($texto))->toContain($raiz);
        }
    }
});

it('agrupa aumentos y terminaciones bajo un encabezado que no se llama como ninguno de los dos', function () {
    expect(Modulo::Movimientos->grupo())->toBe('Cambios y salidas')
        ->and(Modulo::Terminaciones->grupo())->toBe('Cambios y salidas')
        ->and(Modulo::Movimientos->etiqueta())->toBe('Movimientos')
        ->and(Modulo::Terminaciones->etiqueta())->toBe('Terminaciones');
});

it('no le da a ningún grupo del menú el nombre de una de las secciones que agrupa', function () {
    foreach (Modulo::cases() as $modulo) {
        $repetidas = array_filter(
            Modulo::cases(),
            fn (Modulo $otro) => $otro->grupo() !== null && $otro->grupo() === $modulo->etiqueta(),
        );

        expect($repetidas)->toBeEmpty("El grupo «{$modulo->etiqueta()}» repite el nombre de una sección.");
    }
});

it('manda a la barra lateral el grupo de movimientos y terminaciones con su nombre nuevo', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Movimientos, Modulo::Terminaciones);

    $this->actingAs($usuario)
        ->get('/')
        ->assertInertia(function ($pagina) {
            $grupos = collect($pagina->toArray()['props']['usuario']['modulos'])
                ->whereIn('clave', ['movimientos', 'terminaciones'])
                ->pluck('grupo', 'clave')
                ->all();

            expect($grupos)->toBe([
                'movimientos' => 'Cambios y salidas',
                'terminaciones' => 'Cambios y salidas',
            ]);
        });
});

it('muestra en el encabezado la empresa donde se está trabajando', function () {
    $empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso S.A.S.',
        'nit' => '900123456-7',
    ]);
    $usuario = usuarioDe($empresa, Rol::Administrador);

    $this->actingAs($usuario)
        ->get('/')
        ->assertInertia(fn ($pagina) => $pagina
            ->where('usuario.empresa', 'Distribuidora El Progreso S.A.S.')
            ->where('usuario.nit', '900123456-7')
            ->etc()
        );
});

it('lleva la sección de empleados a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Empleados->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Empleados/Index')->etc());
});

it('lleva la sección de incapacidades a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Incapacidades->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Incapacidades/Index')->etc());
});

it('lleva la sección de vacaciones a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Vacaciones->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Vacaciones/Index')->etc());
});

it('lleva la sección de licencias a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Licencias->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Licencias/Index')->etc());
});

it('lleva la sección de movimientos a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Movimientos->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Movimientos/Index')->etc());
});

it('lleva la sección de terminaciones a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Terminaciones->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Terminaciones/Index')->etc());
});

it('lleva la sección de alertas a su panel, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Alertas->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Alertas/Index')->etc());
});

it('lleva la sección de contratos a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Contratos->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Contratos/Index')->etc());
});

it('lleva la sección de auditoría a su listado, no a una sección pendiente', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Superadministrador);

    $this->actingAs($usuario)
        ->get(Modulo::Auditoria->url())
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Auditoria/Index')->etc());
});

it('devuelve 403 en la sección real de un módulo sin permiso', function () {
    // El menú no la pinta, pero eso es diseño. Lo que la sostiene es que la
    // dirección escrita a mano tampoco sirva.
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador, Modulo::Empleados);

    $this->actingAs($usuario)
        ->get(Modulo::Vacaciones->url())
        ->assertForbidden();
});

it('deja entrar a inicio a cualquier usuario, sin módulos asignados', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador);

    $this->actingAs($usuario)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->component('Inicio')->etc());
});

it('escribe la fecha del saludo en español', function () {
    $empresa = Tenant::factory()->create();
    $usuario = usuarioDe($empresa, Rol::Administrador);

    $this->actingAs($usuario)
        ->get('/')
        ->assertInertia(fn ($pagina) => $pagina
            ->where('fecha', fn (string $fecha) => str_contains($fecha, ' de '))
            ->etc()
        );
});
