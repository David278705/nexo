<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Contratacion\TipoContrato;
use App\Models\PeriodoContractual;
use App\Models\Tenant;
use App\Models\Vinculacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * La acción «Renovar contrato» (documento 1, 5.5, y documento 3, sección 5.4).
 *
 * Lo que aquí se defiende es la promesa de la tarea F1-09:
 *
 * 1. **Renovar crea una fila nueva en el historial y editar la fecha no.** Son
 *    dos hechos distintos: uno es un acuerdo jurídico y el otro es corregir una
 *    digitación.
 * 2. **El historial y lo vigente se mueven a la vez.** Una prórroga que agregara
 *    la fila sin mover `fecha_fin` dejaría el listado avisando de un vencimiento
 *    ya renovado.
 * 3. **Se registra el tipo de prórroga** —pactada por escrito o automática por
 *    silencio— y de él depende cuántas prórrogas cortas quedan, que son cuatro
 *    en un caso y tres en el otro.
 * 4. **El modal calcula en el servidor**: duración de la prórroga, total
 *    acumulado y prórrogas restantes llegan escritas en las props.
 *
 * Lo que **no** se prueba aquí es el bloqueo del tope de cuatro años ni el de la
 * prórroga mínima: en esta tarea se advierten y sí se permite guardar. Volverlos
 * bloqueo es F1-10, y entonces estas pruebas cambian de expectativa a propósito.
 *
 * `contratoFijo()`, `contratoSinPlazo()` y `detalleDeContrato()` viven en
 * `ContratosTest.php`, y `usuarioDe()` en `RolesYPermisosTest.php`: Pest carga
 * las funciones de todos los archivos de prueba.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-08-19'));

    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos);
});

/**
 * Los tramos del historial, del inicial al vigente.
 *
 * Se lee con la empresa en contexto porque el global scope falla cerrado: una
 * consulta hecha desde la prueba, fuera de una petición HTTP, no ve nada si no
 * fija el contexto igual que lo hace el middleware. `enLaEmpresa()` vive en
 * `PersonasYVinculacionesTest.php`.
 *
 * @return Collection<int, PeriodoContractual>
 */
function tramosDe(Vinculacion $contrato): Collection
{
    return enLaEmpresa(fn () => $contrato->periodos()->enOrden()->get());
}

/**
 * Lo que el servidor manda al modal de renovación, con lo digitado que se le
 * pase.
 *
 * @param  array<string, string>  $propuesta
 * @return array<string, mixed>
 */
function renovacionDe(Vinculacion $contrato, array $propuesta = []): array
{
    $respuesta = test()->actingAs(test()->usuario)
        ->get('/contratos/'.$contrato->getKey().'?'.http_build_query($propuesta));

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props']['renovacion'];
}

/**
 * Envía el formulario de renovación.
 *
 * @param  array<string, string>  $datos
 */
function renovar(Vinculacion $contrato, array $datos): TestResponse
{
    return test()->actingAs(test()->usuario)
        ->post('/contratos/'.$contrato->getKey().'/renovar', $datos);
}

/**
 * Todas las líneas de la proyección juntas, que es como se leen en pantalla.
 *
 * @param  array<string, string>  $propuesta
 */
function proyeccionDe(Vinculacion $contrato, array $propuesta): string
{
    $proyeccion = renovacionDe($contrato, $propuesta)['proyeccion'];

    return $proyeccion === null ? '' : implode(' ', $proyeccion['lineas']);
}

