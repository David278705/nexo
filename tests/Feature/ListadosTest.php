<?php

use App\Contratacion\ListadoDeContratos;
use App\Contratacion\ListadoDeEmpleados;
use App\Listados\Listado;
use App\Models\Tenant;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\ListadoDeEmpresas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\ListadoDePrueba;
use Tests\Fixtures\RegistroListable;

/**
 * El armazón compartido de los listados (`App\Listados\Listado`).
 *
 * Se prueba con un listado de mentira —`Tests\Fixtures\ListadoDePrueba`— y no
 * con el de empleados, y es a propósito: lo que hay que demostrar es que un
 * listado nuevo hereda el comportamiento sin volver a escribirlo. El fixture
 * declara sus dos filtros y sus dos columnas ordenables, y nada más; si la
 * normalización de la dirección, el orden, la paginación o el conteo estuvieran
 * en cada listado y no en el armazón, estas pruebas no podrían pasar.
 *
 * Lo que cada listado sí prueba por su cuenta son sus filtros y sus filas, en
 * `EmpleadosTest`, `ContratosTest` y `PanelDeEmpresasTest`. Ninguna de esas se
 * tocó al extraer el armazón, que es el otro criterio de la tarea.
 */
beforeEach(function () {
    Schema::create('registros_listables', function (Blueprint $tabla) {
        $tabla->id();
        $tabla->foreignId('tenant_id')->constrained('tenants');
        $tabla->string('titulo');
        $tabla->string('estado');
        $tabla->timestamps();
    });

    $this->empresa = Tenant::factory()->create(['nombre' => 'Empresa del listado']);
});

/**
 * Arma el listado a partir de una dirección, como lo hace un controlador.
 *
 * La petición se deja además como la de la aplicación, que es lo que lee
 * `withQueryString()` para conservar los filtros en los enlaces de la
 * paginación. Un controlador lo tiene gratis; aquí hay que decirlo.
 */
function listadoDePrueba(string $consulta = ''): ListadoDePrueba
{
    $peticion = Request::create('/registros'.($consulta === '' ? '' : '?'.$consulta));

    app()->instance('request', $peticion);

    return ListadoDePrueba::desde($peticion);
}

/**
 * Los títulos que devuelve el listado, en el orden en que salen.
 *
 * @param  list<array{titulo: string, estado?: string}>  $registros
 * @return list<string>
 */
function titulosDelListado(array $registros, string $consulta = ''): array
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function () use ($registros, $consulta): array {
        foreach ($registros as $registro) {
            RegistroListable::create([
                'titulo' => $registro['titulo'],
                'estado' => $registro['estado'] ?? 'abierto',
            ]);
        }

        return listadoDePrueba($consulta)->consulta()->pluck('titulo')->all();
    });
}

it('pagina con el tamaño por defecto cuando la dirección no pide ninguno', function () {
    expect(listadoDePrueba()->porPagina)->toBe(Listado::POR_PAGINA_POR_DEFECTO);
});

it('acepta los tamaños de página permitidos y descarta cualquier otro', function () {
    expect(listadoDePrueba('porPagina=50')->porPagina)->toBe(50)
        ->and(listadoDePrueba('porPagina=100')->porPagina)->toBe(100)
        // Siete no está entre los tres del documento 3: no es un error, se cae
        // al valor por defecto.
        ->and(listadoDePrueba('porPagina=7')->porPagina)->toBe(Listado::POR_PAGINA_POR_DEFECTO);
});

it('ordena por una columna declarada y descarta las que no lo están', function () {
    expect(listadoDePrueba('orden=estado')->orden)->toBe('estado')
        // La lista blanca son las claves del mapa de columnas: una columna que
        // el listado no declara no puede ordenar, y menos sacar un dato que la
        // pantalla no muestra.
        ->and(listadoDePrueba('orden=salario')->orden)->toBe('titulo')
        ->and(listadoDePrueba('orden=todos')->orden)->toBe('titulo');
});

it('ordena ascendente cuando la dirección no es asc ni desc', function () {
    expect(listadoDePrueba('direccion=desc')->direccion)->toBe('desc')
        ->and(listadoDePrueba('direccion=; drop table')->direccion)->toBe('asc');
});

it('aplica el orden pedido', function () {
    // Títulos sin tildes a propósito: lo que se prueba es el sentido del
    // orden, no la colación de la base de datos.
    expect(titulosDelListado([
        ['titulo' => 'Camara'],
        ['titulo' => 'Abaco'],
        ['titulo' => 'Bicicleta'],
    ], 'orden=titulo&direccion=desc'))->toBe(['Camara', 'Bicicleta', 'Abaco']);
});

it('desempata por id en dirección ascendente, para que una fila no salga dos veces entre páginas', function () {
    // Tres filas con el mismo valor en la columna ordenada, sin pedir
    // dirección: el desempate por defecto es ascendente y coincide con el
    // orden de creación. Sin el desempate, el orden entre ellas no está
    // definido y la página siguiente puede repetir una o saltarse otra.
    expect(titulosDelListado([
        ['titulo' => 'Primero', 'estado' => 'abierto'],
        ['titulo' => 'Segundo', 'estado' => 'abierto'],
        ['titulo' => 'Tercero', 'estado' => 'abierto'],
    ], 'orden=estado'))->toBe(['Primero', 'Segundo', 'Tercero']);
});

