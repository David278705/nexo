<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EstadoVinculacion;
use App\Contratacion\ListadoDeEmpleados;
use App\Exports\EmpleadosExport;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Maatwebsite\Excel\Facades\Excel;

/**
 * El listado de empleados (documento 3, sección 5.3).
 *
 * Lo que se defiende aquí es lo que la tarea F1-03 promete: que **los filtros
 * se resuelven en el servidor** y que **la exportación trae exactamente lo
 * filtrado**. Un filtro que solo esconda filas en el navegador dejaría estas
 * pruebas en verde solo si se escribieran contra la pantalla; por eso se
 * escriben contra las props que devuelve el controlador.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`: Pest carga las funciones de
 * todos los archivos de prueba.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);
});

/**
 * Crea un empleado en la empresa de la prueba y devuelve su vinculación.
 *
 * @param  array<string, mixed>  $persona
 * @param  array<string, mixed>  $vinculacion
 */
function empleadoDe(array $persona = [], array $vinculacion = []): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function () use ($persona, $vinculacion) {
        return Vinculacion::factory()
            ->de(Persona::factory()->create($persona))
            ->create($vinculacion);
    });
}

/**
 * Las filas que el servidor manda a la pantalla con los filtros dados.
 *
 * @param  array<string, mixed>  $filtros
 * @return list<array<string, mixed>>
 */
function filasDelListado(array $filtros = []): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/empleados?'.http_build_query($filtros));

    $respuesta->assertOk();

    $props = $respuesta->viewData('page')['props'];

    return $props['empleados']['filas'];
}

/**
 * @param  list<array<string, mixed>>  $filas
 * @return list<string>
 */
function nombresDe(array $filas): array
{
    return array_values(array_map(fn (array $fila) => $fila['nombre'], $filas));
}

describe('las columnas del listado', function () {
    it('trae documento, nombre, cargo, tipo de contrato, fecha de ingreso y estado', function () {
        empleadoDe(
            ['numero_documento' => '43887201', 'primer_nombre' => 'Carolina', 'segundo_nombre' => null, 'primer_apellido' => 'Restrepo', 'segundo_apellido' => 'Vélez'],
            ['cargo' => 'Coordinadora de Operaciones', 'fecha_ingreso' => '2022-02-03'],
        );

        expect(filasDelListado()[0])->toMatchArray([
            'documento' => '43887201',
            'nombre' => 'Carolina Restrepo Vélez',
            'cargo' => 'Coordinadora de Operaciones',
            'contrato' => 'Término indefinido',
            'ingreso' => '03/02/2022',
            'estado' => 'Activo',
        ]);
    });

    it('cuenta dos veces a quien tuvo dos vinculaciones, porque una fila es un vínculo', function () {
        $persona = app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $persona = Persona::factory()->create(['primer_nombre' => 'Andrés', 'primer_apellido' => 'Gómez']);

            Vinculacion::factory()->de($persona)->conIngreso('2019-09-01')->create();
            Vinculacion::factory()->de($persona)->conIngreso('2024-03-01')->create();

            return $persona;
        });

        expect($persona)->not->toBeNull()
            ->and(filasDelListado())->toHaveCount(2);
    });

    it('muestra el nombre sin dobles espacios cuando no hay segundos nombres', function () {
        empleadoDe(['primer_nombre' => 'Ramiro', 'segundo_nombre' => null, 'primer_apellido' => 'Peña', 'segundo_apellido' => null]);

        expect(filasDelListado()[0]['nombre'])->toBe('Ramiro Peña');
    });
});