describe('registrar la prórroga', function () {
    it('crea una fila nueva en el historial', function () {
        $contrato = contratoFijo('Renovado', '2026-01-01', '2026-03-31');

        expect(tramosDe($contrato))->toHaveCount(1);

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaPactada->value,
            'fecha_fin' => '2026-06-30',
        ])->assertRedirect('/contratos/'.$contrato->getKey());

        expect(tramosDe($contrato))->toHaveCount(2);
    });

    it('editar la fecha de finalización no crea ninguna fila', function () {
        $contrato = contratoFijo('Corregido', '2026-01-01', '2026-03-31');

        // Es lo que hace corregir una digitación: mover lo vigente sin registrar
        // un hecho nuevo. El historial no se toca.
        enLaEmpresa(fn () => $contrato->forceFill(['fecha_fin' => Carbon::parse('2026-04-30')])->save());

        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('el tramo nuevo empieza el día siguiente al vencimiento vigente', function () {
        $contrato = contratoFijo('Continuo', '2026-01-01', '2026-03-31');

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaPactada->value,
            'fecha_fin' => '2026-06-30',
        ]);

        $tramo = tramosDe($contrato)->last();

        expect($tramo->fecha_inicio->toDateString())->toBe('2026-04-01')
            ->and($tramo->fecha_fin->toDateString())->toBe('2026-06-30')
            ->and($tramo->orden)->toBe(2);
    });

    it('mueve también la fecha de finalización de la vinculación', function () {
        $contrato = contratoFijo('Alineado', '2026-01-01', '2026-03-31');

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaPactada->value,
            'fecha_fin' => '2026-06-30',
        ]);

        expect(enLaEmpresa(fn () => $contrato->fresh())->fecha_fin->toDateString())
            ->toBe('2026-06-30');
    });

    it('guarda el tipo de prórroga que se eligió', function (string $tipo) {
        $contrato = contratoFijo('Tipada', '2026-01-01', '2026-03-31');

        renovar($contrato, ['tipo' => $tipo, 'fecha_fin' => '2026-06-30']);

        expect(tramosDe($contrato)->last()->evento->value)->toBe($tipo);
    })->with([
        EventoContractual::ProrrogaPactada->value,
        EventoContractual::ProrrogaAutomatica->value,
    ]);

    it('deja constancia de la prórroga registrada en el mensaje', function () {
        $contrato = contratoFijo('Avisada', '2026-01-01', '2026-03-31');

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaAutomatica->value,
            'fecha_fin' => '2026-06-30',
        ])->assertSessionHas('estado', fn (string $mensaje) => str_contains($mensaje, 'prórroga automática')
            && str_contains($mensaje, '30/06/2026'));
    });

    it('la prórroga queda en la auditoría sin que nadie la escriba', function () {
        $contrato = contratoFijo('Auditada', '2026-01-01', '2026-03-31');

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaPactada->value,
            'fecha_fin' => '2026-06-30',
        ]);

        $tramo = tramosDe($contrato)->last();

        $this->assertDatabaseHas('auditoria', [
            'modelo' => PeriodoContractual::class,
            'registro_id' => (string) $tramo->getKey(),
            'accion' => 'creado',
        ]);
    });
});

describe('lo que no se puede renovar', function () {
    it('exige el tipo de prórroga y la fecha', function () {
        $contrato = contratoFijo('Incompleta', '2026-01-01', '2026-03-31');

        renovar($contrato, [])->assertSessionHasErrors(['tipo', 'fecha_fin']);

        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('no acepta como tipo un evento que no es prórroga', function () {
        $contrato = contratoFijo('Inventada', '2026-01-01', '2026-03-31');

        renovar($contrato, [
            'tipo' => EventoContractual::Inicial->value,
            'fecha_fin' => '2026-06-30',
        ])->assertSessionHasErrors('tipo');
    });

    it('rechaza una fecha que no es posterior al vencimiento vigente', function (string $fecha) {
        $contrato = contratoFijo('Retrocedida', '2026-01-01', '2026-03-31');

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaPactada->value,
            'fecha_fin' => $fecha,
        ])->assertSessionHasErrors('fecha_fin');

        expect(tramosDe($contrato))->toHaveCount(1);
    })->with(['2026-03-31', '2026-02-01']);

    it('responde 403 en un contrato que no es a término fijo', function (TipoContrato $tipo) {
        $contrato = contratoSinPlazo('SinPlazo', $tipo);

        renovar($contrato, [
            'tipo' => EventoContractual::ProrrogaPactada->value,
            'fecha_fin' => '2027-06-30',
        ])->assertForbidden();
    })->with([TipoContrato::Indefinido, TipoContrato::ObraLabor]);

    it('responde 403 a quien no tiene el módulo de contratos', function () {
        $contrato = contratoFijo('Ajena', '2026-01-01', '2026-03-31');
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)
            ->post('/contratos/'.$contrato->getKey().'/renovar', [
                'tipo' => EventoContractual::ProrrogaPactada->value,
                'fecha_fin' => '2026-06-30',
            ])
            ->assertForbidden();
    });

    it('no ofrece la acción en un contrato sin plazo', function () {
        expect(renovacionDe(contratoSinPlazo('Indefinida', TipoContrato::Indefinido))['disponible'])
            ->toBeFalse();
    });

    it('sí la ofrece en un término fijo, con el inicio del tramo nuevo', function () {
        $renovacion = renovacionDe(contratoFijo('Fija', '2026-01-01', '2026-03-31'));

        expect($renovacion['disponible'])->toBeTrue()
            ->and($renovacion['inicio'])->toBe('01/04/2026')
            ->and($renovacion['minimo'])->toBe('2026-04-01');
    });
});

