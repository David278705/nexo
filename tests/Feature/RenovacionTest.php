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
 * Y desde F1-10, las dos validaciones de 5.5: **el tope de cuatro años no se
 * puede guardar** y la regla de prórroga mínima advierte con fuerza, cada una con
 * el estado que dice `config/validaciones.php`. Las dos se prueban por el estado
 * configurado y por el contrario, porque cambiar esa palabra es lo que va a pasar
 * cuando el asesor laboral confirme la regla (documento 6, B.1 y B.3).
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

/**
 * Las cuatro prórrogas cortas pactadas que agotan el límite del artículo 46.
 *
 * @return list<array{0: string, 1: string}>
 */
function cuatroCortas(): array
{
    return [
        ['2026-04-01', '2026-06-30'],
        ['2026-07-01', '2026-09-30'],
        ['2026-10-01', '2026-12-31'],
        ['2027-01-01', '2027-03-31'],
    ];
}

/**
 * La que ya no cabe: la quinta corta de un contrato que agotó sus cuatro.
 *
 * @return array<string, string>
 */
function laQuintaCorta(): array
{
    return ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2027-06-30'];
}

/**
 * Un historial de prórrogas automáticas, que tienen un cupo corto menos que las
 * pactadas. `contratoFijo()` solo sabe crear pactadas.
 *
 * @param  list<array{0: string, 1: string}>  $tramos
 */