describe('los filtros, resueltos en el servidor', function () {
    it('muestra solo a los activos mientras no se diga otra cosa', function () {
        empleadoDe(['primer_nombre' => 'Activa', 'primer_apellido' => 'Uno']);
        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['primer_nombre' => 'Inactiva', 'primer_apellido' => 'Dos']))
            ->terminada()
            ->create());

        expect(nombresDe(filasDelListado()))->toHaveCount(1)
            ->and(filasDelListado()[0]['estado'])->toBe('Activo');
    });

    it('deja ver a los inactivos y a todos', function () {
        empleadoDe(['primer_nombre' => 'Activa', 'primer_apellido' => 'Uno']);
        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['primer_nombre' => 'Inactiva', 'primer_apellido' => 'Dos']))
            ->terminada()
            ->create());

        expect(filasDelListado(['estado' => EstadoVinculacion::Inactivo->value]))->toHaveCount(1)
            ->and(filasDelListado(['estado' => EstadoVinculacion::Inactivo->value])[0]['estado'])->toBe('Inactivo')
            ->and(filasDelListado(['estado' => ListadoDeEmpleados::TODOS]))->toHaveCount(2);
    });

    it('filtra por tipo de contrato', function () {
        empleadoDe(['primer_apellido' => 'Indefinida']);
        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['primer_apellido' => 'Fija']))
            ->aTerminoFijo()
            ->create());

        $filas = filasDelListado(['contrato' => 'fijo']);

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['contrato'])->toBe('Término fijo');
    });

    it('filtra por cargo exacto', function () {
        empleadoDe([], ['cargo' => 'Conductor']);
        empleadoDe([], ['cargo' => 'Analista de Compras']);

        $filas = filasDelListado(['cargo' => 'Conductor']);

        expect($filas)->toHaveCount(1)
            ->and($filas[0]['cargo'])->toBe('Conductor');
    });

    it('filtra por rango de fecha de ingreso incluyendo los dos extremos', function () {
        empleadoDe(['primer_apellido' => 'Antes'], ['fecha_ingreso' => '2024-12-31']);
        empleadoDe(['primer_apellido' => 'Inicio'], ['fecha_ingreso' => '2025-01-01']);
        empleadoDe(['primer_apellido' => 'Final'], ['fecha_ingreso' => '2025-12-31']);
        empleadoDe(['primer_apellido' => 'Despues'], ['fecha_ingreso' => '2026-01-01']);

        $ingresos = array_column(
            filasDelListado(['ingresoDesde' => '2025-01-01', 'ingresoHasta' => '2025-12-31']),
            'ingreso'
        );

        sort($ingresos);

        expect($ingresos)->toBe(['01/01/2025', '31/12/2025']);
    });

    it('busca por nombre completo aunque el apellido esté en otra columna', function () {
        empleadoDe(['primer_nombre' => 'Juan', 'segundo_nombre' => 'Pablo', 'primer_apellido' => 'Mejía', 'segundo_apellido' => 'Ochoa']);
        empleadoDe(['primer_nombre' => 'Sandra', 'segundo_nombre' => null, 'primer_apellido' => 'Ospina', 'segundo_apellido' => null]);

        expect(nombresDe(filasDelListado(['buscar' => 'Pablo Mejía'])))
            ->toBe(['Juan Pablo Mejía Ochoa']);
    });

    it('busca por documento y sin distinguir mayúsculas', function () {
        empleadoDe(['numero_documento' => '1017234891', 'primer_nombre' => 'Juan', 'segundo_nombre' => null, 'primer_apellido' => 'Mejía', 'segundo_apellido' => null]);
        empleadoDe(['numero_documento' => '8412337']);

        expect(filasDelListado(['buscar' => '1017234891']))->toHaveCount(1)
            ->and(nombresDe(filasDelListado(['buscar' => 'mejía'])))->toBe(['Juan Mejía']);
    });

    it('ignora un filtro que no existe en vez de romperse', function () {
        empleadoDe();

        expect(filasDelListado(['contrato' => 'prestacion_de_servicios']))->toHaveCount(1)
            ->and(filasDelListado(['ingresoDesde' => 'ayer']))->toHaveCount(1);
    });

    it('enseña los filtros puestos como etiquetas removibles', function () {
        empleadoDe([], ['cargo' => 'Conductor']);

        $props = $this->actingAs($this->usuario)
            ->get('/empleados?cargo=Conductor&buscar=Juan')
            ->viewData('page')['props'];

        expect($props['tieneFiltros'])->toBeTrue()
            ->and(array_column($props['aplicados'], 'clave'))
            ->toBe(['buscar', 'estado', 'cargo']);
    });

    it('ofrece como cargos los que la empresa tiene de verdad', function () {
        empleadoDe([], ['cargo' => 'Operario']);
        empleadoDe([], ['cargo' => 'Analista']);

        $props = $this->actingAs($this->usuario)->get('/empleados')->viewData('page')['props'];

        expect($props['opciones']['cargos'])->toBe(['Analista', 'Operario']);
    });
});