describe('lo que el modal enseña antes de tocar nada', function () {
    it('resume el periodo vigente, las prórrogas y la duración', function () {
        $contrato = contratoFijo('Resumida', '2026-01-01', '2026-03-31', [
            ['2026-04-01', '2026-06-30'],
        ]);

        expect(renovacionDe($contrato)['resumen'])->toBe([
            ['etiqueta' => 'Periodo vigente', 'valor' => '01/04/2026 — 30/06/2026'],
            ['etiqueta' => 'Prórrogas acumuladas', 'valor' => '1'],
            ['etiqueta' => 'Duración total del vínculo', 'valor' => '181 días'],
        ]);
    });

    it('ofrece las dos prórrogas y solo esas dos', function () {
        expect(renovacionDe(contratoFijo('Opciones', '2026-01-01', '2026-03-31'))['tipos'])
            ->toBe([
                ['valor' => 'prorroga_pactada', 'texto' => 'Pactada por escrito'],
                ['valor' => 'prorroga_automatica', 'texto' => 'Automática por silencio'],
            ]);
    });

    it('no proyecta nada mientras falte la fecha o el tipo', function () {
        $contrato = contratoFijo('Vacía', '2026-01-01', '2026-03-31');

        expect(renovacionDe($contrato)['proyeccion'])->toBeNull()
            ->and(renovacionDe($contrato, ['fecha_fin' => '2026-06-30'])['proyeccion'])->toBeNull()
            ->and(renovacionDe($contrato, ['tipo' => 'prorroga_pactada'])['proyeccion'])->toBeNull();
    });
});

