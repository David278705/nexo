<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\AdvertenciaDeReingreso;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Testing\TestResponse;

/**
 * La advertencia de reingreso, F1-05: la sección 5.6 del documento 1.
 *
 * Lo que se defiende aquí, en orden de importancia:
 *
 * 1. **El texto es el literal del documento.** Los dos párrafos se comparan
 *    carácter a carácter contra las constantes, y las constantes contra el texto
 *    escrito a mano en esta prueba. Si alguien reescribe el aviso «para que suene
 *    mejor», esto se pone en rojo.
 * 2. **El énfasis de los 30 días.** Es el caso que pide el «hecho cuando» de la
 *    tarea, y se prueba por sus dos lados: dentro de la ventana y fuera de ella.
 * 3. **No cruza empresas.** El documento de un empleado de otra empresa no
 *    produce ninguna advertencia, que sería revelar que esa persona existe.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `catalogosDelFormulario()` en
 * `RegistroDeEmpleadoTest.php`: Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);
});

/**
 * Una persona de la empresa de la prueba con una vinculación ya terminada.
 *
 * `fechaDeSalida()` sale hoy de `fecha_fin`, así que la anterior se crea a
 * término fijo: es la única modalidad que la tiene.
 */
function personaQueYaSalio(string $salida, ?Tenant $empresa = null): Persona
{
    return app(ContextoTenant::class)->paraTenant($empresa ?? test()->empresa, function () use ($salida): Persona {
        $persona = Persona::factory()->create([
            'numero_documento' => '1017245880',
            'primer_nombre' => 'Carolina',
            'segundo_nombre' => null,
            'primer_apellido' => 'Restrepo',
            'segundo_apellido' => null,
        ]);

        Vinculacion::factory()
            ->de($persona)
            ->aTerminoFijo('2024-01-15', $salida)
            ->terminada()
            ->create();

        return $persona;
    });
}

/**
 * Pide la advertencia como lo hace el asistente: una recarga parcial de la
 * pantalla del formulario que solo trae la prop `reingreso`.
 *
 * @param  array<string, string>  $datos
 */
function consultarReingreso(array $datos): TestResponse
{
    return test()
        ->actingAs(test()->usuario)
        ->get('/empleados/nuevo?'.http_build_query($datos), [
            'X-Inertia' => 'true',
            // La versión se pregunta y no se inventa: si no coincide con la del
            // manifiesto compilado, Inertia responde 409 y la prueba mediría
            // otra cosa.
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Empleados/Nuevo',
            'X-Inertia-Partial-Data' => 'reingreso',
        ]);
}

/**
 * La advertencia que devolvió el servidor, o `null` si no devolvió ninguna.
 *
 * @param  array<string, string>  $datos
 * @return array<string, mixed>|null
 */
function advertenciaDe(array $datos): ?array
{
    /** @var array<string, mixed> $pagina */
    $pagina = consultarReingreso($datos)->json();

    return $pagina['props']['reingreso'] ?? null;
}

describe('cuándo aparece', function () {
    it('no advierte nada mientras el documento está vacío', function () {
        expect(advertenciaDe(['numero_documento' => '']))->toBeNull();
    });

    it('no advierte nada con un documento que nunca se ha registrado', function () {
        personaQueYaSalio('2026-07-31');

        expect(advertenciaDe(['numero_documento' => '9999999999']))->toBeNull();
    });

    it('advierte cuando el documento ya tuvo una vinculación en la empresa', function () {
        personaQueYaSalio('2026-07-31');

        $advertencia = advertenciaDe(['numero_documento' => '1017245880']);

        expect($advertencia)->not->toBeNull()
            ->and($advertencia['nombre'])->toBe('Carolina Restrepo')
            ->and($advertencia['vinculaciones'])->toBe(1);
    });

    it('advierte también cuando la vinculación anterior sigue activa', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
            $persona = Persona::factory()->create(['numero_documento' => '1017245880']);

            Vinculacion::factory()->de($persona)->create();
        });

        expect(advertenciaDe(['numero_documento' => '1017245880']))->not->toBeNull();
    });

    it('no trae la advertencia al abrir el formulario, solo cuando se pide', function () {
        personaQueYaSalio('2026-07-31');

        $this->actingAs($this->usuario)
            ->get('/empleados/nuevo')
            ->assertInertia(fn ($pagina) => $pagina->missing('reingreso')->etc());
    });
});

