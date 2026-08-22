<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Exports\ContratosExport;
use App\Exports\EmpleadosExport;
use App\Exports\IncapacidadesExport;
use App\Exports\LicenciasExport;
use App\Exports\MovimientosExport;
use App\Exports\TerminacionesExport;
use App\Exports\VacacionesExport;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\Movimiento;
use App\Models\PerfilSociodemografico;
use App\Models\PeriodoContractual;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\User;
use App\Models\UserModulo;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Fixtures\RegistroDePrueba;

/**
 * La batería de aislamiento entre empresas.
 *
 * Se diferencia a propósito de sus dos vecinas:
 *
 * - `MultiempresaTest` prueba el trait `PerteneceATenant`.
 * - `RlsTest` prueba la política de PostgreSQL.
 * - **Esta prueba los caminos**: modelo, ruta, exportación y búsqueda. Un
 *   registro creado en la empresa A no puede salir por ninguno de los cuatro
 *   estando autenticado en la B.
 *
 * ## Cómo se agrega una tabla de negocio
 *
 * Cada tabla de negocio nueva suma **una** llamada a `casoDeAislamiento()` al
 * final del archivo. Los cinco casos se escriben solos a partir de ahí. Una
 * tabla de negocio sin su llamada aquí no está terminada.
 */

/**
 * El texto que jamás debe aparecer del lado de la empresa B.
 *
 * Se busca literalmente en el cuerpo de cada respuesta, así que sirve tanto si
 * la fuga viene del listado como si viene de un registro incrustado en otro
 * sitio de la página.
 */
const SECRETO_DE_LA_A = 'Confidencial de la Empresa A';

/**
 * El texto que identifica al registro de la empresa A en este caso.
 *
 * Casi siempre es el de arriba. Una tabla cuyo único campo de texto sea de
 * catálogo cerrado —`user_modulos` y su columna `modulo`— declara el suyo con
 * la clave `secreto`: lo que importa no es que el texto sea secreto, sino que
 * solo lo tenga la empresa A.
 *
 * @param  array{secreto?: string}  $caso
 */
function secretoDe(array $caso): string
{
    return $caso['secreto'] ?? SECRETO_DE_LA_A;
}

/**
 * Registra los cinco casos de aislamiento de una tabla de negocio.
 *
 * @param  array{
 *     tabla: string,
 *     modelo: class-string<Model>,
 *     campo: string,
 *     secreto?: string,
 *     migracion: Closure(): void,
 *     crear: Closure(string): Model,
 * }  $caso
 */
