<?php

use App\Auditoria\AccionAuditada;
use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Contratacion\TipoContrato;
use App\Models\Auditoria;
use App\Models\PeriodoContractual;
use App\Models\Tenant;
use App\Models\Vinculacion;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * La acción «Cambiar a término indefinido» (documento 1, 5.5, y documento 3,
 * sección 5.4).
 *
 * Lo que aquí se defiende es el criterio de terminado de F1-11: **tras el cambio,
 * la antigüedad y el saldo siguen contando desde la fecha original.** De eso
 * dependen tres cosas que se prueban por separado:
 *
 * 1. **La fecha de ingreso no se toca**, ni la del vínculo ni la del tramo
 *    inicial del historial. Es el vínculo el que continúa; lo único que cambia es
 *    su modalidad, así que reescribir cualquiera de las dos fechas borraría la
 *    trazabilidad que el cambio pretende dejar.
 * 2. **El cambio queda registrado con la fecha en que se hizo**, en una fila
 *    nueva del historial y en la auditoría.
 * 3. **El contrato deja de vencer.** Un indefinido no tiene fecha de fin, y con
 *    ella desaparecen el preaviso y las alertas de vencimiento.
 *
 * Y la regla de existencia: **solo un término fijo se convierte**. En obra o
 * labor el paso a indefinido se hace por otrosí fuera del sistema, y el cambio
 * inverso no existe.
 *
 * `contratoFijo()`, `contratoSinPlazo()` y `detalleDeContrato()` viven en
 * `ContratosTest.php`, `fichaDe()` en `FichaDeEmpleadoTest.php`, `tramosDe()` en
 * `RenovacionTest.php` y `usuarioDe()` en `RolesYPermisosTest.php`: Pest carga
 * las funciones de todos los archivos de prueba.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-08-20'));

    $this->empresa = Tenant::factory()->create(['nombre' => 'Distribuidora El Progreso']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Contratos, Modulo::Empleados);
});

/**
 * Confirma el cambio a término indefinido.
 *
 * Sin `$fecha`, envía la propuesta por defecto —hoy, el mismo día de
 * `beforeEach()`—, así que las pruebas escritas antes de HD-11 no tienen que
 * saber que ahora el formulario pide una fecha.
 */
function cambiarAIndefinido(Vinculacion $contrato, ?string $fecha = null): TestResponse
{
    return test()->actingAs(test()->usuario)
        ->post('/contratos/'.$contrato->getKey().'/indefinido', [
            'fecha' => $fecha ?? Carbon::today()->toDateString(),
        ]);
}

/**
 * Lo que el servidor manda al modal de cambio a término indefinido, con la
 * fecha digitada que se le pase.
 *
 * @return array<string, mixed>
 */
function cambioAIndefinidoDe(Vinculacion $contrato, ?string $fecha = null): array
{
    $query = $fecha === null ? '' : '?'.http_build_query(['fecha' => $fecha]);

    $respuesta = test()->actingAs(test()->usuario)->get('/contratos/'.$contrato->getKey().$query);

    $respuesta->assertOk();

    return $respuesta->viewData('page')['props']['cambioAIndefinido'];
}

