<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\ListadoDeContratos;
use App\Contratacion\ListadoDeEmpleados;
use App\Contratacion\VigenciaDelContrato;
use App\Exports\ContratosExport;
use App\Exports\EmpleadosExport;
use App\Exports\IncapacidadesExport;
use App\Exports\LicenciasExport;
use App\Exports\MovimientosExport;
use App\Exports\TerminacionesExport;
use App\Exports\VacacionesExport;
use App\Incapacidades\ListadoDeIncapacidades;
use App\Licencias\ListadoDeLicencias;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Movimientos\ListadoDeMovimientos;
use App\Multiempresa\ContextoTenant;
use App\Terminaciones\ListadoDeTerminaciones;
use App\Vacaciones\ListadoDeVacaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exportación y filtros por módulo (F4-01 · documento 1, capítulo 11).
 *
 * Las tres cosas que pide el capítulo 11 y que hasta ahora estaban a medias:
 *
 * 1. **El botón de exportar respeta los filtros y lo dice** (11.1 y documento 3,
 *    6.1). Los siete módulos lo tienen desde su propia tarea, porque la
 *    exportación y la pantalla comparten la consulta de `Listado`; aquí se
 *    comprueba de punta a punta con tres filtros puestos a la vez.
 * 2. **Toda exportación lleva documento, nombre completo y cargo** (11.2). Se
 *    comprueba en las siete de un tirón, para que la octava no pueda olvidarlo.
 * 3. **Los filtros de cada módulo son los de la tabla de 11.3.** Faltaban cinco:
 *    «Empleado» en incapacidades, vacaciones y licencias, «Cargo» en vacaciones
 *    y movimientos, y «Estado del contrato» en contratos.
 *
 * Auditoría es el octavo módulo de esa tabla y no tiene pantalla todavía: es
 * `REV-04`, y sus cuatro filtros llegan con ella.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle', 'sabado_laboral' => false]);
    $this->contexto = app(ContextoTenant::class);
});

/**
 * Un empleado con nombre y cargo escritos, que es lo que estos filtros buscan.
 * Sin segundos nombres para que «Carolina Ramírez» sea el nombre completo y no
 * un trozo de él.
 */
function empleadoConCargo(string $nombre, string $apellido, string $cargo, string $documento): Vinculacion
{
    return empleadoParaNovedad(
        [
            'numero_documento' => $documento,
            'primer_nombre' => $nombre,
            'segundo_nombre' => null,
            'primer_apellido' => $apellido,
            'segundo_apellido' => null,
        ],
        ['cargo' => $cargo],
    );
}

/**
 * Las siete exportaciones que existen, cada una con su listado sin filtros.
 * El panel de alertas no está: no exporta, y la decisión quedó escrita en F3-06.
 *
 * @return array<string, object>
 */
function lasSieteExportaciones(): array
{
    return [
        'empleados' => new EmpleadosExport(ListadoDeEmpleados::desde(Request::create('/empleados'))),
        'contratos' => new ContratosExport(ListadoDeContratos::desde(Request::create('/contratos'))),
        'incapacidades' => new IncapacidadesExport(ListadoDeIncapacidades::desde(Request::create('/incapacidades'))),
        'vacaciones' => new VacacionesExport(ListadoDeVacaciones::desde(Request::create('/vacaciones'))),
        'licencias' => new LicenciasExport(ListadoDeLicencias::desde(Request::create('/licencias'))),
        'movimientos' => new MovimientosExport(ListadoDeMovimientos::desde(Request::create('/movimientos'))),
        'terminaciones' => new TerminacionesExport(ListadoDeTerminaciones::desde(Request::create('/terminaciones'))),
    ];
}

describe('las columnas obligatorias de toda exportación (11.2)', function () {
    it('las siete exportaciones abren con documento, nombre completo y cargo', function () {
        foreach (lasSieteExportaciones() as $modulo => $exportacion) {
            expect(array_slice($exportacion->headings(), 0, 3))
                ->toBe(['Documento', 'Nombre completo', 'Cargo'], "la exportación de {$modulo}");
        }
    });

    it('la de incapacidades lleva además origen, diagnóstico, días y EPS (11.4)', function () {
        $encabezados = lasSieteExportaciones()['incapacidades']->headings();

        expect($encabezados)
            ->toContain('Origen')
            ->toContain('Código de diagnóstico')
            ->toContain('Días')
            ->toContain('EPS');
    });
});