function casoDeAislamiento(string $etiqueta, array $caso): void
{
    it("{$etiqueta}: no devuelve por modelo un registro de otra empresa", function () use ($caso) {
        $escena = prepararAislamiento($caso);

        $visto = $escena->contexto->paraTenant($escena->empresaB, fn () => [
            'porClave' => $caso['modelo']::find($escena->registro->getKey()),
            'total' => $caso['modelo']::count(),
        ]);

        expect($visto['porClave'])->toBeNull()
            ->and($visto['total'])->toBe(0);
    });

    it("{$etiqueta}: responde 404 al pedir por ruta la ficha de otra empresa", function () use ($caso) {
        $escena = prepararAislamiento($caso);

        $respuesta = test()->actingAs($escena->usuarioB)
            ->get("/aislamiento/{$caso['tabla']}/{$escena->registro->getKey()}");

        $respuesta->assertNotFound();
        expect($respuesta->getContent())->not->toContain(secretoDe($caso));
    });

    it("{$etiqueta}: no incluye registros de otra empresa en la exportación", function () use ($caso) {
        $escena = prepararAislamiento($caso);

        $respuesta = test()->actingAs($escena->usuarioB)
            ->get("/aislamiento/{$caso['tabla']}/exportar");

        $respuesta->assertOk();
        expect($respuesta->getContent())->not->toContain(secretoDe($caso));
    });

    it("{$etiqueta}: no encuentra por búsqueda un registro de otra empresa", function () use ($caso) {
        $escena = prepararAislamiento($caso);

        // Se busca el texto exacto: si el filtro de empresa faltara, este es el
        // camino que más fácil lo devolvería.
        $respuesta = test()->actingAs($escena->usuarioB)
            ->get("/aislamiento/{$caso['tabla']}?buscar=".urlencode(secretoDe($caso)));

        $respuesta->assertOk()->assertJsonCount(0);
        expect($respuesta->getContent())->not->toContain(secretoDe($caso));
    });

    it("{$etiqueta}: la empresa dueña sí lo ve por los cuatro caminos", function () use ($caso) {
        // El contrapeso. Sin él, una ruta rota, una tabla vacía o un modelo que
        // no devuelve nada a nadie dejarían los cuatro casos de arriba en verde
        // sin haber comprobado el aislamiento.
        $escena = prepararAislamiento($caso);

        $porModelo = $escena->contexto->paraTenant(
            $escena->empresaA,
            fn () => $caso['modelo']::find($escena->registro->getKey())
        );

        expect($porModelo)->not->toBeNull();

        test()->actingAs($escena->usuarioA)
            ->get("/aislamiento/{$caso['tabla']}/{$escena->registro->getKey()}")
            ->assertOk()
            ->assertSee(secretoDe($caso));

        $exportacion = test()->actingAs($escena->usuarioA)
            ->get("/aislamiento/{$caso['tabla']}/exportar");

        expect($exportacion->getContent())->toContain(secretoDe($caso));

        test()->actingAs($escena->usuarioA)
            ->get("/aislamiento/{$caso['tabla']}?buscar=".urlencode(secretoDe($caso)))
            ->assertOk()
            ->assertJsonCount(1);
    });
}

/**
 * Deja la escena montada: la tabla, las dos empresas, sus usuarios, el registro
 * de la A y las tres rutas de juguete.
 *
 * @param  array{
 *     tabla: string,
 *     modelo: class-string<Model>,
 *     campo: string,
 *     secreto?: string,
 *     migracion: Closure(): void,
 *     crear: Closure(string): Model,
 * }  $caso
 */
function prepararAislamiento(array $caso): object
{
    ($caso['migracion'])();

    registrarRutasDeAislamiento($caso);

    $contexto = app(ContextoTenant::class);
    $empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
    $empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

    $registro = $contexto->paraTenant($empresaA, fn () => ($caso['crear'])(secretoDe($caso)));

    return (object) [
        'contexto' => $contexto,
        'empresaA' => $empresaA,
        'empresaB' => $empresaB,
        'registro' => $registro,
        // Los usuarios se guardan, no la sesión ya iniciada: `actingAs()`
        // devuelve siempre la misma instancia de prueba, así que autenticar a
        // los dos de una vez dejaría autenticado solo al último.
        'usuarioA' => User::factory()->create(['tenant_id' => $empresaA->id]),
        'usuarioB' => User::factory()->create(['tenant_id' => $empresaB->id]),
    ];
}

/**
 * Las tres rutas de juguete: ficha, exportación y listado con búsqueda.
 *
 * Son de juguete en la forma, no en el fondo: consultan por el modelo y por el
 * grupo `web`, que es exactamente por donde pasarán los controladores de
 * verdad. Cuando exista el módulo real, su caso apuntará a sus rutas y estas
 * desaparecerán.
 *
 * @param  array{tabla: string, modelo: class-string<Model>, campo: string, migracion: Closure, crear: Closure}  $caso
 */