describe('cuándo se ofrece la acción', function () {
    it('la ofrece en un contrato a término fijo', function () {
        $contrato = contratoFijo('Fijo', '2025-07-15', '2026-09-12');

        expect(detalleDeContrato($contrato)['cambioAIndefinido']['disponible'])->toBeTrue();
    });

    it('no la ofrece en un contrato que ya es indefinido, porque no significa nada', function () {
        $contrato = contratoSinPlazo('YaIndefinido', TipoContrato::Indefinido);

        expect(detalleDeContrato($contrato)['cambioAIndefinido']['disponible'])->toBeFalse();
    });

    it('no la ofrece en obra o labor, donde el paso a indefinido se hace por otrosí', function () {
        $contrato = contratoSinPlazo('DeObra', TipoContrato::ObraLabor);

        expect(detalleDeContrato($contrato)['cambioAIndefinido']['disponible'])->toBeFalse();
    });

    it('rechaza la petición enviada a mano donde la acción no existe', function () {
        $contrato = contratoSinPlazo('DeObra', TipoContrato::ObraLabor);

        cambiarAIndefinido($contrato)->assertForbidden();

        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('deja de ofrecerla después del cambio: el inverso no existe', function () {
        $contrato = contratoFijo('Fijo', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato);

        expect(detalleDeContrato($contrato->fresh())['cambioAIndefinido']['disponible'])->toBeFalse();
        cambiarAIndefinido($contrato->fresh())->assertForbidden();
    });
});

describe('qué enseña el modal antes de confirmar', function () {
    it('nombra al empleado y las cinco consecuencias del cambio', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        $cambio = detalleDeContrato($contrato)['cambioAIndefinido'];

        expect($cambio['entrada'])->toContain('Juan Contratado');
        expect($cambio['implicaciones'])->toHaveCount(5);
        expect(implode(' ', $cambio['implicaciones']))
            ->toContain('El vínculo continúa')
            ->toContain('La fecha de ingreso no cambia: sigue siendo el 15/07/2025')
            ->toContain('siguen corriendo desde esa fecha')
            ->toContain('Dejarán de generarse las alertas')
            ->toContain('20/08/2026');
    });

    it('advierte que el cambio no se puede revertir, que es lo que hace irreversible la confirmación', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        expect(detalleDeContrato($contrato)['cambioAIndefinido']['advertencia'])
            ->toContain('no se puede revertir');
    });
});

describe('el efecto del cambio', function () {
    it('no mueve la fecha de ingreso ni el tramo inicial del historial', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12', [['2026-09-13', '2027-03-12']]);

        cambiarAIndefinido($contrato);

        $tramos = tramosDe($contrato);

        expect($contrato->fresh()->fecha_ingreso->toDateString())->toBe('2025-07-15');
        expect($tramos->first()->fecha_inicio->toDateString())->toBe('2025-07-15');
        expect($tramos->first()->fecha_fin->toDateString())->toBe('2026-09-12');
        expect($tramos->first()->tipo_contrato)->toBe(TipoContrato::Fijo);
    });

    it('mantiene la antigüedad contando desde la fecha original', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        $antes = datoDelResumen(fichaDe($contrato), 'Antigüedad')['valor'];

        cambiarAIndefinido($contrato);

        $despues = fichaDe($contrato->fresh());

        expect($antes)->toBe('1 año, 1 mes');
        expect(datoDelResumen($despues, 'Antigüedad')['valor'])->toBe($antes);
        expect(datoDelResumen($despues, 'Fecha de ingreso')['valor'])->toBe('15/07/2025');
    });

    it('agrega un tramo indefinido sin fecha de fin, con la fecha del cambio', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato);

        $ultimo = tramosDe($contrato)->last();

        expect($ultimo->evento)->toBe(EventoContractual::CambioAIndefinido);
        expect($ultimo->tipo_contrato)->toBe(TipoContrato::Indefinido);
        expect($ultimo->fecha_inicio->toDateString())->toBe('2026-08-20');
        expect($ultimo->fecha_fin)->toBeNull();
        expect($ultimo->orden)->toBe(2);
    });

    it('mueve lo vigente a la vez que el historial: el contrato deja de vencer', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato);

        $vigente = $contrato->fresh();

        expect($vigente->tipo_contrato)->toBe(TipoContrato::Indefinido);
        expect($vigente->fecha_fin)->toBeNull();
    });

    it('quita del detalle el vencimiento y el límite de preaviso', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato);

        $props = detalleDeContrato($contrato->fresh());
        $etiquetas = array_column($props['indicadores'], 'etiqueta');

        expect($props['contrato']['tipo'])->toBe('Término indefinido');
        expect($etiquetas)->not->toContain('Límite de preaviso');
        expect($etiquetas)->toContain('Prórrogas acumuladas');
    });

    it('lo saca del listado de contratos que vencen, porque ya no vence', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        expect(empleadosDe(filasDeContratos()))->toContain('Juan Contratado');

        cambiarAIndefinido($contrato);

        expect(filasDeContratos())->toBe([]);
    });

    it('deja el cambio en la auditoría', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato);

        $registros = Auditoria::query()
            ->where('modelo', PeriodoContractual::class)
            ->where('accion', AccionAuditada::Creado->value)
            ->count();

        // El tramo inicial y el del cambio: dos creaciones registradas.
        expect($registros)->toBe(2);
    });

    it('confirma en pantalla lo que hizo y lo que no', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato)
            ->assertRedirect('/contratos/'.$contrato->getKey())
            ->assertSessionHas('estado', fn (string $estado) => str_contains($estado, '20/08/2026')
                && str_contains($estado, 'La fecha de ingreso no cambió'));
    });

    it('en un contrato que aún no ha empezado, el tramo nuevo no arranca antes que el vigente', function () {
        $contrato = contratoFijo('Futuro', '2026-10-01', '2027-03-31');

        cambiarAIndefinido($contrato);

        expect(tramosDe($contrato)->last()->fecha_inicio->toDateString())->toBe('2026-10-01');
    });
});

