<?php

use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Multiempresa\PoliticaRls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
 * Registra los cinco casos de aislamiento de una tabla de negocio.
 *
 * @param  array{
 *     tabla: string,
 *     modelo: class-string<Model>,
 *     campo: string,
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
        expect($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it("{$etiqueta}: no incluye registros de otra empresa en la exportación", function () use ($caso) {
        $escena = prepararAislamiento($caso);

        $respuesta = test()->actingAs($escena->usuarioB)
            ->get("/aislamiento/{$caso['tabla']}/exportar");

        $respuesta->assertOk();
        expect($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
    });

    it("{$etiqueta}: no encuentra por búsqueda un registro de otra empresa", function () use ($caso) {
        $escena = prepararAislamiento($caso);

        // Se busca el texto exacto: si el filtro de empresa faltara, este es el
        // camino que más fácil lo devolvería.
        $respuesta = test()->actingAs($escena->usuarioB)
            ->get("/aislamiento/{$caso['tabla']}?buscar=".urlencode(SECRETO_DE_LA_A));

        $respuesta->assertOk()->assertJsonCount(0);
        expect($respuesta->getContent())->not->toContain(SECRETO_DE_LA_A);
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
            ->assertSee(SECRETO_DE_LA_A);

        $exportacion = test()->actingAs($escena->usuarioA)
            ->get("/aislamiento/{$caso['tabla']}/exportar");

        expect($exportacion->getContent())->toContain(SECRETO_DE_LA_A);

        test()->actingAs($escena->usuarioA)
            ->get("/aislamiento/{$caso['tabla']}?buscar=".urlencode(SECRETO_DE_LA_A))
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

    $registro = $contexto->paraTenant($empresaA, fn () => ($caso['crear'])(SECRETO_DE_LA_A));

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
            $modelo::query()->pluck($campo)->implode("\n"),
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
