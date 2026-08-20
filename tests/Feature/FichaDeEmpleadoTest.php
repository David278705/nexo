<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EstadoCivil;
use App\Contratacion\EventoContractual;
use App\Contratacion\NivelEducativo;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoRiesgo;
use App\Models\Eps;
use App\Models\PerfilSociodemografico;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;

/**
 * La ficha del empleado, F1-07: la sección 5.3 del documento 3.
 *
 * Lo que se defiende aquí, en orden de importancia:
 *
 * 1. **Las cuatro pestañas cargan y el saldo se ve arriba**, que es el «hecho
 *    cuando» de la tarea. El saldo se ve diciendo que todavía no se puede
 *    calcular: el motor es F2-02 y la regla 2 prohíbe guardarlo en una columna,
 *    así que cualquier número que apareciera hoy sería inventado.
 * 2. **La ficha enseña lo que hay, no lo que falta.** Un campo sin llenar se
 *    dice con una raya, y el perfil sociodemográfico sin diligenciar con una
 *    frase, no con ocho renglones vacíos.
 * 3. **El bloque sociodemográfico aparece solo si la empresa lo encendió**, tal
 *    como en el asistente de F1-06.
 * 4. **El historial del contrato sale de `HistorialDeContrato`**, el mismo
 *    objeto de F1-02, y empieza por el periodo inicial.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoDe()` en
 * `EmpleadosTest.php`: Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);
});

/**
 * Las props que el servidor manda a la ficha de ese empleado.
 *
 * @return array<string, mixed>
 */
function fichaDe(Vinculacion $empleado): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/empleados/'.$empleado->getKey());

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props'];
}

/**
 * El valor de un campo dentro de un bloque de la pestaña de datos.
 *
 * @param  array<string, mixed>  $props
 */
function campoDeLaFicha(array $props, string $bloque, string $etiqueta): ?string
{
    foreach ($props['datos'] as $grupo) {
        if ($grupo['titulo'] !== $bloque) {
            continue;
        }

        foreach ($grupo['campos'] as $campo) {
            if ($campo['etiqueta'] === $etiqueta) {
                return $campo['valor'];
            }
        }
    }

    return null;
}

/**
 * @param  array<string, mixed>  $props
 */
function datoDelResumen(array $props, string $etiqueta): ?array
{
    foreach ($props['resumen'] as $dato) {
        if ($dato['etiqueta'] === $etiqueta) {
            return $dato;
        }
    }

    return null;
}

describe('el encabezado', function () {
    it('enseña nombre, cargo y estado', function () {
        $empleado = empleadoDe(
            ['primer_nombre' => 'Carolina', 'segundo_nombre' => null, 'primer_apellido' => 'Restrepo', 'segundo_apellido' => 'Vélez'],
            ['cargo' => 'Coordinadora de Operaciones'],
        );

        $props = fichaDe($empleado);

        expect($props['empleado']['nombre'])->toBe('Carolina Restrepo Vélez')
            ->and($props['empleado']['cargo'])->toBe('Coordinadora de Operaciones')
            ->and($props['empleado']['estado'])->toBe('Activo')
            ->and($props['empleado']['activo'])->toBeTrue();
    });

    it('trae los seis datos del resumen, con el saldo de vacaciones de último', function () {
        $props = fichaDe(empleadoDe());

        $etiquetas = array_map(fn (array $dato) => $dato['etiqueta'], $props['resumen']);

        expect($etiquetas)->toBe([
            'Documento',
            'Fecha de ingreso',
            'Antigüedad',
            'Tipo de contrato',
            'Salario',
            'Saldo de vacaciones',
        ]);
    });

    /*
     * La regla 2 del proyecto: el saldo se calcula, no se guarda. Mientras el
     * motor de cálculo no exista, el único número honesto es ninguno. Cuando
     * F2-02 esté, esta prueba se cambia por la del saldo de verdad.
     */
    it('dice que el saldo de vacaciones todavía no se puede calcular, en vez de enseñar un cero', function () {
        $props = fichaDe(empleadoDe());

        $saldo = datoDelResumen($props, 'Saldo de vacaciones');

        expect($saldo['pendiente'])->toBeTrue()
            ->and($saldo['valor'])->not->toContain('0')
            ->and($saldo['nota'])->not->toBe('');
    });

    it('cuenta la antigüedad en años y meses cumplidos desde el ingreso', function () {
        Carbon::setTestNow('2026-08-19');

        $empleado = empleadoDe([], ['fecha_ingreso' => '2022-02-03']);

        expect(datoDelResumen(fichaDe($empleado), 'Antigüedad')['valor'])->toBe('4 años, 6 meses');
    });

    it('dice «Menos de un mes» de quien acaba de entrar', function () {
        Carbon::setTestNow('2026-08-19');

        $empleado = empleadoDe([], ['fecha_ingreso' => '2026-08-10']);

        expect(datoDelResumen(fichaDe($empleado), 'Antigüedad')['valor'])->toBe('Menos de un mes');
    });

    it('deja de contar la antigüedad de quien ya salió', function () {
        Carbon::setTestNow('2026-08-19');

        $empleado = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create())
            ->aTerminoFijo('2024-01-01', '2025-01-01')
            ->terminada()
            ->create());

        // Un año exacto, no los dos y medio que llevaría contando hasta hoy.
        expect(datoDelResumen(fichaDe($empleado), 'Antigüedad')['valor'])->toBe('1 año');
    });

    it('enseña el salario en formato colombiano', function () {
        $empleado = empleadoDe([], ['salario' => 3_200_000]);

        expect(datoDelResumen(fichaDe($empleado), 'Salario')['valor'])->toBe('$ 3.200.000');
    });
});

