<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Contratacion\HistorialDeContrato;
use App\Contratacion\ListadoDeContratos;
use App\Contratacion\TipoContrato;
use App\Contratacion\VigenciaDelContrato;
use App\Exports\ContratosExport;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * El listado y el detalle de contratos (documento 3, sección 5.4, y documento
 * 1, sección 5.5).
 *
 * Tres cosas se defienden aquí, que son las que promete la tarea F1-08:
 *
 * 1. **La vista por defecto son los que vencen en 90 días**, con los ya
 *    vencidos dentro, porque son los que más urgen.
 * 2. **La fecha límite de preaviso se ve en pantalla**, no solo llega por
 *    correo. Son treinta días antes del vencimiento y salen de
 *    `config/reglas_laborales.php`, nunca de un número escrito a mano.
 * 3. **Un contrato con tres prórrogas muestra cuatro filas de historial**: el
 *    periodo inicial cuenta.
 *
 * Todo se prueba contra las props que devuelve el controlador, no contra la
 * pantalla: un filtro que solo escondiera filas en el navegador tiene que dejar
 * estas pruebas en rojo.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; Pest carga las funciones de
 * todos los archivos de prueba.
 */
beforeEach(function () {
    // Todo el archivo se sitúa el mismo día. El estado de un contrato depende
    // de cuándo se mire, así que sin fijar el día las pruebas caducarían solas.
    $this->travelTo(Carbon::parse('2026-08-19'));

    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
});

/**
 * Un contrato a término fijo de la empresa de la prueba, con las prórrogas que
 * se le pasen.
 *
 * Cada prórroga mueve también `fecha_fin` de la vinculación, que es lo que hará
 * la acción «Renovar» de F1-09: el historial y lo vigente se mueven a la vez o
 * quedan desalineados.
 *
 * @param  list<array{0: string, 1: string}>  $prorrogas  Pares de fecha de inicio y fin.
 */
function contratoFijo(string $nombre, string $ingreso, string $fin, array $prorrogas = []): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function () use ($nombre, $ingreso, $fin, $prorrogas) {
        $vinculacion = Vinculacion::factory()
            ->de(Persona::factory()->create([
                'primer_nombre' => $nombre,
                'segundo_nombre' => null,
                'primer_apellido' => 'Contratado',
                'segundo_apellido' => null,
            ]))
            ->aTerminoFijo($ingreso, $fin)
            ->create(['cargo' => 'Auxiliar de Bodega']);

        foreach ($prorrogas as [$desde, $hasta]) {
            $vinculacion->registrarPeriodo(
                EventoContractual::ProrrogaPactada,
                TipoContrato::Fijo,
                Carbon::parse($desde),
                Carbon::parse($hasta),
            );

            $vinculacion->forceFill(['fecha_fin' => Carbon::parse($hasta)])->save();
        }

        return $vinculacion->fresh();
    });
}

/**
 * Un contrato sin plazo: indefinido u obra o labor.
 */
function contratoSinPlazo(string $nombre, TipoContrato $tipo): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function () use ($nombre, $tipo) {
        $fabrica = Vinculacion::factory()->de(Persona::factory()->create([
            'primer_nombre' => $nombre,
            'segundo_nombre' => null,
            'primer_apellido' => 'Contratado',
            'segundo_apellido' => null,
        ]));

        if ($tipo === TipoContrato::ObraLabor) {
            $fabrica = $fabrica->deObraOLabor();
        }

        return $fabrica->create(['cargo' => 'Operario', 'fecha_ingreso' => '2026-01-15', 'fecha_fin' => null]);
    });
}

/**
 * Las filas que el servidor manda a la pantalla con los filtros dados.
 *
 * @param  array<string, mixed>  $filtros
 * @return list<array<string, mixed>>
 */
function filasDeContratos(array $filtros = []): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/contratos?'.http_build_query($filtros));

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props']['contratos']['filas'];
}

/**
 * @param  list<array<string, mixed>>  $filas
 * @return list<string>
 */
function empleadosDe(array $filas): array
{
    return array_values(array_map(fn (array $fila) => $fila['empleado'], $filas));
}

/**
 * Las props del detalle de un contrato.
 *
 * @return array<string, mixed>
 */
function detalleDeContrato(Vinculacion $contrato): array
{
    $respuesta = test()->actingAs(test()->usuario)->get('/contratos/'.$contrato->getKey());

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props'];
}