function registrarRutasDeAislamiento(array $caso): void
{
    ['tabla' => $tabla, 'modelo' => $modelo, 'campo' => $campo] = $caso;

    Route::middleware('web')->group(function () use ($tabla, $modelo, $campo) {
        Route::get("/aislamiento/{$tabla}", function (Request $peticion) use ($modelo, $campo) {
            $consulta = $modelo::query();

            $termino = $peticion->query('buscar');

            if (is_string($termino) && $termino !== '') {
                $consulta->where($campo, 'ilike', '%'.$termino.'%');
            }

            return response()->json($consulta->get());
        });

        // Va antes que la ficha: si no, `exportar` se leería como un
        // identificador.
        Route::get("/aislamiento/{$tabla}/exportar", fn () => response(
            // El campo puede venir casteado a enumerado —`user_modulos` lo
            // hace—, y un enumerado no se convierte solo a texto.
            $modelo::query()
                ->pluck($campo)
                ->map(fn (mixed $valor) => $valor instanceof BackedEnum ? $valor->value : (string) $valor)
                ->implode("\n"),
            200,
            ['Content-Type' => 'text/csv']
        ));

        Route::get("/aislamiento/{$tabla}/{registro}", fn (string $registro) => response()->json(
            $modelo::query()->findOrFail($registro)
        ));
    });
}

// ---------------------------------------------------------------------------
// Los casos, uno por tabla de negocio.
// ---------------------------------------------------------------------------

casoDeAislamiento('registros de prueba', [
    'tabla' => 'registros_de_prueba',
    'modelo' => RegistroDePrueba::class,
    'campo' => 'titulo',
    'migracion' => function (): void {
        // Lo mismo que hará la migración de la primera tabla real: columna,
        // índice con `tenant_id` primero y la política de RLS en el mismo sitio
        // donde se crea la tabla.
        Schema::create('registros_de_prueba', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tenant_id')->constrained('tenants');
            $tabla->string('titulo');
            $tabla->timestamps();

            $tabla->index(['tenant_id', 'titulo']);
        });

        PoliticaRls::activarEn('registros_de_prueba');
    },
    'crear' => fn (string $titulo) => RegistroDePrueba::create(['titulo' => $titulo]),
]);

casoDeAislamiento('módulos de usuario', [
    'tabla' => 'user_modulos',
    'modelo' => UserModulo::class,
    'campo' => 'modulo',
    // La columna solo admite nombres de módulo, así que el marcador es uno de
    // ellos. Sigue sirviendo: solo la empresa A tiene una fila con este valor.
    'secreto' => 'vacaciones',
    // La tabla la crea su propia migración; aquí no hay nada que montar.
    'migracion' => function (): void {},
    'crear' => fn (string $modulo) => UserModulo::create([
        'user_id' => User::factory()->create(['tenant_id' => tenant_actual_id()])->id,
        'modulo' => $modulo,
    ]),
]);

casoDeAislamiento('personas', [
    'tabla' => 'personas',
    'modelo' => Persona::class,
    'campo' => 'primer_nombre',
    'migracion' => function (): void {},
    'crear' => fn (string $nombre) => Persona::factory()->create(['primer_nombre' => $nombre]),
]);

casoDeAislamiento('vinculaciones', [
    'tabla' => 'vinculaciones',
    'modelo' => Vinculacion::class,
    'campo' => 'cargo',
    'migracion' => function (): void {},
    'crear' => fn (string $cargo) => Vinculacion::factory()->create(['cargo' => $cargo]),
]);

casoDeAislamiento('periodos contractuales', [
    'tabla' => 'periodos_contractuales',
    'modelo' => PeriodoContractual::class,
    'campo' => 'evento',
    // Como en `user_modulos`: la tabla no tiene texto libre, así que el
    // marcador es una de sus opciones cerradas. Solo la empresa A tiene un
    // tramo con este evento; el `inicial` que abre toda vinculación no estorba.
    'secreto' => 'prorroga_pactada',
    'migracion' => function (): void {},
    'crear' => fn (string $evento) => PeriodoContractual::factory()->create(['evento' => $evento]),
]);

/**
 * El listado de empleados, ya por sus rutas de verdad.
 *
 * Los casos de arriba prueban el patrón con rutas de juguete; este prueba el
 * primer módulo construido —listado, ficha, exportación y búsqueda— tal como lo
 * usa un cliente. Cuando cada módulo exista, tendrá el suyo así.
 */