describe('el «hecho cuando»: tres filtros y exportar', function () {
    beforeEach(function () {
        $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $carolina = empleadoConCargo('Carolina', 'Ramírez', 'Analista de compras', '43887201');
        $mauricio = empleadoConCargo('Mauricio', 'Peña', 'Analista de compras', '71554332');
        $diego = empleadoConCargo('Diego', 'Ramírez', 'Auxiliar de bodega', '10298455');

        $this->contexto->paraTenant($this->empresa, function () use ($carolina, $mauricio, $diego): void {
            // El único que pasa los tres filtros.
            Vacacion::factory()->de($carolina)->create([
                'fecha_inicio' => '2026-07-27',
                'fecha_fin' => '2026-07-31',
            ]);
            // La misma persona y el mismo cargo, pero antes del corte.
            Vacacion::factory()->de($carolina)->create([
                'fecha_inicio' => '2026-01-12',
                'fecha_fin' => '2026-01-16',
            ]);
            // El mismo cargo y la misma fecha, otro apellido.
            Vacacion::factory()->de($mauricio)->create([
                'fecha_inicio' => '2026-07-27',
                'fecha_fin' => '2026-07-31',
            ]);
            // El mismo apellido y la misma fecha, otro cargo.
            Vacacion::factory()->de($diego)->create([
                'fecha_inicio' => '2026-07-27',
                'fecha_fin' => '2026-07-31',
            ]);
        });

        $this->filtros = [
            'buscar' => 'Ramírez',
            'desde' => '2026-07-01',
            'cargo' => 'Analista de compras',
        ];
    });

    it('la pantalla enseña exactamente un registro y dice que está filtrada', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/vacaciones?'.http_build_query($this->filtros));

        $props = $respuesta->viewData('page')['props'];

        expect($props['vacaciones']['total'])->toBe(1)
            ->and($props['vacaciones']['filas'][0]['nombre'])->toBe('Carolina Ramírez')
            ->and($props['tieneFiltros'])->toBeTrue();
    });

    it('los tres filtros se ven como etiquetas removibles encima de la tabla', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/vacaciones?'.http_build_query($this->filtros));

        expect($respuesta->viewData('page')['props']['aplicados'])->toBe([
            ['clave' => 'buscar', 'texto' => 'Empleado: Ramírez'],
            ['clave' => 'desde', 'texto' => 'Desde: 01/07/2026'],
            ['clave' => 'cargo', 'texto' => 'Cargo: Analista de compras'],
        ]);
    });

    it('la exportación trae ese registro y nada más, con documento, nombre y cargo', function () {
        Excel::fake();

        $this->actingAs($this->usuario)
            ->get('/vacaciones/exportar?'.http_build_query($this->filtros))
            ->assertOk();

        Excel::assertDownloaded(
            'vacaciones-'.now()->format('Y-m-d').'.xlsx',
            function (VacacionesExport $exportacion) {
                $filas = $exportacion->collection();
                $fila = ListadoDeVacaciones::fila($filas->first());

                return $filas->count() === 1
                    && $fila['documento'] === '43887201'
                    && $fila['nombre'] === 'Carolina Ramírez'
                    && $fila['cargo'] === 'Analista de compras'
                    && $fila['inicio'] === '27/07/2026';
            }
        );
    });
});