describe('la vista por defecto', function () {
    it('trae los que vencen en los próximos 90 días', function () {
        contratoFijo('Cercano', '2026-01-01', '2026-09-30');
        contratoFijo('Lejano', '2026-01-01', '2027-06-30');

        expect(empleadosDe(filasDeContratos()))->toBe(['Cercano Contratado']);
    });

    it('incluye los que ya vencieron, que son los que más urgen', function () {
        contratoFijo('Vencido', '2025-06-01', '2026-07-31');

        expect(empleadosDe(filasDeContratos()))->toBe(['Vencido Contratado']);
    });

    it('deja fuera los contratos sin plazo', function () {
        contratoSinPlazo('Indefinido', TipoContrato::Indefinido);
        contratoSinPlazo('DeObra', TipoContrato::ObraLabor);

        expect(filasDeContratos())->toBe([]);
    });

    it('ordena por fecha de vencimiento, del más próximo al más lejano', function () {
        contratoFijo('Tercero', '2026-01-01', '2026-10-30');
        contratoFijo('Primero', '2026-01-01', '2026-08-25');
        contratoFijo('Segundo', '2026-01-01', '2026-09-15');

        expect(empleadosDe(filasDeContratos()))
            ->toBe(['Primero Contratado', 'Segundo Contratado', 'Tercero Contratado']);
    });

    it('deja al final los que no vencen cuando se piden todos', function () {
        contratoSinPlazo('Indefinido', TipoContrato::Indefinido);
        contratoFijo('Fijo', '2026-01-01', '2026-09-30');

        expect(empleadosDe(filasDeContratos(['vence' => 'todos'])))
            ->toBe(['Fijo Contratado', 'Indefinido Contratado']);
    });

    it('no lista el contrato de un empleado inactivo', function () {
        $contrato = contratoFijo('Retirado', '2026-01-01', '2026-09-30');

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($contrato) {
            $contrato->desactivar();
            $contrato->save();
        });

        expect(filasDeContratos())->toBe([]);
    });
});

describe('los filtros', function () {
    it('acota el rango de vencimiento a 30 días', function () {
        contratoFijo('Dentro', '2026-01-01', '2026-09-10');
        contratoFijo('Fuera', '2026-01-01', '2026-10-30');

        expect(empleadosDe(filasDeContratos(['vence' => '30'])))->toBe(['Dentro Contratado']);
    });

    it('trae solo los ya vencidos cuando se piden', function () {
        contratoFijo('Vencido', '2025-06-01', '2026-07-31');
        contratoFijo('Vigente', '2026-01-01', '2026-09-30');

        expect(empleadosDe(filasDeContratos(['vence' => 'vencidos'])))->toBe(['Vencido Contratado']);
    });

    it('filtra por tipo de contrato', function () {
        contratoSinPlazo('DeObra', TipoContrato::ObraLabor);
        contratoFijo('Fijo', '2026-01-01', '2026-09-30');

        expect(empleadosDe(filasDeContratos(['vence' => 'todos', 'contrato' => 'obra_labor'])))
            ->toBe(['DeObra Contratado']);
    });

    it('filtra los que acumulan tres prórrogas o más', function () {
        contratoFijo('ConTres', '2025-09-01', '2026-02-28', [
            ['2026-03-01', '2026-05-31'],
            ['2026-06-01', '2026-08-31'],
            ['2026-09-01', '2026-09-30'],
        ]);
        contratoFijo('ConUna', '2026-01-01', '2026-06-30', [['2026-07-01', '2026-09-30']]);

        expect(empleadosDe(filasDeContratos(['prorrogas' => '3'])))->toBe(['ConTres Contratado']);
    });

    it('filtra los que no tienen ninguna prórroga', function () {
        contratoFijo('SinNinguna', '2026-01-01', '2026-09-30');
        contratoFijo('ConUna', '2026-01-01', '2026-06-30', [['2026-07-01', '2026-09-30']]);

        expect(empleadosDe(filasDeContratos(['prorrogas' => '0'])))->toBe(['SinNinguna Contratado']);
    });

    it('enseña el rango por defecto como etiqueta removible', function () {
        $respuesta = $this->actingAs($this->usuario)->get('/contratos');

        expect($respuesta->viewData('page')['props']['aplicados'])
            ->toBe([['clave' => 'vence', 'texto' => 'Vencimiento: Vencen en 90 días']]);
    });

    it('ignora un filtro inventado en la dirección y usa el valor por defecto', function () {
        contratoFijo('Lejano', '2026-01-01', '2027-06-30');

        // `vence=todo-lo-que-hay` no es una opción: se vuelve a los 90 días, y
        // el contrato lejano sigue sin aparecer.
        expect(filasDeContratos(['vence' => 'todo-lo-que-hay']))->toBe([]);
    });
});

