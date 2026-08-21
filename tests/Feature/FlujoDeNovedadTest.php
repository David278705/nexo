<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\TipoContrato;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Novedades\EmpleadoDeNovedad;
use App\Novedades\TipoDeNovedad;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * El flujo compartido de novedades, F2-03: la sección 5.5 del documento 3.
 *
 * Lo que se defiende aquí, en orden de importancia:
 *
 * 1. **La búsqueda del empleado la resuelve el servidor y es una sola**, la
 *    misma para los cinco módulos. Se pide desde cualquier pantalla del cliente
 *    con una recarga parcial, que es lo que permite validar al salir del campo
 *    sin una llamada de `axios`.
 * 2. **Un documento que no corresponde a nadie, o a alguien que ya no está
 *    activo, no deja continuar**, y el mensaje lo dice con el número o con el
 *    nombre.
 * 3. **El aislamiento entre empresas no se rompe por este camino.** El documento
 *    es único por empresa: el mismo número puede existir en dos, y cada una ve
 *    el suyo.
 * 4. **Quien no puede registrar ninguna novedad no consulta la ficha de nadie**
 *    por aquí. La ficha lleva cargo y saldo de vacaciones.
 * 5. **La ficha del modal grande sale de `FichaDeEmpleado`**, con el saldo
 *    calculado por el motor y no leído de una columna.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`: Pest carga las funciones de
 * todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Incapacidades);
});

/**
 * Un empleado de la empresa de la prueba.
 *
 * @param  array<string, mixed>  $persona
 * @param  array<string, mixed>  $vinculacion
 */
function empleadoParaNovedad(array $persona = [], array $vinculacion = [], bool $terminada = false): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(
        test()->empresa,
        function () use ($persona, $vinculacion, $terminada): Vinculacion {
            $fabrica = Vinculacion::factory()->de(Persona::factory()->create($persona));

            return ($terminada ? $fabrica->terminada() : $fabrica)->create($vinculacion);
        }
    );
}

/**
 * Pide el empleado como lo hace el modal: una recarga parcial de la pantalla en
 * la que está el listado del módulo, trayendo solo la prop del flujo.
 */
function consultarEmpleado(string $documento, ?string $url = null): TestResponse
{
    return test()
        ->actingAs(test()->usuario)
        ->get(($url ?? '/incapacidades').'?'.http_build_query(['documento' => $documento]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'SeccionPendiente',
            'X-Inertia-Partial-Data' => EmpleadoDeNovedad::PROP,
        ]);
}

/**
 * La respuesta del servidor al documento digitado, o `null`.
 *
 * @return array<string, mixed>|null
 */
function empleadoDeNovedad(string $documento, ?string $url = null): ?array
{
    /** @var array<string, mixed> $pagina */
    $pagina = consultarEmpleado($documento, $url)->json();

    return $pagina['props'][EmpleadoDeNovedad::PROP] ?? null;
}

/**
 * El valor de un renglón de la ficha del modal grande.
 *
 * @param  array<string, mixed>  $empleado
 */
function datoDeLaFicha(array $empleado, string $etiqueta): ?string
{
    foreach ($empleado['datos'] as $dato) {
        if ($dato['etiqueta'] === $etiqueta) {
            return $dato['valor'];
        }
    }

    return null;
}

describe('los cinco módulos comparten el flujo', function () {
    it('tiene un tipo de novedad por cada módulo que registra novedades', function () {
        expect(TipoDeNovedad::modulos())->toBe([
            Modulo::Incapacidades,
            Modulo::Vacaciones,
            Modulo::Licencias,
            Modulo::Movimientos,
            Modulo::Terminaciones,
        ]);
    });

    it('conserva los cinco títulos del prototipo', function () {
        $titulos = array_map(
            fn (TipoDeNovedad $tipo): string => $tipo->tituloDelModal(),
            TipoDeNovedad::cases(),
        );

        expect($titulos)->toBe([
            'Nueva incapacidad',
            'Registrar vacaciones',
            'Nueva licencia o ausencia',
            'Nuevo movimiento salarial',
            'Registrar terminación',
        ]);
    });

    it('comparte la etiqueta y la ayuda del campo con todas las pantallas', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/incapacidades');

        $flujo = $respuesta->viewData('page')['props']['flujoDeNovedad'];

        expect($flujo['etiqueta'])->toBe('Número de documento del empleado')
            ->and($flujo['ayuda'])->toBe(EmpleadoDeNovedad::AYUDA)
            ->and($flujo['tipos'])->toHaveCount(5);
    });

    it('no resuelve la búsqueda en la carga de la pantalla, solo cuando se pide', function () {
        empleadoParaNovedad(['numero_documento' => '43887201']);

        $props = $this->actingAs($this->usuario)
            ->get('/incapacidades?documento=43887201')
            ->viewData('page')['props'];

        expect($props)->not->toHaveKey(EmpleadoDeNovedad::PROP);
    });

    it('resuelve la búsqueda desde el listado de cualquiera de los cinco módulos', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, ...TipoDeNovedad::modulos());
        $this->usuario = $usuario;

        empleadoParaNovedad(['numero_documento' => '43887201']);

        foreach (TipoDeNovedad::modulos() as $modulo) {
            expect(empleadoDeNovedad('43887201', $modulo->url())['encontrado'])->toBeTrue();
        }
    });
});

describe('la validación al salir del campo', function () {
    it('no dice nada mientras el documento está vacío', function () {
        expect(empleadoDeNovedad(''))->toBeNull();
    });

    it('encuentra al empleado activo y devuelve su ficha', function () {
        empleadoParaNovedad(
            ['numero_documento' => '43887201', 'primer_nombre' => 'Carolina', 'primer_apellido' => 'Ramírez'],
            ['cargo' => 'Analista de compras'],
        );

        $empleado = empleadoDeNovedad('43887201');

        expect($empleado['encontrado'])->toBeTrue()
            ->and($empleado['error'])->toBeNull()
            ->and($empleado['nombre'])->toContain('Carolina')
            ->and(datoDeLaFicha($empleado, 'Cargo'))->toBe('Analista de compras');
    });

    it('no deja continuar con un documento que no corresponde a nadie', function () {
        $empleado = empleadoDeNovedad('99999999');

        expect($empleado['encontrado'])->toBeFalse()
            ->and($empleado['vinculacion'])->toBeNull()
            ->and($empleado['error'])->toBe(
                'El documento 99999999 no corresponde a ningún empleado activo. Verifica el número o registra primero al empleado.'
            );
    });

    it('no deja continuar con una persona cuya vinculación ya no está activa', function () {
        empleadoParaNovedad(
            ['numero_documento' => '1017234891', 'primer_nombre' => 'Juan', 'primer_apellido' => 'Pablo'],
            [],
            terminada: true,
        );

        $empleado = empleadoDeNovedad('1017234891');

        expect($empleado['encontrado'])->toBeFalse()
            ->and($empleado['error'])->toContain('su vinculación ya no está activa');
    });

    it('ignora el documento cuando llega manipulado como arreglo', function () {
        $respuesta = $this->actingAs($this->usuario)
            ->get('/incapacidades?documento[]=43887201', [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'SeccionPendiente',
                'X-Inertia-Partial-Data' => EmpleadoDeNovedad::PROP,
            ]);

        expect($respuesta->json()['props'][EmpleadoDeNovedad::PROP])->toBeNull();
    });
});

describe('la ficha del modal grande', function () {
    it('lleva los cinco renglones de la sección 5.5 y ninguno más', function () {
        empleadoParaNovedad(['numero_documento' => '43887201']);

        $etiquetas = array_column(empleadoDeNovedad('43887201')['datos'], 'etiqueta');

        expect($etiquetas)->toBe([
            'Empleado',
            'Documento',
            'Cargo',
            'Fecha de ingreso',
            'Saldo de vacaciones',
        ]);
    });

    it('trae el saldo calculado por el motor y no una columna', function () {
        // Cuatro meses cerrados desde el ingreso son cinco días: 1,25 por mes.
        Carbon::setTestNow(Carbon::parse('2026-05-15'));

        empleadoParaNovedad(
            ['numero_documento' => '43887201'],
            [
                'fecha_ingreso' => '2026-01-01',
                'tipo_contrato' => TipoContrato::Indefinido->value,
                'fecha_fin' => null,
            ],
        );

        expect(datoDeLaFicha(empleadoDeNovedad('43887201'), 'Saldo de vacaciones'))
            ->toBe('5 días');

        Carbon::setTestNow();
    });
});

describe('el aislamiento y los permisos', function () {
    it('no encuentra al empleado de otra empresa con el mismo documento', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Otra Empresa']);

        app(ContextoTenant::class)->paraTenant($otra, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create([
                'numero_documento' => '43887201',
                'primer_nombre' => 'Ajena',
            ]))
            ->create());

        expect(empleadoDeNovedad('43887201')['encontrado'])->toBeFalse();
    });

    it('no resuelve la búsqueda a quien no registra ninguna novedad', function () {
        empleadoParaNovedad(['numero_documento' => '43887201']);

        $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        expect(empleadoDeNovedad('43887201', '/empleados'))->toBeNull();
    });

    it('sí la resuelve a quien solo tiene uno de los cinco módulos', function () {
        empleadoParaNovedad(['numero_documento' => '43887201']);

        $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        expect(empleadoDeNovedad('43887201', '/vacaciones')['encontrado'])->toBeTrue();
    });
});