describe('el filtro de empleado de los tres módulos de novedades (11.3)', function () {
    beforeEach(function () {
        $this->carolina = empleadoConCargo('Carolina', 'Ramírez', 'Analista de compras', '43887201');
        $this->mauricio = empleadoConCargo('Mauricio', 'Peña', 'Auxiliar de bodega', '71554332');
    });

    it('incapacidades: encuentra por nombre y descarta al resto', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Incapacidades);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Incapacidad::factory()->de($this->carolina)->del14al16DeJulio()->create();
            Incapacidad::factory()->de($this->mauricio)->del14al16DeJulio()->create();
        });

        $respuesta = $this->actingAs($usuario)->get('/incapacidades?buscar='.urlencode('Ramírez'));

        $props = $respuesta->viewData('page')['props'];

        expect($props['incapacidades']['filas'])->toHaveCount(1)
            ->and($props['incapacidades']['filas'][0]['nombre'])->toBe('Carolina Ramírez')
            ->and($props['aplicados'])->toContain(['clave' => 'buscar', 'texto' => 'Empleado: Ramírez']);
    });

    it('incapacidades: encuentra también por número de documento', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Incapacidades);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Incapacidad::factory()->de($this->carolina)->del14al16DeJulio()->create();
            Incapacidad::factory()->de($this->mauricio)->del14al16DeJulio()->create();
        });

        $respuesta = $this->actingAs($usuario)->get('/incapacidades?buscar=71554332');

        expect($respuesta->viewData('page')['props']['incapacidades']['filas'])->toHaveCount(1)
            ->and($respuesta->viewData('page')['props']['incapacidades']['filas'][0]['nombre'])
            ->toBe('Mauricio Peña');
    });

    it('vacaciones: encuentra por nombre', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Vacacion::factory()->de($this->carolina)->del27al31DeJulio()->create();
            Vacacion::factory()->de($this->mauricio)->del27al31DeJulio()->create();
        });

        $respuesta = $this->actingAs($usuario)->get('/vacaciones?buscar=Carolina');

        expect($respuesta->viewData('page')['props']['vacaciones']['filas'])->toHaveCount(1)
            ->and($respuesta->viewData('page')['props']['vacaciones']['filas'][0]['nombre'])
            ->toBe('Carolina Ramírez');
    });

    it('licencias: encuentra por nombre', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Licencias);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Licencia::factory()->de($this->carolina)->delJuevesAlMartes()->create();
            Licencia::factory()->de($this->mauricio)->delJuevesAlMartes()->create();
        });

        $respuesta = $this->actingAs($usuario)->get('/licencias?buscar='.urlencode('Peña'));

        expect($respuesta->viewData('page')['props']['licencias']['filas'])->toHaveCount(1)
            ->and($respuesta->viewData('page')['props']['licencias']['filas'][0]['nombre'])
            ->toBe('Mauricio Peña');
    });

    it('no encuentra nada cuando nadie coincide, y lo dice como búsqueda sin resultados', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $this->contexto->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($this->carolina)
            ->del27al31DeJulio()
            ->create());

        $respuesta = $this->actingAs($usuario)->get('/vacaciones?buscar=Villalobos');

        expect($respuesta->viewData('page')['props']['vacaciones']['filas'])->toBe([])
            ->and($respuesta->viewData('page')['props']['tieneFiltros'])->toBeTrue();
    });
});

describe('el filtro de cargo de vacaciones y de movimientos (11.3)', function () {
    beforeEach(function () {
        $this->carolina = empleadoConCargo('Carolina', 'Ramírez', 'Analista de compras', '43887201');
        $this->mauricio = empleadoConCargo('Mauricio', 'Peña', 'Auxiliar de bodega', '71554332');
    });

    it('vacaciones: trae solo los periodos de quien tiene ese cargo', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Vacacion::factory()->de($this->carolina)->del27al31DeJulio()->create();
            Vacacion::factory()->de($this->mauricio)->del27al31DeJulio()->create();
        });

        $respuesta = $this->actingAs($usuario)->get('/vacaciones?cargo='.urlencode('Auxiliar de bodega'));

        $props = $respuesta->viewData('page')['props'];

        expect($props['vacaciones']['filas'])->toHaveCount(1)
            ->and($props['vacaciones']['filas'][0]['cargo'])->toBe('Auxiliar de bodega')
            ->and($props['aplicados'])->toContain(['clave' => 'cargo', 'texto' => 'Cargo: Auxiliar de bodega']);
    });

    it('vacaciones: ofrece los cargos que existen hoy en la empresa', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $respuesta = $this->actingAs($usuario)->get('/vacaciones');

        expect($respuesta->viewData('page')['props']['opciones']['cargos'])
            ->toBe(['Analista de compras', 'Auxiliar de bodega']);
    });

    it('movimientos: trae solo los movimientos de quien tiene ese cargo', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Movimientos);

        $this->contexto->paraTenant($this->empresa, function (): void {
            Movimiento::factory()->de($this->carolina)->create();
            Movimiento::factory()->de($this->mauricio)->create();
        });

        $respuesta = $this->actingAs($usuario)->get('/movimientos?cargo='.urlencode('Analista de compras'));

        $props = $respuesta->viewData('page')['props'];

        expect($props['movimientos']['filas'])->toHaveCount(1)
            ->and($props['movimientos']['filas'][0]['nombre'])->toBe('Carolina Ramírez')
            ->and($props['aplicados'])->toContain(['clave' => 'cargo', 'texto' => 'Cargo: Analista de compras']);
    });

    it('un cargo que no existe no filtra a medias: no devuelve nada', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $this->contexto->paraTenant($this->empresa, fn () => Vacacion::factory()
            ->de($this->carolina)
            ->del27al31DeJulio()
            ->create());

        $respuesta = $this->actingAs($usuario)->get('/vacaciones?cargo='.urlencode('Gerente general'));

        expect($respuesta->viewData('page')['props']['vacaciones']['filas'])->toBe([]);
    });
});