describe('las columnas de la fila', function () {
    it('trae empleado, tipo, inicio, vencimiento, prórrogas, preaviso y estado', function () {
        contratoFijo('Juan', '2026-01-01', '2026-06-30', [['2026-07-01', '2026-09-12']]);

        expect(filasDeContratos()[0])->toMatchArray([
            'empleado' => 'Juan Contratado',
            'contrato' => 'Término fijo',
            // El inicio es el del periodo vigente, no la fecha de ingreso: es
            // el tramo que está corriendo el que se contrasta con el
            // vencimiento de la columna de al lado.
            'inicio' => '01/07/2026',
            'vence' => '12/09/2026',
            'prorrogas' => 1,
            // Treinta días antes del vencimiento (artículo 46 del CST).
            'preaviso' => '13/08/2026',
            'estado' => 'Vence en 24 días',
        ]);
    });

    it('dice «Vigente» y no inventa fechas en un contrato sin plazo', function () {
        contratoSinPlazo('Indefinido', TipoContrato::Indefinido);

        expect(filasDeContratos(['vence' => 'todos'])[0])->toMatchArray([
            'contrato' => 'Término indefinido',
            'vence' => '—',
            'preaviso' => '—',
            'estado' => 'Vigente',
            'tono' => 'exito',
        ]);
    });

    it('marca en rojo el que venció sin gestionar', function () {
        contratoFijo('Vencido', '2025-06-01', '2026-08-12');

        expect(filasDeContratos()[0])->toMatchArray([
            'estado' => 'Vencido hace 7 días',
            'tono' => 'error',
        ]);
    });

    it('marca en ámbar el que ya pasó su fecha límite de preaviso', function () {
        // Vence el 12/09; el preaviso venció el 13/08, hace seis días.
        contratoFijo('Juan', '2026-01-01', '2026-09-12');

        expect(filasDeContratos()[0]['tono'])->toBe('advertencia');
    });
});

describe('el aviso de contratos vencidos', function () {
    it('cuenta los vencidos sin gestionar, con filtros o sin ellos', function () {
        contratoFijo('Vencido', '2025-06-01', '2026-07-31');
        contratoFijo('Vigente', '2026-01-01', '2026-09-30');

        $respuesta = $this->actingAs($this->usuario)->get('/contratos?vence=30');

        expect($respuesta->viewData('page')['props']['conteos'])
            ->toMatchArray(['vencidos' => 1, 'dias' => 90]);
    });

    it('no cuenta como vencido el contrato que vence hoy', function () {
        contratoFijo('Hoy', '2026-01-01', '2026-08-19');

        $respuesta = $this->actingAs($this->usuario)->get('/contratos');

        expect($respuesta->viewData('page')['props']['conteos']['vencidos'])->toBe(0)
            ->and(filasDeContratos()[0]['estado'])->toBe('Vence hoy');
    });
});

describe('la exportación', function () {
    it('trae exactamente lo filtrado', function () {
        Excel::fake();

        contratoFijo('Cercano', '2026-01-01', '2026-09-30');
        contratoFijo('Lejano', '2026-01-01', '2027-06-30');

        $this->actingAs($this->usuario)->get('/contratos/exportar')->assertOk();

        Excel::assertDownloaded(
            'contratos-'.now()->format('Y-m-d').'.xlsx',
            fn (ContratosExport $exportacion) => $exportacion->collection()->count() === 1
        );
    });

    it('lleva documento, nombre y cargo, que son obligatorias en toda exportación', function () {
        expect((new ContratosExport(ListadoDeContratos::desde(request())))->headings())
            ->toContain('Documento', 'Nombre completo', 'Cargo');
    });
});