describe('la pestaña de datos personales', function () {
    it('trae los tres bloques de siempre', function () {
        $props = fichaDe(empleadoDe());

        $titulos = array_map(fn (array $bloque) => $bloque['titulo'], $props['datos']);

        expect($titulos)->toBe(['Identificación', 'Residencia', 'Seguridad social']);
    });

    it('enseña las etiquetas de los enumerados, no lo que guarda la base', function () {
        $empleado = empleadoDe([], ['tipo_riesgo' => TipoRiesgo::Cuatro]);

        expect(campoDeLaFicha(fichaDe($empleado), 'Seguridad social', 'Nivel de riesgo'))
            ->toBe('Riesgo IV – Alto')
            ->and(campoDeLaFicha(fichaDe($empleado), 'Identificación', 'Tipo de documento'))
            ->toBe('Cédula de ciudadanía');
    });

    it('enseña el nombre de la entidad afiliada y una raya donde no hay ninguna', function () {
        $eps = Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]);
        $empleado = empleadoDe([], ['eps_id' => $eps->getKey()]);

        expect(campoDeLaFicha(fichaDe($empleado), 'Seguridad social', 'EPS'))->toBe('Sura EPS')
            ->and(campoDeLaFicha(fichaDe($empleado), 'Seguridad social', 'Banco de nómina'))->toBe('—');
    });

    it('compone el lugar de nacimiento con ciudad, departamento y país', function () {
        $empleado = empleadoDe();

        $lugar = campoDeLaFicha(fichaDe($empleado), 'Identificación', 'Lugar de nacimiento');
        $ciudad = $empleado->persona->ciudadNacimiento;

        expect($lugar)->toBe($ciudad->nombre.', '.$ciudad->departamento->nombre.', Colombia');
    });

    it('usa el texto digitado a mano cuando la persona nació fuera de Colombia', function () {
        $empleado = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->nacidaEnElExterior()->create())
            ->create());

        expect(campoDeLaFicha(fichaDe($empleado), 'Identificación', 'Lugar de nacimiento'))
            ->toContain('Maracaibo')
            ->toContain('Zulia');
    });
});