describe('el filtro de estado del contrato (11.3)', function () {
    beforeEach(function () {
        $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
        $hoy = Carbon::today();

        $this->contexto->paraTenant($this->empresa, function () use ($hoy): void {
            // Vencido sin gestionar: la fecha pasó y nadie registró nada.
            Vinculacion::factory()
                ->de(Persona::factory()->sinSegundosNombres()->create(['primer_nombre' => 'Vencido']))
                ->aTerminoFijo($hoy->copy()->subYear()->toDateString(), $hoy->copy()->subDays(5)->toDateString())
                ->create();

            // Con el plazo de preaviso pasado: vence dentro de los 30 días.
            Vinculacion::factory()
                ->de(Persona::factory()->sinSegundosNombres()->create(['primer_nombre' => 'Preaviso']))
                ->aTerminoFijo($hoy->copy()->subMonths(6)->toDateString(), $hoy->copy()->addDays(10)->toDateString())
                ->create();

            // Al día: todavía queda plazo de sobra.
            Vinculacion::factory()
                ->de(Persona::factory()->sinSegundosNombres()->create(['primer_nombre' => 'Holgado']))
                ->aTerminoFijo($hoy->copy()->subMonths(2)->toDateString(), $hoy->copy()->addDays(200)->toDateString())
                ->create();

            // Al día también: un indefinido no tiene fecha que se le pase.
            Vinculacion::factory()
                ->de(Persona::factory()->sinSegundosNombres()->create(['primer_nombre' => 'Indefinido']))
                ->create();
        });
    });

    it('cada estado trae solo los contratos que están en él', function () {
        $esperado = [
            'vencido' => ['Vencido'],
            'preaviso_pasado' => ['Preaviso'],
            'al_dia' => ['Holgado', 'Indefinido'],
        ];

        foreach ($esperado as $estado => $nombres) {
            $respuesta = $this->actingAs($this->usuario)
                ->get('/contratos?'.http_build_query(['vence' => 'todos', 'estado' => $estado]));

            $primeros = array_map(
                fn (array $fila): string => explode(' ', (string) $fila['empleado'])[0],
                $respuesta->viewData('page')['props']['contratos']['filas'],
            );

            sort($primeros);

            expect($primeros)->toBe($nombres, "el estado {$estado}");
        }
    });

    /*
     * La consulta y el color son dos definiciones del mismo estado —una en SQL y
     * otra en PHP—, y el día que se separen la pantalla enseñaría una etiqueta
     * verde en una fila que el filtro trajo como vencida. Esto es lo que lo
     * impide.
     */
    it('el tono de cada fila filtrada es el que le corresponde a su estado', function () {
        $tonos = ['vencido' => 'error', 'preaviso_pasado' => 'advertencia', 'al_dia' => 'exito'];

        foreach ($tonos as $estado => $tono) {
            $respuesta = $this->actingAs($this->usuario)
                ->get('/contratos?'.http_build_query(['vence' => 'todos', 'estado' => $estado]));

            $filas = $respuesta->viewData('page')['props']['contratos']['filas'];

            expect($filas)->not->toBeEmpty("el estado {$estado}");

            foreach ($filas as $fila) {
                expect($fila['tono'])->toBe($tono, "el estado {$estado}");
            }
        }
    });

    it('los tres estados reparten entre ellos todos los contratos, sin dejar ninguno fuera', function () {
        $sinFiltro = $this->actingAs($this->usuario)->get('/contratos?vence=todos');
        $total = $sinFiltro->viewData('page')['props']['contratos']['total'];

        $sumados = 0;

        foreach (array_column(VigenciaDelContrato::ESTADOS, 'valor') as $estado) {
            $respuesta = $this->actingAs($this->usuario)
                ->get('/contratos?'.http_build_query(['vence' => 'todos', 'estado' => $estado]));

            $sumados += $respuesta->viewData('page')['props']['contratos']['total'];
        }

        expect($sumados)->toBe($total)->and($total)->toBe(4);
    });

    it('se ve como etiqueta removible con el texto del estado', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/contratos?estado=vencido');

        expect($respuesta->viewData('page')['props']['aplicados'])
            ->toContain(['clave' => 'estado', 'texto' => 'Estado: Vencido sin gestionar']);
    });

    it('un estado inventado en la dirección no filtra nada, como el resto de las listas', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/contratos?vence=todos&estado=cualquier_cosa');

        expect($respuesta->viewData('page')['props']['contratos']['total'])->toBe(4)
            ->and($respuesta->viewData('page')['props']['filtros']['estado'])->toBe('todos');
    });

    it('la exportación se lleva exactamente los del estado filtrado', function () {
        Excel::fake();

        $this->actingAs($this->usuario)
            ->get('/contratos/exportar?'.http_build_query(['vence' => 'todos', 'estado' => 'al_dia']))
            ->assertOk();

        Excel::assertDownloaded(
            'contratos-'.now()->format('Y-m-d').'.xlsx',
            fn (ContratosExport $exportacion) => $exportacion->collection()->count() === 2
        );
    });
});