describe('el detalle del contrato', function () {
    it('muestra cuatro filas de historial en un contrato con tres prórrogas', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2025-10-14', [
            ['2025-10-15', '2026-01-14'],
            ['2026-01-15', '2026-04-14'],
            ['2026-04-15', '2026-09-12'],
        ]);

        $historial = detalleDeContrato($contrato)['historial'];

        expect($historial)->toHaveCount(4)
            ->and($historial[0])->toMatchArray([
                'orden' => 1,
                'evento' => 'Periodo inicial',
                'desde' => '15/07/2025',
                'hasta' => '14/10/2025',
            ])
            ->and($historial[3])->toMatchArray([
                'orden' => 4,
                'evento' => 'Prórroga pactada',
                'hasta' => '12/09/2026',
            ]);
    });

    it('destaca la fecha límite de preaviso con los días que quedan', function () {
        // Vence el 30/09: el preaviso se cumple el 31/08, dentro de doce días.
        $contrato = contratoFijo('Juan', '2026-01-01', '2026-09-30');

        $preaviso = collect(detalleDeContrato($contrato)['indicadores'])
            ->firstWhere('etiqueta', 'Límite de preaviso');

        expect($preaviso)->toMatchArray([
            'valor' => '31/08/2026',
            'nota' => 'Quedan 12 días',
        ]);
    });

    it('enseña las prórrogas acumuladas y el tope legal de cuatro años', function () {
        $contrato = contratoFijo('Juan', '2026-01-01', '2026-06-30', [['2026-07-01', '2026-09-30']]);

        $indicadores = collect(detalleDeContrato($contrato)['indicadores']);

        expect($indicadores->firstWhere('etiqueta', 'Prórrogas acumuladas')['valor'])->toBe('1')
            ->and($indicadores->firstWhere('etiqueta', 'Duración total del vínculo')['nota'])
            ->toBe('Tope legal de cuatro años: 01/01/2030');
    });

    it('ofrece renovar solo en el término fijo', function () {
        $fijo = contratoFijo('Juan', '2026-01-01', '2026-09-30');
        $indefinido = contratoSinPlazo('Ana', TipoContrato::Indefinido);

        expect(detalleDeContrato($fijo)['contrato']['admiteRenovacion'])->toBeTrue()
            ->and(detalleDeContrato($indefinido)['contrato']['admiteRenovacion'])->toBeFalse();
    });

    it('no enseña vencimiento ni preaviso donde no hay plazo', function () {
        $contrato = contratoSinPlazo('Ana', TipoContrato::Indefinido);

        $props = detalleDeContrato($contrato);
        $etiquetas = array_column($props['indicadores'], 'etiqueta');
        $campos = array_column($props['campos'], 'etiqueta');

        expect($etiquetas)->not->toContain('Límite de preaviso')
            ->and($campos)->not->toContain('Fecha límite de preaviso')
            ->and($campos)->not->toContain('Fecha de vencimiento');
    });

    it('enseña la fecha límite de preaviso también entre los datos del contrato', function () {
        $contrato = contratoFijo('Juan', '2026-01-01', '2026-09-30');

        expect(detalleDeContrato($contrato)['campos'])
            ->toContain(['etiqueta' => 'Fecha límite de preaviso', 'valor' => '31/08/2026']);
    });
});

describe('el permiso del módulo', function () {
    it('responde 403 a quien no tiene el módulo de contratos', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)->get('/contratos')->assertForbidden();
    });

    it('responde 403 en el detalle a quien no tiene el módulo', function () {
        $contrato = contratoFijo('Juan', '2026-01-01', '2026-09-30');
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)
            ->get('/contratos/'.$contrato->getKey())
            ->assertForbidden();
    });
});

/**
 * El historial de un contrato, leído dentro del contexto de su empresa.
 *
 * Fuera de él no devuelve nada, y eso es exactamente lo que tiene que pasar: el
 * global scope del trait no distingue entre una prueba y una fuga.
 */
function historialDe(Vinculacion $contrato): HistorialDeContrato
{
    return app(ContextoTenant::class)->paraTenant(
        test()->empresa,
        fn () => HistorialDeContrato::de($contrato)
    );
}

describe('la vigencia, sin pasar por la pantalla', function () {
    it('cuenta el preaviso con la constante legal y no con un número escrito a mano', function () {
        config()->set('reglas_laborales.preaviso_dias', 45);

        $vigencia = VigenciaDelContrato::de(historialDe(contratoFijo('Juan', '2026-01-01', '2026-09-30')));

        expect($vigencia->fechaLimiteDePreaviso()?->format('d/m/Y'))->toBe('16/08/2026');
    });

    it('no tiene plazo ni preaviso en una obra o labor', function () {
        $vigencia = VigenciaDelContrato::de(historialDe(contratoSinPlazo('Ana', TipoContrato::ObraLabor)));

        expect($vigencia->tienePlazo())->toBeFalse()
            ->and($vigencia->fechaLimiteDePreaviso())->toBeNull()
            ->and($vigencia->diasParaVencer())->toBeNull()
            ->and($vigencia->estado())->toBe('Vigente');
    });

    it('se puede situar en otro día sin tocar el historial', function () {
        $historial = historialDe(contratoFijo('Juan', '2026-01-01', '2026-09-30'));

        expect(VigenciaDelContrato::de($historial, Carbon::parse('2026-09-29'))->estado())
            ->toBe('Vence en 1 día')
            ->and(VigenciaDelContrato::de($historial, Carbon::parse('2026-10-01'))->estado())
            ->toBe('Vencido hace 1 día');
    });
});