it('desempata en la misma dirección pedida, no siempre ascendente', function () {
    // Las tres filas empatan en la columna ordenada y se pide `direccion=desc`
    // —«lo más reciente primero»—. Desempatar siempre ascendente por id
    // devolvería exactamente el orden contrario al que se pidió: el creado
    // primero apareciendo de primero, cuando la dirección pedida dice lo
    // opuesto. Corregido el 2026-08-25, anotado desde REV-04.
    expect(titulosDelListado([
        ['titulo' => 'Primero', 'estado' => 'abierto'],
        ['titulo' => 'Segundo', 'estado' => 'abierto'],
        ['titulo' => 'Tercero', 'estado' => 'abierto'],
    ], 'orden=estado&direccion=desc'))->toBe(['Tercero', 'Segundo', 'Primero']);
});

it('deja el filtro de lista en su valor por defecto si el pedido no existe', function () {
    expect(listadoDePrueba()->estado)->toBe('abierto')
        ->and(listadoDePrueba('estado=cerrado')->estado)->toBe('cerrado')
        ->and(listadoDePrueba('estado=inventado')->estado)->toBe('abierto');
});

it('apaga el filtro de lista con «todos»', function () {
    expect(listadoDePrueba('estado=todos')->estado)->toBe(Listado::TODOS)
        ->and(titulosDelListado([
            ['titulo' => 'Abierto', 'estado' => 'abierto'],
            ['titulo' => 'Cerrado', 'estado' => 'cerrado'],
        ], 'estado=todos&orden=titulo'))->toBe(['Abierto', 'Cerrado']);
});

it('filtra la consulta que comparten la pantalla y la exportación', function () {
    expect(titulosDelListado([
        ['titulo' => 'Abierto', 'estado' => 'abierto'],
        ['titulo' => 'Cerrado', 'estado' => 'cerrado'],
    ]))->toBe(['Abierto']);
});

it('cuenta en total exactamente lo filtrado, que es lo que dice el botón de exportar', function () {
    app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
        RegistroListable::create(['titulo' => 'Uno', 'estado' => 'abierto']);
        RegistroListable::create(['titulo' => 'Dos', 'estado' => 'abierto']);
        RegistroListable::create(['titulo' => 'Tres', 'estado' => 'cerrado']);

        expect(listadoDePrueba()->total())->toBe(2)
            ->and(listadoDePrueba('estado=todos')->total())->toBe(3)
            ->and(listadoDePrueba('buscar=Un')->total())->toBe(1);
    });
});

it('pagina conservando los filtros de la dirección', function () {
    app(ContextoTenant::class)->paraTenant($this->empresa, function (): void {
        RegistroListable::create(['titulo' => 'Uno', 'estado' => 'abierto']);
        RegistroListable::create(['titulo' => 'Dos', 'estado' => 'abierto']);

        $pagina = listadoDePrueba('estado=abierto&porPagina=50')->pagina();

        expect($pagina->total())->toBe(2)
            ->and($pagina->perPage())->toBe(50)
            // `withQueryString()` es lo que hace que al volver de la ficha los
            // filtros sigan puestos.
            ->and($pagina->url(1))->toContain('estado=abierto');
    });
});

it('entrega a la pantalla los filtros propios y los tres que comparten todos los listados', function () {
    expect(listadoDePrueba('buscar=jose&estado=cerrado&orden=estado&direccion=desc&porPagina=100')->paraLaInterfaz())
        ->toBe([
            'buscar' => 'jose',
            'estado' => 'cerrado',
            'orden' => 'estado',
            'direccion' => 'desc',
            'porPagina' => 100,
        ]);
});

it('distingue la pantalla vacía sin filtros de la que no encontró nada', function () {
    // El estado sale siempre que filtre, incluido su valor por defecto: si no
    // se viera, parecería que faltan registros.
    expect(listadoDePrueba()->tieneFiltros())->toBeTrue()
        ->and(listadoDePrueba()->aplicados())->toBe([['clave' => 'estado', 'texto' => 'Estado: abierto']])
        ->and(listadoDePrueba('estado=todos')->tieneFiltros())->toBeFalse()
        ->and(listadoDePrueba('estado=todos')->aplicados())->toBe([]);
});

it('no alcanza los registros de otra empresa por el armazón', function () {
    $otra = Tenant::factory()->create(['nombre' => 'Empresa ajena']);
    $contexto = app(ContextoTenant::class);

    $contexto->paraTenant($otra, fn () => RegistroListable::create([
        'titulo' => 'Confidencial de la otra',
        'estado' => 'abierto',
    ]));

    // El armazón no escribe ni un `where('tenant_id', ...)`: el filtro lo pone
    // el trait del modelo, y por eso hereda el aislamiento sin saber nada de él.
    expect(titulosDelListado([['titulo' => 'Propio']], 'estado=todos'))->toBe(['Propio']);

    $contexto->paraTenant($otra, function (): void {
        expect(listadoDePrueba('estado=todos')->total())->toBe(1);
    });
});