describe('el aislamiento de los filtros nuevos', function () {
    beforeEach(function () {
        $this->otraEmpresa = Tenant::factory()->create(['nombre' => 'Empresa ajena']);

        $this->contexto->paraTenant($this->otraEmpresa, function (): void {
            $ajena = Vinculacion::factory()
                ->de(Persona::factory()->sinSegundosNombres()->create([
                    'numero_documento' => '99999999',
                    'primer_nombre' => 'Confidencial',
                    'primer_apellido' => 'DeLaOtra',
                ]))
                ->create(['cargo' => 'Cargo secreto']);

            Vacacion::factory()->de($ajena)->del27al31DeJulio()->create();
            Incapacidad::factory()->de($ajena)->del14al16DeJulio()->create();
            Licencia::factory()->de($ajena)->delJuevesAlMartes()->create();
            Movimiento::factory()->de($ajena)->create();
        });
    });

    it('buscar por el nombre de un empleado de otra empresa no devuelve nada', function () {
        $modulos = [
            '/vacaciones' => Modulo::Vacaciones,
            '/incapacidades' => Modulo::Incapacidades,
            '/licencias' => Modulo::Licencias,
        ];

        foreach ($modulos as $ruta => $modulo) {
            $usuario = usuarioDe($this->empresa, Rol::Administrador, $modulo);

            $respuesta = $this->actingAs($usuario)->get($ruta.'?buscar=Confidencial');

            $respuesta->assertOk();

            /*
             * Se comprueba contra el apellido y no contra el nombre buscado: lo
             * que se digitó vuelve en los filtros de la pantalla, así que
             * «Confidencial» está en la respuesta por haberlo escrito. Lo que no
             * puede estar es el resto de la fila.
             */
            expect($respuesta->viewData('page')['props'][trim($ruta, '/')]['filas'])->toBe([], $ruta)
                ->and($respuesta->getContent())->not->toContain('DeLaOtra');
        }
    });

    it('filtrar por el cargo de otra empresa no devuelve nada, y ese cargo no se ofrece', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Vacaciones);

        $respuesta = $this->actingAs($usuario)->get('/vacaciones?cargo='.urlencode('Cargo secreto'));

        expect($respuesta->viewData('page')['props']['vacaciones']['filas'])->toBe([])
            ->and($respuesta->viewData('page')['props']['opciones']['cargos'])->not->toContain('Cargo secreto');
    });
});