describe('el perfil sociodemográfico en la ficha', function () {
    it('no aparece si la empresa no lo tiene habilitado', function () {
        $props = fichaDe(empleadoDe());

        $titulos = array_map(fn (array $bloque) => $bloque['titulo'], $props['datos']);

        expect($titulos)->not->toContain('Perfil sociodemográfico');
    });

    it('aparece con lo diligenciado cuando la empresa lo tiene habilitado', function () {
        $this->empresa->forceFill(['perfil_sociodemografico_habilitado' => true])->save();

        $empleado = empleadoDe();

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => PerfilSociodemografico::query()->create([
            'persona_id' => $empleado->persona_id,
            'estado_civil' => EstadoCivil::cases()[0],
            'nivel_educativo' => NivelEducativo::cases()[0],
            'estrato' => 3,
        ]));

        $props = fichaDe($empleado);

        expect(campoDeLaFicha($props, 'Perfil sociodemográfico', 'Estrato'))->toBe('Estrato 3')
            ->and(campoDeLaFicha($props, 'Perfil sociodemográfico', 'Número de hijos'))->toBe('—');
    });

    /*
     * Encender el parámetro con la planta ya cargada no marca a nadie como
     * incompleto: es la mitad del «hecho cuando» de F1-06 y aquí es donde se
     * ve, porque la ficha es la pantalla que lo enseñaría en rojo si se hiciera
     * mal.
     */
    it('se explica con una frase cuando está habilitado y sin diligenciar', function () {
        $this->empresa->forceFill(['perfil_sociodemografico_habilitado' => true])->save();

        $props = fichaDe(empleadoDe());

        $bloque = collect($props['datos'])->firstWhere('titulo', 'Perfil sociodemográfico');

        expect($bloque['campos'])->toBe([])
            ->and($bloque['vacio'])->not->toBe('');
    });
});

describe('la pestaña de contrato', function () {
    it('enseña el periodo inicial en el historial de un contrato recién creado', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2026-01-15']);

        $historial = fichaDe($empleado)['contrato']['historial'];

        expect($historial)->toHaveCount(1)
            ->and($historial[0]['orden'])->toBe(1)
            ->and($historial[0]['evento'])->toBe(EventoContractual::Inicial->etiqueta())
            ->and($historial[0]['desde'])->toBe('15/01/2026');
    });

    it('enseña una fila por prórroga, empezando por el contrato inicial', function () {
        $empleado = app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->aTerminoFijo('2025-01-01', '2025-12-31')
                ->create();

            $vinculacion->registrarPeriodo(
                EventoContractual::ProrrogaPactada,
                TipoContrato::Fijo,
                Carbon::parse('2026-01-01'),
                Carbon::parse('2026-12-31'),
            );

            return $vinculacion;
        });

        $historial = fichaDe($empleado)['contrato']['historial'];

        expect($historial)->toHaveCount(2)
            ->and(array_column($historial, 'orden'))->toBe([1, 2])
            ->and($historial[1]['evento'])->toBe(EventoContractual::ProrrogaPactada->etiqueta());
    });

    it('agrega el tope de cuatro años solo donde hay plazo que topar', function () {
        $fijo = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create())
            ->aTerminoFijo('2026-01-01', '2026-12-31')
            ->create());

        $etiquetasDelFijo = array_column(fichaDe($fijo)['contrato']['campos'], 'etiqueta');
        $etiquetasDelIndefinido = array_column(fichaDe(empleadoDe())['contrato']['campos'], 'etiqueta');

        expect($etiquetasDelFijo)->toContain('Tope legal de cuatro años')
            ->and($etiquetasDelIndefinido)->not->toContain('Tope legal de cuatro años');
    });
});

describe('las pestañas que esperan su módulo', function () {
    it('carga las novedades y los movimientos vacíos, sin romperse', function () {
        $props = fichaDe(empleadoDe());

        expect($props['novedades'])->toBe([])
            ->and($props['movimientos'])->toBe([]);
    });
});

describe('el acceso a la ficha', function () {
    it('devuelve 403 sin el módulo de empleados asignado', function () {
        $empleado = empleadoDe();
        $sinModulo = usuarioDe($this->empresa, Rol::Administrador);

        $this->actingAs($sinModulo)->get('/empleados/'.$empleado->getKey())->assertForbidden();
    });

    it('carga la ficha con el componente de la pantalla', function () {
        $empleado = empleadoDe();

        $this->actingAs($this->usuario)
            ->get('/empleados/'.$empleado->getKey())
            ->assertInertia(fn ($pagina) => $pagina->component('Empleados/Ficha')->etc());
    });
});
