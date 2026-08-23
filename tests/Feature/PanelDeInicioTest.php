<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\ListadoDeEmpleados;
use App\Contratacion\VigenciaDelContrato;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Http\Middleware\HandleInertiaRequests;
use App\Inicio\AtencionDelInicio;
use App\Inicio\BloqueDeAccion;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El panel de inicio, bloque de acción (F4-03 · documento 1, capítulo 12,
 * bloque 1 · documento 3, sección 5.2).
 *
 * Lo que hay que poder demostrar es el «hecho cuando» de la tarea —pulsar
 * «contratos por vencer» lleva al listado filtrado— y las tres cosas que hacen
 * que una tarjeta de acción sea una tarjeta de acción y no un indicador: que la
 * cifra sea cierta, que el destino traiga exactamente esa cifra y que no lleve a
 * un sitio donde el usuario recibiría un 403.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create([
        'nombre' => 'Distribuidora El Progreso',
        'sabado_laboral' => false,
    ]);
    $this->contexto = app(ContextoTenant::class);

    // Un lunes cualquiera, para que «esta semana» sea una semana entera y las
    // cuentas de la prueba no dependan del día en que se corra.
    $this->hoy = Carbon::parse('2026-08-17');
    Carbon::setTestNow($this->hoy->copy()->setTime(9, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Las tarjetas que ve un superadministrador, que puede entrar a todo.
 *
 * Las dos vueltas juntas: la que el panel pinta de entrada y la diferida. Aquí
 * se prueban a la vez porque lo que cambia entre ellas es cuándo llegan, no lo
 * que dicen.
 *
 * @return list<array<string, mixed>>
 */
function tarjetasDeAccion(): array
{
    $usuario = usuarioDe(test()->empresa, Rol::Superadministrador);
    $bloque = app(BloqueDeAccion::class);

    return test()->contexto->paraTenant(test()->empresa, fn (): array => [
        ...$bloque->paraElUsuario($usuario, test()->hoy),
        ...$bloque->diferidasParaElUsuario($usuario, test()->hoy),
    ]);
}

/**
 * La tarjeta cuya etiqueta contiene ese texto, o nulo si no está.
 *
 * @return array<string, mixed>|null
 */
function tarjetaCon(string $texto): ?array
{
    foreach (tarjetasDeAccion() as $tarjeta) {
        if (str_contains($tarjeta['etiqueta'], $texto)) {
            return $tarjeta;
        }
    }

    return null;
}

/**
 * Cuántas filas trae el listado de empleados al abrir el destino de una tarjeta.
 */
function empleadosEn(string $enlace): int
{
    return test()->contexto->paraTenant(
        test()->empresa,
        fn (): int => ListadoDeEmpleados::desde(Request::create($enlace))->total(),
    );
}

describe('el «hecho cuando»: la tarjeta de contratos por vencer', function () {
    beforeEach(function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Uno en cada rango, contando desde el 17 de agosto.
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-09-05')->create();
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-10-05')->create();
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-11-05')->create();
            // Fuera de los tres rangos: vence en cinco meses.
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2027-01-20')->create();
        });
    });

    it('enseña las tres cifras acumuladas del capítulo 12', function () {
        $tarjeta = tarjetaCon('en 30 días');

        // El de 60 incluye al de 30, y el de 90 incluye a ambos.
        expect($tarjeta['valor'])->toBe(1)
            ->and($tarjeta['extras'][0]['texto'])->toBe('2 en 60 días')
            ->and($tarjeta['extras'][1]['texto'])->toBe('3 en 90 días');
    });

    it('cada cifra lleva al listado de contratos filtrado a su rango', function () {
        $tarjeta = tarjetaCon('en 30 días');

        expect($tarjeta['enlace'])->toBe('/contratos?vence=30')
            ->and($tarjeta['extras'][0]['enlace'])->toBe('/contratos?vence=60')
            ->and($tarjeta['extras'][1]['enlace'])->toBe('/contratos?vence=90');
    });

    it('el listado de destino trae exactamente los contratos que la tarjeta cuenta', function () {
        $tarjeta = tarjetaCon('en 30 días');

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);

        $respuesta = $this->actingAs($usuario)->get($tarjeta['enlace']);

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['contratos']['total'])->toBe($tarjeta['valor']);
    });

    it('no se pinta cuando no vence ninguno en noventa días', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::query()->update(['fecha_fin' => '2027-06-30']);
        });

        expect(tarjetaCon('en 30 días'))->toBeNull();
    });

    it('sale aunque no venza ninguno en treinta días, si vence alguno en noventa', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::query()->where('fecha_fin', '2026-09-05')->update(['fecha_fin' => '2026-10-06']);
        });

        $tarjeta = tarjetaCon('en 30 días');

        expect($tarjeta['valor'])->toBe(0)
            ->and($tarjeta['extras'][1]['texto'])->toBe('3 en 90 días');
    });
});