it('los tres listados del sistema salen del mismo armazón', function () {
    expect(ListadoDeEmpleados::desde(Request::create('/empleados')))->toBeInstanceOf(Listado::class)
        ->and(ListadoDeContratos::desde(Request::create('/contratos')))->toBeInstanceOf(Listado::class)
        ->and(ListadoDeEmpresas::desde(Request::create('/proveedor/empresas')))->toBeInstanceOf(Listado::class)
        // Los tamaños de página y el valor «todos» se declaran una vez: el día
        // que cambien, cambian en los siete listados a la vez.
        ->and(ListadoDeEmpleados::TAMANOS_DE_PAGINA)->toBe(Listado::TAMANOS_DE_PAGINA)
        ->and(ListadoDeContratos::TAMANOS_DE_PAGINA)->toBe(Listado::TAMANOS_DE_PAGINA)
        ->and(ListadoDeEmpresas::TAMANOS_DE_PAGINA)->toBe(Listado::TAMANOS_DE_PAGINA)
        ->and(ListadoDeEmpleados::TODOS)->toBe(Listado::TODOS);
});

it('ningún listado vuelve a copiar el armazón', function () {
    // El motivo de la tarea escrito como prueba: el documento 3 cuenta siete
    // pantallas de listado. Si el patrón se vuelve a copiar, esto se pone rojo
    // antes de que sean siete copias.
    $copiados = ['opcion', 'porPagina', 'pagina', 'total', 'tieneFiltros', 'paraLaInterfaz', 'ordenar'];

    $listados = [
        'app/Contratacion/ListadoDeEmpleados.php',
        'app/Contratacion/ListadoDeContratos.php',
        'app/Proveedor/ListadoDeEmpresas.php',
    ];

    foreach ($listados as $archivo) {
        $codigo = (string) file_get_contents(base_path($archivo));

        foreach ($copiados as $metodo) {
            expect($codigo)->not->toContain("function {$metodo}(");
        }
    }
});

/*
 * Las props de la pantalla (revisión de F2-11).
 *
 * El armazón resolvía la consulta, pero cada controlador seguía armando a mano
 * el literal con el que la página viaja a Inertia. Eran ocho copias del mismo
 * bloque, y una ya se había separado: el panel de empresas escribía `total`
 * antes que `pagina`.
 */
it('entrega la página y los tres bloques de filtros ya armados para la pantalla', function () {
    app(ContextoTenant::class)->paraTenant(test()->empresa, function (): void {
        RegistroListable::create(['titulo' => 'Primero', 'estado' => 'abierto']);
        RegistroListable::create(['titulo' => 'Segundo', 'estado' => 'abierto']);

        $props = listadoDePrueba('buscar=Primero')->paraLaPantalla(
            'registros',
            fn (RegistroListable $registro): array => ['titulo' => $registro->titulo],
        );

        expect($props)->toHaveKeys(['registros', 'filtros', 'aplicados', 'tieneFiltros'])
            ->and($props['registros']['filas'])->toBe([['titulo' => 'Primero']])
            ->and($props['registros']['pagina'])->toBe(1)
            ->and($props['registros']['paginas'])->toBe(1)
            ->and($props['registros']['total'])->toBe(1)
            ->and($props['registros']['desde'])->toBe(1)
            ->and($props['registros']['hasta'])->toBe(1)
            // Los tres bloques de filtros son los mismos que responden los
            // métodos sueltos: la pantalla no puede recibir dos versiones.
            ->and($props['filtros'])->toBe(listadoDePrueba('buscar=Primero')->paraLaInterfaz())
            ->and($props['tieneFiltros'])->toBeTrue()
            ->and($props['aplicados'])->toBe([
                ['clave' => 'buscar', 'texto' => 'Búsqueda: Primero'],
                // El fixture abre con `estado=abierto`, así que ese filtro
                // también viaja puesto.
                ['clave' => 'estado', 'texto' => 'Estado: abierto'],
            ]);
    });
});

it('ningún controlador de listado vuelve a armar el bloque de paginación', function () {
    // Ocho controladores escribían `'pagina' => $pagina->currentPage()` y sus
    // cinco hermanos. Si vuelve a aparecer, esto se pone rojo.
    $controladores = [
        'app/Http/Controllers/EmpleadoController.php',
        'app/Http/Controllers/ContratoController.php',
        'app/Http/Controllers/IncapacidadController.php',
        'app/Http/Controllers/VacacionController.php',
        'app/Http/Controllers/LicenciaController.php',
        'app/Http/Controllers/MovimientoController.php',
        'app/Http/Controllers/TerminacionController.php',
        'app/Http/Controllers/Proveedor/EmpresaController.php',
    ];

    foreach ($controladores as $archivo) {
        $codigo = (string) file_get_contents(base_path($archivo));

        expect($codigo)->toContain('paraLaPantalla(')
            ->and($codigo)->not->toContain('currentPage()')
            ->and($codigo)->not->toContain('lastPage()')
            ->and($codigo)->not->toContain('firstItem()');
    }
});