describe('el texto', function () {
    it('es el literal de la sección 5.6, palabra por palabra', function () {
        expect(AdvertenciaDeReingreso::TEXTO)->toBe(
            'Esta persona ya tuvo una vinculación anterior con la empresa. Verifique si es válido pactar periodo de prueba en este contrato.'
        );
    });

    it('lleva la nota literal sobre las alertas de periodo de prueba', function () {
        expect(AdvertenciaDeReingreso::NOTA)->toBe(
            'Por configuración del sistema usted recibirá las alertas de periodo de prueba estipuladas. Sin embargo, le corresponde a usted verificar si ese periodo de prueba es válido, considerando que la persona ya tuvo una vinculación previa.'
        );
    });

    it('manda los dos textos a la pantalla sin retocarlos', function () {
        personaQueYaSalio('2026-07-31');

        $advertencia = advertenciaDe(['numero_documento' => '1017245880']);

        expect($advertencia['texto'])->toBe(AdvertenciaDeReingreso::TEXTO)
            ->and($advertencia['nota'])->toBe(AdvertenciaDeReingreso::NOTA);
    });
});

describe('el énfasis de los 30 días', function () {
    it('marca el reingreso ocurrido dentro de los 30 días siguientes a la salida', function () {
        personaQueYaSalio('2026-07-31');

        $advertencia = advertenciaDe([
            'numero_documento' => '1017245880',
            'fecha_ingreso' => '2026-08-20',
        ]);

        expect($advertencia['reciente'])->toBeTrue()
            ->and($advertencia['detalle'])->toBe(
                'El reingreso es 20 días después de la salida anterior (31 de julio de 2026), dentro de los 30 días siguientes.'
            );
    });

    it('marca también el día 30 exacto, que todavía está dentro', function () {
        personaQueYaSalio('2026-07-31');

        $advertencia = advertenciaDe([
            'numero_documento' => '1017245880',
            'fecha_ingreso' => '2026-08-30',
        ]);

        expect($advertencia['reciente'])->toBeTrue();
    });

    it('no lo marca en el día 31, que ya está fuera', function () {
        personaQueYaSalio('2026-07-31');

        $advertencia = advertenciaDe([
            'numero_documento' => '1017245880',
            'fecha_ingreso' => '2026-08-31',
        ]);

        expect($advertencia['reciente'])->toBeFalse()
            ->and($advertencia['detalle'])->toBe('La vinculación anterior terminó el 31 de julio de 2026.');
    });

    it('no lo marca cuando la salida fue hace años', function () {
        personaQueYaSalio('2020-06-30');

        $advertencia = advertenciaDe([
            'numero_documento' => '1017245880',
            'fecha_ingreso' => '2026-08-20',
        ]);

        expect($advertencia['reciente'])->toBeFalse();
    });

    it('no lo marca cuando el ingreso es anterior a la salida: eso es un dato mal digitado', function () {
        personaQueYaSalio('2026-07-31');

        $advertencia = advertenciaDe([
            'numero_documento' => '1017245880',
            'fecha_ingreso' => '2026-07-01',
        ]);

        expect($advertencia['reciente'])->toBeFalse();
    });

    it('mide contra hoy mientras la fecha de ingreso no se ha digitado', function () {
        $this->travelTo('2026-08-19');

        personaQueYaSalio('2026-08-10');

        expect(advertenciaDe(['numero_documento' => '1017245880'])['reciente'])->toBeTrue();
    });

    it('advierte sin énfasis cuando no hay fecha de salida registrada', function () {
        // Una vinculación indefinida terminada: no tiene `fecha_fin`, y la fecha
        // de salida de verdad llega con el módulo de terminaciones (F2-09).
        app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
            $persona = Persona::factory()->create(['numero_documento' => '1017245880']);

            Vinculacion::factory()->de($persona)->terminada()->create();
        });

        $advertencia = advertenciaDe(['numero_documento' => '1017245880']);

        expect($advertencia)->not->toBeNull()
            ->and($advertencia['reciente'])->toBeFalse()
            ->and($advertencia['detalle'])->toBeNull();
    });
});

describe('el aislamiento entre empresas', function () {
    it('no advierte por el documento de un empleado de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Ferretería La Esquina']);

        personaQueYaSalio('2026-07-31', $otra);

        expect(advertenciaDe(['numero_documento' => '1017245880']))->toBeNull();
    });

    it('no deja que la advertencia diga el nombre de una persona de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Ferretería La Esquina']);

        personaQueYaSalio('2026-07-31', $otra);

        consultarReingreso(['numero_documento' => '1017245880'])
            ->assertDontSee('Carolina');
    });
});