describe('la tarjeta de contratos vencidos sin gestionar', function () {
    it('cuenta los que vencieron sin prórroga ni terminación y va en rojo', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2026-06-30')->create();
            Vinculacion::factory()->aTerminoFijo('2025-01-01', '2026-08-01')->create();
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create();
        });

        $tarjeta = tarjetaCon('Contratos vencidos sin gestionar');

        expect($tarjeta['valor'])->toBe(2)
            ->and($tarjeta['tono'])->toBe('error');
    });

    it('pide todos los vencimientos, no los noventa días con que abre el listado', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Venció hace más de noventa días: con el filtro por defecto del
            // listado no aparecería, y es justo el que más urge.
            Vinculacion::factory()->aTerminoFijo('2024-01-01', '2026-02-10')->create();
        });

        $tarjeta = tarjetaCon('Contrato vencido sin gestionar');

        expect($tarjeta['enlace'])
            ->toBe('/contratos?vence=todos&estado='.VigenciaDelContrato::VENCIDO);

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
        $respuesta = $this->actingAs($usuario)->get($tarjeta['enlace']);

        expect($respuesta->viewData('page')['props']['contratos']['total'])->toBe(1);
    });

    it('no se pinta cuando no hay ninguno vencido', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create();
        });

        expect(tarjetaCon('vencido'))->toBeNull();
    });
});

describe('la tarjeta de periodos de prueba', function () {
    it('cuenta los que terminan entre hoy y el domingo', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Indefinido: dos meses. Termina el miércoles 19, dentro de la semana.
            Vinculacion::factory()->conIngreso('2026-06-20')->create();
            // Termina el 16 de agosto, ayer: ya no se puede hacer nada a tiempo.
            Vinculacion::factory()->conIngreso('2026-06-17')->create();
            // Termina el 24 de agosto, el lunes siguiente.
            Vinculacion::factory()->conIngreso('2026-06-25')->create();
        });

        expect(tarjetaCon('Periodo de prueba termina esta semana')['valor'])->toBe(1);
    });

    it('deja fuera el contrato de obra o labor, que no tiene periodo de prueba (9.6)', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->deObraOLabor()->conIngreso('2026-06-20')->create();
        });

        expect(tarjetaCon('Periodo de prueba'))->toBeNull();
    });

    it('deja fuera al empleado que ya no está activo', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->terminada()->conIngreso('2026-06-20')->create();
        });

        expect(tarjetaCon('Periodo de prueba'))->toBeNull();
    });

    it('lleva al listado de empleados, que trae exactamente esa gente', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->conIngreso('2026-06-20')->create();
            Vinculacion::factory()->conIngreso('2020-03-01')->create();
        });

        $tarjeta = tarjetaCon('Periodo de prueba termina esta semana');

        expect($tarjeta['enlace'])->toBe('/empleados?atencion=periodos-de-prueba')
            ->and(empleadosEn($tarjeta['enlace']))->toBe(1);
    });
});