function conProrrogasAutomaticas(Vinculacion $contrato, array $tramos): Vinculacion
{
    return enLaEmpresa(function () use ($contrato, $tramos): Vinculacion {
        foreach ($tramos as [$desde, $hasta]) {
            $contrato->registrarPeriodo(
                EventoContractual::ProrrogaAutomatica,
                TipoContrato::Fijo,
                Carbon::parse($desde),
                Carbon::parse($hasta),
            );
        }

        return $contrato->fresh();
    });
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

    it('devuelve lo digitado para que el modal no se vacíe al recalcular', function () {
        $contrato = contratoFijo('Persistente', '2026-01-01', '2026-03-31');

        expect(renovacionDe($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30'])['propuesta'])
            ->toBe(['tipo' => 'prorroga_pactada', 'fecha_fin' => '2026-06-30']);
    });
});

describe('el tope de cuatro años', function () {
    // Con un contrato que arranca el 01/01/2026 el tope se cumple el 01/01/2030:
    // una prórroga hasta junio de 2030 lo pasa, y una hasta diciembre de 2029 no.
    $pasadoElTope = ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2030-06-30'];

    it('no deja registrar la prórroga que lo supera', function () use ($pasadoElTope) {
        $contrato = contratoFijo('Topada', '2026-01-01', '2026-03-31');

        renovar($contrato, $pasadoElTope)->assertSessionHasErrors('fecha_fin');

        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('el error dice por qué y ofrece la salida correcta', function () use ($pasadoElTope) {
        $contrato = contratoFijo('Explicada', '2026-01-01', '2026-03-31');

        renovar($contrato, $pasadoElTope)->assertSessionHasErrors([
            'fecha_fin' => 'No se puede registrar esta prórroga: con esta fecha el vínculo a '
                .'término fijo superaría el tope legal de cuatro años, y al superarlo el contrato '
                .'se convierte en indefinido por ley. Usa la acción «Cambiar a término '
                .'indefinido», que es la salida correcta y deja el contrato al día.',
        ]);
    });

    it('el modal lo enseña en rojo y con el botón bloqueado', function () use ($pasadoElTope) {
        $contrato = contratoFijo('Roja', '2026-01-01', '2026-03-31');

        $proyeccion = renovacionDe($contrato, $pasadoElTope)['proyeccion'];

        expect($proyeccion['tono'])->toBe('error')
            ->and($proyeccion['bloqueado'])->toBeTrue()
            ->and(implode(' ', $proyeccion['lineas']))
            ->toContain('No se puede registrar esta prórroga')
            ->and(implode(' ', $proyeccion['lineas']))
            ->toContain('Cambiar a término indefinido');
    });

    it('la fecha que llega justo al tope sí se registra', function () {
        $contrato = contratoFijo('Justa', '2026-01-01', '2026-03-31');

        renovar($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2030-01-01'])
            ->assertSessionHasNoErrors();

        expect(tramosDe($contrato))->toHaveCount(2);
    });

    it('se puede volver advertencia por configuración, sin tocar código', function () use ($pasadoElTope) {
        // Es la decisión B.3 del documento 6: mientras no esté cerrada, el estado
        // de la validación es una palabra de `config/validaciones.php`.
        config(['validaciones.renovacion_tope_termino_fijo' => 'advierte']);

        $contrato = contratoFijo('Advertida', '2026-01-01', '2026-03-31');
        $proyeccion = renovacionDe($contrato, $pasadoElTope)['proyeccion'];

        expect($proyeccion['tono'])->toBe('advertencia')
            ->and($proyeccion['bloqueado'])->toBeFalse()
            ->and(implode(' ', $proyeccion['lineas']))
            ->not->toContain('No se puede registrar');

        renovar($contrato, $pasadoElTope)->assertSessionHasNoErrors();
    });

    it('bloquea si la configuración dice cualquier otra cosa', function () use ($pasadoElTope) {
        // Ante una configuración equivocada, no registrar algo que puede ser
        // ilegal es la opción segura.
        config(['validaciones.renovacion_tope_termino_fijo' => 'lo que sea']);

        renovar(contratoFijo('Rara', '2026-01-01', '2026-03-31'), $pasadoElTope)
            ->assertSessionHasErrors('fecha_fin');
    });
});

describe('la regla de prórroga mínima', function () {
    it('advierte con fuerza pero permite registrar la prórroga corta de más', function () {
        $contrato = contratoFijo('Agotada', '2026-01-01', '2026-03-31', cuatroCortas());

        $renovacion = renovacionDe($contrato, laQuintaCorta());
        $texto = implode(' ', $renovacion['proyeccion']['lineas']);

        expect($renovacion['proyeccion']['tono'])->toBe('advertencia')
            ->and($renovacion['proyeccion']['bloqueado'])->toBeFalse()
            ->and($texto)->toContain('el contrato no puede prorrogarse por menos de un año')
            ->and($texto)->toContain('Si ya está firmada puedes registrarla');

        // Es la decisión B.1: aquí sí puede haber un contrato ya firmado que la
        // empresa tenga que registrar, y bloquearlo la dejaría sin dónde hacerlo.
        renovar($contrato, laQuintaCorta())->assertSessionHasNoErrors();

        expect(tramosDe($contrato))->toHaveCount(6);
    });

    it('se vuelve bloqueo por configuración cuando el asesor lo confirme', function () {
        config(['validaciones.renovacion_prorroga_minima' => 'bloquea']);

        $contrato = contratoFijo('Bloqueada', '2026-01-01', '2026-03-31', cuatroCortas());

        $proyeccion = renovacionDe($contrato, laQuintaCorta())['proyeccion'];

        expect($proyeccion['tono'])->toBe('error')
            ->and($proyeccion['bloqueado'])->toBeTrue();

        renovar($contrato, laQuintaCorta())->assertSessionHasErrors([
            'fecha_fin' => 'No se puede registrar esta prórroga: es inferior a un año y el '
                .'contrato ya agotó las que la ley admite. A partir de esta renovación el '
                .'contrato no puede prorrogarse por menos de un año.',
        ]);

        expect(tramosDe($contrato))->toHaveCount(5);
    });

    it('a la automática se le agota un cupo antes que a la pactada', function () {
        config(['validaciones.renovacion_prorroga_minima' => 'bloquea']);

        $contrato = conProrrogasAutomaticas(
            contratoFijo('Silenciosa', '2026-01-01', '2026-03-31'),
            [['2026-04-01', '2026-06-30'], ['2026-07-01', '2026-09-30'], ['2026-10-01', '2026-12-31']],
        );

        // Tres automáticas y la cuarta ya no cabe corta; con cuatro pactadas
        // encima todavía cabría una quinta.
        renovar($contrato, ['tipo' => 'prorroga_automatica', 'fecha_fin' => '2027-03-31'])
            ->assertSessionHasErrors('fecha_fin');
    });

    it('la prórroga de un año o más se registra aunque los cupos estén agotados', function () {
        config(['validaciones.renovacion_prorroga_minima' => 'bloquea']);

        $contrato = contratoFijo('Anualizada', '2026-01-01', '2026-03-31', cuatroCortas());

        renovar($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2028-03-31'])
            ->assertSessionHasNoErrors();

        expect(tramosDe($contrato))->toHaveCount(6);
    });

    it('no dice nada cuando el contrato inicial es de un año o más', function () {
        config(['validaciones.renovacion_prorroga_minima' => 'bloquea']);

        // La regla del artículo 46 solo aplica a los contratos inferiores a un
        // año: uno de un año se prorroga por el plazo que quieran las partes.
        $contrato = contratoFijo('Anual', '2027-01-01', '2027-12-31', [
            ['2028-01-01', '2028-03-31'],
            ['2028-04-01', '2028-06-30'],
            ['2028-07-01', '2028-09-30'],
            ['2028-10-01', '2028-12-31'],
        ]);

        renovar($contrato, ['tipo' => 'prorroga_pactada', 'fecha_fin' => '2029-03-31'])
            ->assertSessionHasNoErrors();
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