describe('el orden y la paginación', function () {
    it('pagina de 25 en 25 y admite 50 y 100', function () {
        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()->count(30)->create());

        $props = $this->actingAs($this->usuario)->get('/empleados')->viewData('page')['props'];

        expect($props['empleados']['filas'])->toHaveCount(25)
            ->and($props['empleados']['total'])->toBe(30)
            ->and($props['empleados']['paginas'])->toBe(2)
            ->and(filasDelListado(['porPagina' => 50]))->toHaveCount(30)
            ->and(filasDelListado(['porPagina' => 7]))->toHaveCount(25);
    });

    it('ordena por la columna pedida y en el sentido pedido', function () {
        empleadoDe([], ['cargo' => 'Conductor']);
        empleadoDe([], ['cargo' => 'Analista']);
        empleadoDe([], ['cargo' => 'Operario']);

        $cargos = fn (string $direccion) => array_column(
            filasDelListado(['orden' => 'cargo', 'direccion' => $direccion]),
            'cargo'
        );

        expect($cargos('asc'))->toBe(['Analista', 'Conductor', 'Operario'])
            ->and($cargos('desc'))->toBe(['Operario', 'Conductor', 'Analista']);
    });

    it('ordena por documento, que vive en la persona y no en la vinculación', function () {
        empleadoDe(['numero_documento' => '2000000000']);
        empleadoDe(['numero_documento' => '1000000000']);

        expect(array_column(filasDelListado(['orden' => 'documento']), 'documento'))
            ->toBe(['1000000000', '2000000000']);
    });
});

describe('la exportación', function () {
    it('trae exactamente lo filtrado, no todo el listado', function () {
        Excel::fake();

        empleadoDe([], ['cargo' => 'Conductor']);
        empleadoDe([], ['cargo' => 'Analista']);

        $this->actingAs($this->usuario)->get('/empleados/exportar?cargo=Conductor')->assertOk();

        Excel::assertDownloaded('empleados-'.now()->format('Y-m-d').'.xlsx', function (EmpleadosExport $exportacion) {
            $filas = $exportacion->collection();

            return $filas->count() === 1
                && $exportacion->map($filas->first())[2] === 'Conductor';
        });
    });

    it('empieza por documento, nombre completo y cargo', function () {
        expect(array_slice((new EmpleadosExport(ListadoDeEmpleados::desde(request())))->headings(), 0, 3))
            ->toBe(['Documento', 'Nombre completo', 'Cargo']);
    });

    it('escribe las mismas seis columnas que la pantalla', function () {
        $empleado = empleadoDe(
            ['numero_documento' => '52998741', 'primer_nombre' => 'Marcela', 'segundo_nombre' => null, 'primer_apellido' => 'Ruiz', 'segundo_apellido' => null],
            ['cargo' => 'Analista de Compras', 'fecha_ingreso' => '2024-01-20'],
        );

        $exportacion = new EmpleadosExport(ListadoDeEmpleados::desde(request()));

        // Dentro del contexto de la empresa, porque leer la persona del
        // empleado es una consulta y toda consulta pasa por el filtro de
        // empresa. Fuera de él no hay ninguna persona que leer, que es
        // exactamente lo que se quiere de un proceso sin empresa en contexto.
        $fila = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $exportacion->map($empleado)
        );

        expect($fila)->toBe([
            '52998741',
            'Marcela Ruiz',
            'Analista de Compras',
            'Término indefinido',
            '20/01/2024',
            'Activo',
        ]);
    });
});

describe('el acceso', function () {
    it('devuelve 403 al listado y a la exportación sin el módulo asignado', function () {
        $sinModulo = usuarioDe($this->empresa, Rol::Administrador);

        $this->actingAs($sinModulo)->get('/empleados')->assertForbidden();
        $this->actingAs($sinModulo)->get('/empleados/exportar')->assertForbidden();
    });

    it('lleva a la ficha del empleado desde la fila', function () {
        $empleado = empleadoDe(['primer_nombre' => 'Sebastián', 'segundo_nombre' => null, 'primer_apellido' => 'Ortiz', 'segundo_apellido' => null]);

        $this->actingAs($this->usuario)
            ->get('/empleados/'.$empleado->getKey())
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('titulo', 'Ficha de Sebastián Ortiz')->etc());
    });
});