describe('la tarjeta de vacaciones acumuladas', function () {
    beforeEach(function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            // Trece meses cerrados al 17 de agosto de 2026: 16,25 días.
            Vinculacion::factory()->conIngreso('2025-07-01')->create();
            // Cinco meses: 6,25 días.
            Vinculacion::factory()->conIngreso('2026-03-01')->create();
        });
    });

    it('cuenta a quien ya tiene acumulado un periodo completo', function () {
        expect(tarjetaCon('vacaciones acumuladas')['valor'])->toBe(1);
    });

    it('escribe los quince días y no los inventa', function () {
        expect(tarjetaCon('vacaciones acumuladas')['etiqueta'])
            ->toBe('Persona con 15 días o más de vacaciones acumuladas');

        expect(SaldoDeVacaciones::diasDeUnPeriodoCompleto())->toBe(15.0);
    });

    it('deja de contar a quien disfruta sus vacaciones, sin operación inversa', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            $antigua = Vinculacion::query()->whereDate('fecha_ingreso', '2025-07-01')->firstOrFail();

            Vacacion::factory()->de($antigua)->create([
                'fecha_inicio' => '2026-08-03',
                'fecha_fin' => '2026-08-14',
            ]);
        });

        expect(tarjetaCon('vacaciones acumuladas'))->toBeNull();
    });

    it('lleva al listado de empleados, que trae exactamente esa gente', function () {
        $tarjeta = tarjetaCon('vacaciones acumuladas');

        expect($tarjeta['enlace'])->toBe('/empleados?atencion=vacaciones-acumuladas')
            ->and(empleadosEn($tarjeta['enlace']))->toBe(1);
    });

    /*
     * Es la única que tarda —el saldo se calcula, no se guarda— y por eso llega
     * en la segunda vuelta. Si alguna vez se colara en la primera, el panel
     * volvería a esperar por ella sin que nadie se enterara.
     */
    it('no llega en la primera vuelta, para que el panel no espere por ella', function () {
        $usuario = usuarioDe($this->empresa, Rol::Superadministrador);

        $primera = $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => app(BloqueDeAccion::class)->paraElUsuario($usuario, $this->hoy),
        );

        expect(array_column($primera, 'etiqueta'))
            ->not->toContain('Persona con 15 días o más de vacaciones acumuladas');
    });
});

describe('el filtro «atencion» del listado de empleados', function () {
    it('sale como etiqueta removible, para poder volver a la planta entera', function () {
        $listado = ListadoDeEmpleados::desde(
            Request::create('/empleados?atencion=vacaciones-acumuladas')
        );

        expect($listado->aplicados())->toContain([
            'clave' => 'atencion',
            'texto' => 'Con 15 días o más acumulados',
        ]);
    });

    it('un valor que no existe no filtra, como los demás filtros del armazón', function () {
        $listado = ListadoDeEmpleados::desde(Request::create('/empleados?atencion=lo-que-sea'));

        $claves = array_column($listado->aplicados(), 'clave');

        expect($listado->atencion)->toBeNull()
            ->and($claves)->not->toContain('atencion');
    });

    it('no se ofrece en la barra de filtros, porque no es de la tabla 11.3', function () {
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $opciones = $this->actingAs($usuario)->get('/empleados')
            ->viewData('page')['props']['opciones'];

        expect($opciones)->not->toHaveKey('atenciones');
    });
});