describe('empleados, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->empleadoDeLaA = $this->contexto->paraTenant($this->empresaA, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
            ->create(['cargo' => SECRETO_DE_LA_A]));

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Empleados);
    });

    it('no lista empleados de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/empleados');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['empleados']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no encuentra por búsqueda un empleado de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)
            ->get('/empleados?buscar='.urlencode('Confidencial'));

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['empleados']['filas'])->toBe([]);
    });

    it('responde 404 en la ficha de un empleado de otra empresa', function () {
        $this->actingAs($this->usuarioB)
            ->get('/empleados/'.$this->empleadoDeLaA->getKey())
            ->assertNotFound();
    });

    it('no incluye empleados de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/empleados/exportar?estado=todos')->assertOk();

        Excel::assertDownloaded(
            'empleados-'.now()->format('Y-m-d').'.xlsx',
            fn (EmpleadosExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('la empresa dueña sí ve a su empleado por los cuatro caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Empleados);

        $listado = $this->actingAs($usuarioA)->get('/empleados?buscar='.urlencode('Confidencial'));
        $listado->assertOk();

        expect($listado->viewData('page')['props']['empleados']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)
            ->get('/empleados/'.$this->empleadoDeLaA->getKey())
            ->assertOk();

        $this->actingAs($usuarioA)->get('/empleados/exportar')->assertOk();

        Excel::assertDownloaded(
            'empleados-'.now()->format('Y-m-d').'.xlsx',
            fn (EmpleadosExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

/**
 * El listado de contratos, por sus rutas de verdad (F1-08).
 *
 * Un contrato es una vinculación, así que la tabla ya tiene su caso arriba. Lo
 * que se prueba aquí es el módulo nuevo: listado, detalle y exportación.
 */
describe('contratos, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->contratoDeLaA = $this->contexto->paraTenant($this->empresaA, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
            ->aTerminoFijo(now()->subMonths(6)->toDateString(), now()->addDays(20)->toDateString())
            ->create(['cargo' => SECRETO_DE_LA_A]));

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Contratos);
    });

    it('no lista contratos de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/contratos');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['contratos']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no cuenta contratos de otra empresa en el aviso de vencidos', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/contratos');

        expect($respuesta->viewData('page')['props']['conteos']['porVencer'])->toBe(0);
    });

    it('responde 404 en el detalle de un contrato de otra empresa', function () {
        $this->actingAs($this->usuarioB)
            ->get('/contratos/'.$this->contratoDeLaA->getKey())
            ->assertNotFound();
    });

    it('responde 404 al renovar un contrato de otra empresa', function () {
        $this->actingAs($this->usuarioB)
            ->post('/contratos/'.$this->contratoDeLaA->getKey().'/renovar', [
                'tipo' => 'prorroga_pactada',
                'fecha_fin' => now()->addYear()->toDateString(),
            ])
            ->assertNotFound();

        expect($this->contexto->paraTenant(
            $this->empresaA,
            fn () => $this->contratoDeLaA->periodos()->count()
        ))->toBe(1);
    });

    it('no incluye contratos de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/contratos/exportar?vence=todos')->assertOk();

        Excel::assertDownloaded(
            'contratos-'.now()->format('Y-m-d').'.xlsx',
            fn (ContratosExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('la empresa dueña sí ve su contrato por los tres caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Contratos);

        $listado = $this->actingAs($usuarioA)->get('/contratos');
        $listado->assertOk();

        expect($listado->viewData('page')['props']['contratos']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)
            ->get('/contratos/'.$this->contratoDeLaA->getKey())
            ->assertOk();

        $this->actingAs($usuarioA)->get('/contratos/exportar')->assertOk();

        Excel::assertDownloaded(
            'contratos-'.now()->format('Y-m-d').'.xlsx',
            fn (ContratosExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

/**
 * El listado de incapacidades, por sus rutas de verdad (F2-04). El primer
 * módulo de novedades, y el primero que se registra por `POST` en vez de
 * heredar la fila de otra tabla.
 */
describe('incapacidades, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->incapacidadDeLaA = $this->contexto->paraTenant($this->empresaA, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
                ->create(['cargo' => SECRETO_DE_LA_A]);

            $eps = Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]);

            return Incapacidad::factory()->de($vinculacion)->del14al16DeJulio()->create(['eps_id' => $eps->id]);
        });

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Incapacidades);
    });

    it('no lista incapacidades de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/incapacidades');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['incapacidades']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no incluye incapacidades de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/incapacidades/exportar')->assertOk();

        Excel::assertDownloaded(
            'incapacidades-'.now()->format('Y-m-d').'.xlsx',
            fn (IncapacidadesExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('no registra una incapacidad sobre el empleado de otra empresa', function () {
        $this->actingAs($this->usuarioB)->post('/incapacidades', [
            'vinculacion_id' => $this->incapacidadDeLaA->vinculacion_id,
            'fecha_inicio' => '2026-07-14',
            'fecha_fin' => '2026-07-16',
            'codigo_diagnostico' => 'J06.9',
            'es_prorroga' => 'no',
            'eps_id' => $this->incapacidadDeLaA->eps_id,
            'origen' => 'comun',
        ])->assertSessionHasErrors('vinculacion_id');

        expect($this->contexto->paraTenant(
            $this->empresaA,
            fn () => Incapacidad::query()->count()
        ))->toBe(1);
    });

    it('la empresa dueña sí ve su incapacidad por los dos caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Incapacidades);

        $listado = $this->actingAs($usuarioA)->get('/incapacidades');
        $listado->assertOk();

        expect($listado->viewData('page')['props']['incapacidades']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)->get('/incapacidades/exportar')->assertOk();

        Excel::assertDownloaded(
            'incapacidades-'.now()->format('Y-m-d').'.xlsx',
            fn (IncapacidadesExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

/**
 * El listado de vacaciones, por sus rutas de verdad (F2-05). El segundo
 * módulo de novedades que se registra por `POST` en vez de heredar la fila de
 * otra tabla, igual que incapacidades.
 */
describe('vacaciones, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->vacacionDeLaA = $this->contexto->paraTenant($this->empresaA, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
                ->create(['cargo' => SECRETO_DE_LA_A]);

            return Vacacion::factory()->de($vinculacion)->del27al31DeJulio()->create();
        });

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Vacaciones);
    });

    it('no lista vacaciones de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/vacaciones');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['vacaciones']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no incluye vacaciones de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/vacaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'vacaciones-'.now()->format('Y-m-d').'.xlsx',
            fn (VacacionesExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('no registra unas vacaciones sobre el empleado de otra empresa', function () {
        $this->actingAs($this->usuarioB)->post('/vacaciones', [
            'vinculacion_id' => $this->vacacionDeLaA->vinculacion_id,
            'fecha_inicio' => '2026-07-27',
            'fecha_fin' => '2026-07-31',
        ])->assertSessionHasErrors('vinculacion_id');

        expect($this->contexto->paraTenant(
            $this->empresaA,
            fn () => Vacacion::query()->count()
        ))->toBe(1);
    });

    it('la empresa dueña sí ve su vacación por los dos caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Vacaciones);

        $listado = $this->actingAs($usuarioA)->get('/vacaciones');
        $listado->assertOk();

        expect($listado->viewData('page')['props']['vacaciones']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)->get('/vacaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'vacaciones-'.now()->format('Y-m-d').'.xlsx',
            fn (VacacionesExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

/**
 * El listado de licencias, ausencias y suspensiones, por sus rutas de verdad
 * (F2-07). El tercer módulo de novedades que se registra por `POST` en vez de
 * heredar la fila de otra tabla, igual que incapacidades y vacaciones.
 */
describe('licencias, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->licenciaDeLaA = $this->contexto->paraTenant($this->empresaA, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
                ->create(['cargo' => SECRETO_DE_LA_A]);

            return Licencia::factory()->de($vinculacion)->delJuevesAlMartes()->create();
        });

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Licencias);
    });

    it('no lista licencias de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/licencias');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['licencias']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no incluye licencias de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/licencias/exportar')->assertOk();

        Excel::assertDownloaded(
            'licencias-'.now()->format('Y-m-d').'.xlsx',
            fn (LicenciasExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('no registra una licencia sobre el empleado de otra empresa', function () {
        $this->actingAs($this->usuarioB)->post('/licencias', [
            'vinculacion_id' => $this->licenciaDeLaA->vinculacion_id,
            'tipo' => 'ausencia',
            'fecha_inicio' => '2026-07-02',
            'fecha_fin' => '2026-07-07',
        ])->assertSessionHasErrors('vinculacion_id');

        expect($this->contexto->paraTenant(
            $this->empresaA,
            fn () => Licencia::query()->count()
        ))->toBe(1);
    });

    it('la empresa dueña sí ve su licencia por los dos caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Licencias);

        $listado = $this->actingAs($usuarioA)->get('/licencias');
        $listado->assertOk();

        expect($listado->viewData('page')['props']['licencias']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)->get('/licencias/exportar')->assertOk();

        Excel::assertDownloaded(
            'licencias-'.now()->format('Y-m-d').'.xlsx',
            fn (LicenciasExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

/**
 * El listado de aumentos y promociones, por sus rutas de verdad (F2-08). Se
 * prueba aparte de `casoDeAislamiento()` por lo mismo que las tres novedades:
 * se registra por `POST` sobre un empleado, y ese es el camino por donde una
 * empresa podría tocar el salario de otra.
 */
describe('movimientos, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->movimientoDeLaA = $this->contexto->paraTenant($this->empresaA, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
                ->create(['cargo' => SECRETO_DE_LA_A, 'salario' => 1_800_000]);

            return Movimiento::factory()
                ->de($vinculacion)
                ->promocion(SECRETO_DE_LA_A, SECRETO_DE_LA_A)
                ->create();
        });

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Movimientos);
    });

    it('no lista movimientos de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/movimientos');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['movimientos']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no incluye movimientos de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/movimientos/exportar')->assertOk();

        Excel::assertDownloaded(
            'movimientos-'.now()->format('Y-m-d').'.xlsx',
            fn (MovimientosExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('no registra un movimiento sobre el empleado de otra empresa, ni le toca el salario', function () {
        $this->actingAs($this->usuarioB)->post('/movimientos', [
            'vinculacion_id' => $this->movimientoDeLaA->vinculacion_id,
            'tipo' => 'aumento_salarial',
            'salario_nuevo' => '9000000',
            'fecha_efectividad' => '2026-08-15',
        ])->assertSessionHasErrors('vinculacion_id');

        $visto = $this->contexto->paraTenant($this->empresaA, fn () => [
            'movimientos' => Movimiento::query()->count(),
            'salario' => (float) Vinculacion::query()
                ->findOrFail($this->movimientoDeLaA->vinculacion_id)
                ->getAttribute('salario'),
        ]);

        expect($visto['movimientos'])->toBe(1)
            ->and($visto['salario'])->toBe(1800000.0);
    });

    it('no resuelve el cargo ni el salario vigentes del empleado de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)
            ->get('/movimientos?'.http_build_query(['vinculacion_id' => $this->movimientoDeLaA->vinculacion_id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Movimientos/Index',
                'X-Inertia-Partial-Data' => 'situacionActual',
            ]);

        expect($respuesta->json()['props']['situacionActual'])->toBeNull()
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('la empresa dueña sí ve su movimiento por los dos caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Movimientos);

        $listado = $this->actingAs($usuarioA)->get('/movimientos');
        $listado->assertOk();

        expect($listado->viewData('page')['props']['movimientos']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)->get('/movimientos/exportar')->assertOk();

        Excel::assertDownloaded(
            'movimientos-'.now()->format('Y-m-d').'.xlsx',
            fn (MovimientosExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

/**
 * El listado de terminaciones, por sus rutas de verdad (F2-09). Se prueba
 * aparte de `casoDeAislamiento()` por lo mismo que los otros cuatro módulos del
 * flujo: se registra por `POST` sobre un empleado, y aquí ese camino es el más
 * grave de todos —una empresa que pudiera terminar al empleado de otra le
 * dejaría a alguien sin alertas y sin saber por qué—.
 */
describe('terminaciones, por sus rutas reales', function () {
    beforeEach(function () {
        $this->contexto = app(ContextoTenant::class);
        $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
        $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

        $this->terminacionDeLaA = $this->contexto->paraTenant($this->empresaA, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create(['primer_nombre' => 'Confidencial', 'primer_apellido' => 'DeLaEmpresaA']))
                ->create(['cargo' => SECRETO_DE_LA_A]);

            return Terminacion::factory()->de($vinculacion)->create();
        });

        $this->activoDeLaA = $this->contexto->paraTenant($this->empresaA, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create(['primer_apellido' => 'SigueActivo']))
            ->create(['cargo' => SECRETO_DE_LA_A]));

        $this->usuarioB = usuarioDe($this->empresaB, Rol::Administrador, Modulo::Terminaciones);
    });

    it('no lista terminaciones de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)->get('/terminaciones');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['terminaciones']['filas'])->toBe([])
            ->and($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it('no incluye terminaciones de otra empresa en la exportación', function () {
        Excel::fake();

        $this->actingAs($this->usuarioB)->get('/terminaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'terminaciones-'.now()->format('Y-m-d').'.xlsx',
            fn (TerminacionesExport $exportacion) => $exportacion->collection()->isEmpty()
        );
    });

    it('no termina al empleado de otra empresa ni lo deja inactivo', function () {
        $this->actingAs($this->usuarioB)->post('/terminaciones', [
            'vinculacion_id' => $this->activoDeLaA->id,
            'tipo_salida' => 'renuncia',
            'motivo' => 'Motivos de salud',
            'fecha_salida' => '2026-08-31',
        ])->assertSessionHasErrors('vinculacion_id');

        $visto = $this->contexto->paraTenant($this->empresaA, fn () => [
            'terminaciones' => Terminacion::query()->count(),
            'activo' => Vinculacion::query()->findOrFail($this->activoDeLaA->id)->estaActiva(),
        ]);

        expect($visto['terminaciones'])->toBe(1)
            ->and($visto['activo'])->toBeTrue();
    });

    it('no resuelve el aviso de confirmación del empleado de otra empresa', function () {
        $respuesta = $this->actingAs($this->usuarioB)
            ->get('/terminaciones?'.http_build_query(['vinculacion_id' => $this->activoDeLaA->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Terminaciones/Index',
                'X-Inertia-Partial-Data' => 'avisoDeTerminacion',
            ]);

        expect($respuesta->json()['props']['avisoDeTerminacion'])->toBeNull()
            ->and($respuesta->getContent())->not->toContain('SigueActivo');
    });

    it('la empresa dueña sí ve su terminación por los dos caminos', function () {
        Excel::fake();

        $usuarioA = usuarioDe($this->empresaA, Rol::Administrador, Modulo::Terminaciones);

        $listado = $this->actingAs($usuarioA)->get('/terminaciones');
        $listado->assertOk();

        expect($listado->viewData('page')['props']['terminaciones']['filas'])->toHaveCount(1);

        $this->actingAs($usuarioA)->get('/terminaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'terminaciones-'.now()->format('Y-m-d').'.xlsx',
            fn (TerminacionesExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });
});

casoDeAislamiento('perfil sociodemográfico', [
    'tabla' => 'perfil_sociodemografico',
    'modelo' => PerfilSociodemografico::class,
    'campo' => 'tipo_vivienda',
    // Ningún campo del perfil es texto libre: son listas y números. Se usa como
    // marcador una de las opciones cerradas, como en `user_modulos`. Lo que
    // importa no es que el texto sea secreto, sino que solo lo tenga la A.
    'secreto' => 'propia',
    'migracion' => function (): void {},
    'crear' => fn (string $vivienda) => PerfilSociodemografico::factory()->create([
        'tipo_vivienda' => $vivienda,
    ]),
]);