describe('la fecha del cambio (HD-11)', function () {
    it('propone hoy y el rango editable hacia atrás hasta el inicio del periodo vigente', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        $cambio = cambioAIndefinidoDe($contrato);

        expect($cambio['fecha'])->toBe('2026-08-20');
        expect($cambio['minimo'])->toBe('2025-07-15');
        expect($cambio['maximo'])->toBe('2026-08-20');
    });

    it('acepta una fecha anterior a hoy y la usa como inicio del tramo indefinido', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato, '2026-08-13');

        expect(tramosDe($contrato)->last()->fecha_inicio->toDateString())->toBe('2026-08-13');
    });

    it('las implicaciones dicen la fecha elegida y no «hoy» literal', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        $cambio = cambioAIndefinidoDe($contrato, '2026-08-13');

        expect($cambio['fecha'])->toBe('2026-08-13');
        expect(implode(' ', $cambio['implicaciones']))
            ->toContain('13/08/2026')
            ->not->toContain('la fecha de hoy');
    });

    it('rechaza una fecha anterior al inicio del periodo vigente', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato, '2025-07-14')->assertSessionHasErrors('fecha');

        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('rechaza una fecha posterior a hoy', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato, '2026-08-21')->assertSessionHasErrors('fecha');

        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('en pantalla ignora una fecha fuera de rango y vuelve a la propuesta por defecto', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        expect(cambioAIndefinidoDe($contrato, '2026-08-21')['fecha'])->toBe('2026-08-20');
        expect(cambioAIndefinidoDe($contrato, '2025-07-14')['fecha'])->toBe('2026-08-20');
    });

    it('conserva el ajuste de coherencia: en un contrato que aún no ha empezado solo admite la fecha del periodo vigente', function () {
        $contrato = contratoFijo('Futuro', '2026-10-01', '2027-03-31');

        $cambio = cambioAIndefinidoDe($contrato);

        expect($cambio['fecha'])->toBe('2026-10-01');
        expect($cambio['minimo'])->toBe('2026-10-01');
        expect($cambio['maximo'])->toBe('2026-10-01');

        cambiarAIndefinido($contrato, '2026-08-20')->assertSessionHasErrors('fecha');
        expect(tramosDe($contrato))->toHaveCount(1);
    });

    it('el historial contractual queda con la fecha elegida y no con la de hoy', function () {
        $contrato = contratoFijo('Juan', '2025-07-15', '2026-09-12');

        cambiarAIndefinido($contrato, '2026-08-13');

        $props = detalleDeContrato($contrato->fresh());
        $fila = collect($props['historial'])->firstWhere('evento', 'Cambio a término indefinido');

        expect($fila['desde'])->toBe('13/08/2026');
    });
});

describe('el aislamiento entre empresas', function () {
    it('no deja convertir el contrato de otra empresa', function () {
        $ajena = Tenant::factory()->create(['nombre' => 'Otra Empresa']);
        $contrato = contratoFijo('Ajeno', '2025-07-15', '2026-09-12');

        $intruso = usuarioDe($ajena, Rol::Administrador, Modulo::Contratos);

        test()->actingAs($intruso)
            ->post('/contratos/'.$contrato->getKey().'/indefinido')
            ->assertNotFound();

        expect($contrato->fresh()->tipo_contrato)->toBe(TipoContrato::Fijo);
    });
});
