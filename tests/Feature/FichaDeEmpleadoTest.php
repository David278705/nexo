<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EstadoCivil;
use App\Contratacion\EventoContractual;
use App\Contratacion\NivelEducativo;
use App\Contratacion\TipoContrato;
use App\Contratacion\TipoRiesgo;
use App\Incapacidades\OrigenIncapacidad;
use App\Licencias\TipoDeLicencia;
use App\Models\Eps;
use App\Models\Incapacidad;
use App\Models\Licencia;
use App\Models\PerfilSociodemografico;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Support\Carbon;

/**
 * La ficha del empleado, F1-07: la sección 5.3 del documento 3.
 *
 * Lo que se defiende aquí, en orden de importancia:
 *
 * 1. **Las cuatro pestañas cargan y el saldo se ve arriba**, que es el «hecho
 *    cuando» de la tarea. Desde F2-02 el saldo se ve con su número, calculado
 *    por el motor: la regla 2 prohíbe guardarlo en una columna, así que la ficha
 *    lo pide y lo escribe, y no lo lee de ninguna parte.
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
 * Las tres clases de novedad sobre un mismo empleado, con fechas que se
 * distinguen entre sí para poder leer el orden en que salen (HD-01).
 */
function conLasTresNovedades(Vinculacion $empleado): void
{
    app(ContextoTenant::class)->paraTenant($empleado->tenant, function () use ($empleado) {
        Incapacidad::factory()->de($empleado)->del14al16DeJulio(2026)->create([
            'codigo_diagnostico' => 'J06.9',
            'origen' => OrigenIncapacidad::Comun,
        ]);

        Vacacion::factory()->de($empleado)->del27al31DeJulio(2026)->create();

        Licencia::factory()->de($empleado)->create([
            'tipo' => TipoDeLicencia::Suspension,
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-03',
        ]);
    });
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
     * La regla 2 del proyecto: el saldo se calcula, no se guarda. Lo calcula el
     * motor de F2-02 y la ficha solo lo escribe; que la cuenta esté bien lo
     * comprueban los diez casos obligatorios, no esta prueba.
     */
    it('enseña el saldo de vacaciones calculado, sin leer ninguna columna de saldo', function () {
        Carbon::setTestNow('2026-08-19');

        // Diez meses cumplidos desde el ingreso, a 1,25 días cada uno.
        $props = fichaDe(empleadoDe([], ['fecha_ingreso' => '2025-10-01']));

        $saldo = datoDelResumen($props, 'Saldo de vacaciones');

        expect($saldo['pendiente'])->toBeFalse()
            ->and($saldo['valor'])->toBe('12,50 días');
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

/**
 * Los tres datos de la salida (HD-02): hasta esta tarea, `resumen()` daba
 * siempre los mismos seis campos y la terminación era invisible en la ficha
 * —solo se veía la etiqueta «Inactiva» arriba, sin fecha, sin motivo—.
 *
 * «Cuánto duró la vinculación» no tiene su propia prueba aquí: ya la cubre
 * «cuenta la antigüedad en años y meses...» y «deja de contar la antigüedad
 * de quien ya salió», arriba, porque es el mismo campo del resumen de
 * siempre y no uno nuevo.
 */
describe('los datos de la salida', function () {
    it('no aparecen en el resumen de una persona activa, ni siquiera como un hueco', function () {
        $etiquetas = array_map(fn (array $dato) => $dato['etiqueta'], fichaDe(empleadoDe())['resumen']);

        expect($etiquetas)->not->toContain('Fecha de salida')
            ->and($etiquetas)->not->toContain('Tipo de salida')
            ->and($etiquetas)->not->toContain('Motivo');
    });

    it('enseña la fecha, el tipo y el motivo de quien ya salió', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2024-01-15']);

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $empleado->registrarTerminacion(
            TipoDeSalida::Renuncia,
            'Mejor oferta laboral — salario',
            Carbon::parse('2026-08-31'),
        ));

        $props = fichaDe($empleado);

        expect($props['empleado']['activo'])->toBeFalse()
            ->and(datoDelResumen($props, 'Fecha de salida')['valor'])->toBe('31/08/2026')
            ->and(datoDelResumen($props, 'Tipo de salida')['valor'])->toBe('Renuncia')
            ->and(datoDelResumen($props, 'Motivo')['valor'])->toBe('Mejor oferta laboral — salario');
    });

    /*
     * Los dos tipos que se autocompletan (documento 1, 7.3): el motivo que
     * queda es el que decide `TipoDeSalida::motivoResultante()`, no el que se
     * hubiera mandado.
     */
    it('enseña el motivo autocompletado de la expiración del plazo', function () {
        $empleado = app(ContextoTenant::class)->paraTenant($this->empresa, function () {
            $vinculacion = Vinculacion::factory()
                ->de(Persona::factory()->create())
                ->aTerminoFijo('2025-01-01', '2026-01-01')
                ->create();

            $vinculacion->registrarTerminacion(TipoDeSalida::ExpiracionDelPlazo, null, Carbon::parse('2026-01-01'));

            return $vinculacion;
        });

        expect(datoDelResumen(fichaDe($empleado), 'Motivo')['valor'])
            ->toBe('Expiración del plazo fijo pactado');
    });

    /*
     * La carga histórica que F5-02 todavía no trae puede dejar una vinculación
     * inactiva sin pasar por `registrarTerminacion()` —la fábrica `terminada()`
     * ya hace exactamente eso—, y entonces no hay terminación de dónde sacar
     * el tipo ni el motivo. La fecha de salida cae al respaldo de `fecha_fin`
     * (F2-09), y la ficha no se rompe por la falta de las otras dos.
     */
    it('no se rompe con una persona inactiva sin terminación registrada', function () {
        $empleado = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->de(Persona::factory()->create())
            ->aTerminoFijo('2024-01-01', '2025-01-01')
            ->terminada()
            ->create());

        $props = fichaDe($empleado);

        expect(datoDelResumen($props, 'Fecha de salida')['valor'])->toBe('01/01/2025')
            ->and(datoDelResumen($props, 'Tipo de salida')['valor'])->toBe('—')
            ->and(datoDelResumen($props, 'Motivo')['valor'])->toBe('—');
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

/**
 * La pestaña de novedades (HD-01). Hasta esta tarea `novedades()` era
 * `return []` con un comentario que decía «vacío hasta que existan sus
 * módulos»; los tres existen desde la fase 2.
 */
describe('la pestaña de novedades', function () {
    it('enseña las tres clases juntas, de la más reciente a la más antigua', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2024-01-15']);
        conLasTresNovedades($empleado);

        $novedades = fichaDe($empleado)['novedades'];

        expect($novedades)->toHaveCount(3)
            ->and(array_column($novedades, 'clase'))->toBe([
                'Vacaciones',   // 27 de julio
                'Incapacidad',  // 14 de julio
                'Suspensión',   // 1 de junio
            ]);
    });

    it('trae de cada novedad sus fechas y sus días', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2024-01-15']);
        conLasTresNovedades($empleado);

        $incapacidad = collect(fichaDe($empleado)['novedades'])->firstWhere('clase', 'Incapacidad');

        expect($incapacidad['desde'])->toBe('14/07/2026')
            ->and($incapacidad['hasta'])->toBe('16/07/2026')
            // Días corridos, del motor: es el caso obligatorio de F2-04.
            ->and($incapacidad['dias'])->toBe(3);
    });

    /*
     * Los días de vacaciones son hábiles y los de las otras dos corridos: son
     * tres reglas distintas del motor (regla 3) y la ficha no puede aplicar la
     * que no es.
     */
    it('cuenta los días de vacaciones en hábiles y los de la licencia en corridos', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2024-01-15']);
        conLasTresNovedades($empleado);

        $novedades = collect(fichaDe($empleado)['novedades'])->keyBy('clase');

        // Del lunes 27 al viernes 31 de julio: cinco hábiles.
        expect($novedades['Vacaciones']['dias'])->toBe(5)
            // Del 1 al 3 de junio: tres corridos, con el domingo dentro.
            ->and($novedades['Suspensión']['dias'])->toBe(3);
    });

    it('distingue la incapacidad por su origen y su diagnóstico', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2024-01-15']);
        conLasTresNovedades($empleado);

        $usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados, Modulo::Incapacidades);

        $respuesta = $this->actingAs($usuario)->get('/empleados/'.$empleado->getKey());
        $respuesta->assertOk();

        $novedades = $respuesta->viewData('page')['props']['novedades'];

        expect(collect($novedades)->firstWhere('clase', 'Incapacidad')['detalle'])
            ->toBe('Común · Diagnóstico J06.9');
    });

    /*
     * El diagnóstico es el dato sensible del documento 1, 6.3. Al listado de
     * incapacidades no se llega sin su módulo, pero a la ficha se entra con el
     * de empleados, que es otro permiso: sin el de incapacidades, el código no
     * viaja.
     */
    it('no enseña el diagnóstico a quien no tiene el módulo de incapacidades', function () {
        $empleado = empleadoDe([], ['fecha_ingreso' => '2024-01-15']);
        conLasTresNovedades($empleado);

        $respuesta = $this->actingAs($this->usuario)->get('/empleados/'.$empleado->getKey());
        $respuesta->assertOk();

        $props = $respuesta->viewData('page')['props'];

        expect(collect($props['novedades'])->firstWhere('clase', 'Incapacidad')['detalle'])
            ->toBe('Común')
            ->and($respuesta->getContent())->not->toContain('J06.9');
    });

    it('deja el estado vacío solo para quien de verdad no tiene ninguna', function () {
        $props = fichaDe(empleadoDe());

        expect($props['novedades'])->toBe([]);
    });

    it('carga los movimientos vacíos sin romperse', function () {
        expect(fichaDe(empleadoDe())['movimientos'])->toBe([]);
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