describe('la pantalla', function () {
    it('llega con la fecha escrita y las tarjetas ya resueltas', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-09-05')->create();
        });

        $usuario = usuarioDe($this->empresa, Rol::Superadministrador);

        $props = $this->actingAs($usuario)->get('/')->viewData('page')['props'];

        expect($props['fecha'])->toBe('Lunes, 17 de agosto de 2026')
            ->and($props['acciones'])->toHaveCount(1)
            ->and($props['acciones'][0]['etiqueta'])->toBe('Contrato vence en 30 días');
    });

    /**
     * La tarjeta que tarda no viene en la primera respuesta y llega en la
     * segunda vuelta que Inertia pide sola. Es lo que hace que el panel abra sin
     * esperar a que el motor recorra la planta.
     */
    it('deja la tarjeta de vacaciones para la segunda vuelta', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->conIngreso('2024-01-15')->create();
        });

        $usuario = usuarioDe($this->empresa, Rol::Superadministrador);

        $primera = $this->actingAs($usuario)->get('/')->viewData('page');

        expect($primera['props'])->not->toHaveKey('accionesDiferidas');

        $segunda = $this->actingAs($usuario)->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Inicio',
            'X-Inertia-Partial-Data' => 'accionesDiferidas',
        ]);

        expect($segunda->json()['props']['accionesDiferidas'][0]['etiqueta'])
            ->toBe('Persona con 15 días o más de vacaciones acumuladas');
    });

    it('no ofrece una tarjeta que llevaría a un 403', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-09-05')->create();
            Vinculacion::factory()->conIngreso('2026-06-20')->create();
        });

        // Tiene empleados pero no contratos: no puede ver ninguna de las dos
        // tarjetas que llevan al listado de contratos, ni en la primera vuelta
        // ni en la diferida.
        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $acciones = $this->actingAs($usuario)->get('/')->viewData('page')['props']['acciones'];

        foreach ($acciones as $accion) {
            expect($accion['enlace'])->toStartWith('/empleados');
        }

        expect($acciones)->toHaveCount(1);
    });

    it('tampoco ofrece la tarjeta diferida a quien no tiene el módulo de empleados', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->conIngreso('2024-01-15')->create();
        });

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);

        $diferidas = $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => app(BloqueDeAccion::class)->diferidasParaElUsuario($usuario, $this->hoy),
        );

        expect($diferidas)->toBe([]);
    });

    it('un usuario sin módulos no ve ninguna tarjeta y la pantalla sigue cargando', function () {
        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-09-05')->create();
        });

        $usuario = usuarioDe($this->empresa, Rol::Administrador);

        $respuesta = $this->actingAs($usuario)->get('/');

        $respuesta->assertOk();
        expect($respuesta->viewData('page')['props']['acciones'])->toBe([]);
    });
});

describe('el panel abre sin esperar a nadie', function () {
    /**
     * La medición que justifica que la tarjeta de vacaciones vaya diferida.
     *
     * Con doscientos empleados, resolver el bloque entero de una vez tardaba
     * **4,8 segundos**, porque contar a quién le sobran quince días obliga a
     * pedirle el saldo al motor uno por uno y el saldo se calcula, no se guarda.
     * Separada, la primera vuelta baja a **milésimas**. El umbral es el del
     * documento —el panel carga en menos de dos segundos—, y esta prueba se pone
     * en rojo el día en que alguien meta en la primera vuelta algo que recorra
     * la planta.
     */
    it('resuelve la primera vuelta con doscientos empleados en menos de dos segundos', function () {
        $usuario = usuarioDe($this->empresa, Rol::Superadministrador);

        $this->contexto->paraTenant($this->empresa, function (): void {
            // De una sola tanda para que compartan municipio: una fábrica por
            // persona agotaría los códigos de departamento del catálogo, y lo
            // que se está midiendo no es eso.
            Persona::factory()->count(200)->create()->each(
                fn (Persona $persona) => Vinculacion::factory()->de($persona)->conIngreso('2023-01-15')->create()
            );
        });

        $arranque = microtime(true);
        $this->contexto->paraTenant(
            $this->empresa,
            fn (): array => app(BloqueDeAccion::class)->paraElUsuario($usuario, $this->hoy),
        );
        $tardanza = microtime(true) - $arranque;

        expect($tardanza)->toBeLessThan(2.0);
    });
});

describe('el aislamiento', function () {
    it('las tarjetas de una empresa no cuentan los contratos de otra', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);

        $this->contexto->paraTenant($otra, function (): void {
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-09-05')->create();
            Vinculacion::factory()->aTerminoFijo('2024-01-01', '2026-02-10')->create();
        });

        $this->contexto->paraTenant($this->empresa, function (): void {
            Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-09-05')->create();
        });

        expect(tarjetaCon('en 30 días')['valor'])->toBe(1)
            ->and(tarjetaCon('vencido'))->toBeNull();
    });

    it('el filtro de atención no alcanza a los empleados de otra empresa', function () {
        $otra = Tenant::factory()->create(['nombre' => 'Textiles del Norte']);

        $this->contexto->paraTenant($otra, function (): void {
            Vinculacion::factory()->conIngreso('2026-06-20')->create();
        });

        expect(AtencionDelInicio::PeriodosDePrueba->cuantos($this->hoy))->toBe(0);

        $ajenos = $this->contexto->paraTenant(
            $this->empresa,
            fn (): int => empleadosEn('/empleados?atencion=periodos-de-prueba'),
        );

        expect($ajenos)->toBe(0);
    });
});