describe('las tres cuentas de la proyección', function () {
    it('dice cuánto dura la prórroga, con sus dos fechas', function () {
        $texto = proyeccionDe(contratoFijo('Duración', '2026-01-01', '2026-03-31'), [
            'tipo' => 'prorroga_pactada',
            'fecha_fin' => '2026-06-30',
        ]);

        expect($texto)->toContain('Prórroga de 91 días, del 01/04/2026 al 30/06/2026.');
    });

    it('dice en cuánto quedaría el vínculo acumulado y cuándo se cumple el tope', function () {
        $texto = proyeccionDe(contratoFijo('Acumulado', '2026-01-01', '2026-03-31'), [
            'tipo' => 'prorroga_pactada',
            'fecha_fin' => '2026-06-30',
        ]);

        expect($texto)->toContain('El vínculo quedaría en 181 días')
            ->and($texto)->toContain('se cumplen el 01/01/2030');
    });

    it('dice qué número de prórroga sería', function () {
        $contrato = contratoFijo('Numerada', '2026-01-01', '2026-03-31', [
            ['2026-04-01', '2026-06-30'],
        ]);

        expect(proyeccionDe($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-09-30']))
            ->toContain('Sería la 2ª prórroga del contrato, prórroga pactada.');
    });

    it('cuenta cuatro cupos cortos en la pactada y tres en la automática', function () {
        $contrato = contratoFijo('Cupos', '2026-01-01', '2026-03-31');

        expect(proyeccionDe($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30']))
            ->toContain('quedarían 3 prórrogas cortas')
            ->and(proyeccionDe($contrato, ['tipo' => 'prorroga_automatica', 'fecha_fin' => '2026-06-30']))
            ->toContain('quedarían 2 prórrogas cortas');
    });

    it('los cupos salen de la configuración y no de un número escrito a mano', function () {
        config(['reglas_laborales.prorrogas_cortas_pactadas' => 6]);

        $contrato = contratoFijo('Configurada', '2026-01-01', '2026-03-31');

        expect(proyeccionDe($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30']))
            ->toContain('quedarían 5 prórrogas cortas');
    });

    it('avisa en ámbar cuando la prórroga gasta el último cupo corto', function () {
        $contrato = contratoFijo('Última', '2026-01-01', '2026-03-31', [
            ['2026-04-01', '2026-06-30'],
            ['2026-07-01', '2026-09-30'],
            ['2026-10-01', '2026-12-31'],
        ]);

        $renovacion = renovacionDe($contrato, [
            'tipo' => 'prorroga_pactada',
            'fecha_fin' => '2027-03-31',
        ]);

        expect($renovacion['proyeccion']['tono'])->toBe('advertencia')
            ->and(implode(' ', $renovacion['proyeccion']['lineas']))
            ->toContain('la siguiente renovación de este contrato deberá ser de mínimo un año');
    });

    it('una prórroga de un año o más no gasta cupo corto', function () {
        $contrato = contratoFijo('Larga', '2026-01-01', '2026-03-31');

        $texto = proyeccionDe($contrato, [
            'tipo' => 'prorroga_pactada',
            'fecha_fin' => '2027-03-31',
        ]);

        expect($texto)->not->toContain('prórroga inferior a un año')
            ->and($texto)->not->toContain('cupo');
    });

    it('no habla de cupos cuando el contrato inicial es de un año o más', function () {
        $contrato = contratoFijo('Anual', '2026-01-01', '2026-12-31');

        expect(proyeccionDe($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2027-03-31']))
            ->not->toContain('cortas');
    });

    it('advierte del tope de cuatro años sin impedir el guardado', function () {
        $contrato = contratoFijo('Topada', '2026-01-01', '2026-03-31');

        $renovacion = renovacionDe($contrato, [
            'tipo' => 'prorroga_pactada',
            'fecha_fin' => '2030-06-30',
        ]);

        expect($renovacion['proyeccion']['tono'])->toBe('advertencia')
            ->and(implode(' ', $renovacion['proyeccion']['lineas']))
            ->toContain('superaría el tope legal');

        // El bloqueo es F1-10. Hoy se advierte y se permite, que es lo que dice
        // la tarea.
        renovar($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2030-06-30'])
            ->assertSessionHasNoErrors();
    });

    it('devuelve lo digitado para que el modal no se vacíe al recalcular', function () {
        $contrato = contratoFijo('Persistente', '2026-01-01', '2026-03-31');

        expect(renovacionDe($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30'])['propuesta'])
            ->toBe(['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30']);
    });
});

describe('el historial después de renovar', function () {
    it('suma una fila y deja la anterior intacta', function () {
        $contrato = contratoFijo('Historiada', '2026-01-01', '2026-03-31');

        renovar($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30']);

        $historial = detalleDeContrato($contrato)['historial'];

        expect($historial)->toHaveCount(2)
            ->and($historial[0]['evento'])->toBe('Periodo inicial')
            ->and($historial[0]['hasta'])->toBe('31/03/2026')
            ->and($historial[1]['evento'])->toBe('Prórroga pactada')
            ->and($historial[1]['desde'])->toBe('01/04/2026');
    });

    it('mueve el vencimiento que ve el listado', function () {
        $contrato = contratoFijo('Vencimiento', '2026-01-01', '2026-03-31');

        renovar($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-09-30']);

        $campos = collect(detalleDeContrato($contrato)['campos'])
            ->firstWhere('etiqueta', 'Fecha de vencimiento');

        expect($campos['valor'])->toBe('30/09/2026');
    });
});
