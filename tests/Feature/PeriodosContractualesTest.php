<?php

use App\Contratacion\EventoContractual;
use App\Contratacion\HistorialDeContrato;
use App\Contratacion\TipoContrato;
use App\Models\PeriodoContractual;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Multiempresa\PerteneceATenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El historial del contrato: la tabla `periodos_contractuales` y las cuentas
 * que se hacen sobre ella (documento 2, 3.3).
 *
 * Lo que estas pruebas defienden es que el historial **empieza en el contrato
 * original y no en la primera prórroga**, y que las dos cuentas de las que
 * dependen las validaciones bloqueantes de 5.5 —prórrogas acumuladas y
 * duración total del vínculo— dan el número correcto.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Empresa A']);
});

/**
 * Ejecuta algo dentro de la empresa de la prueba.
 */
function enLaEmpresaDelContrato(Closure $accion): mixed
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, $accion);
}

describe('el periodo inicial', function () {
    it('nace con la vinculación, sin que nadie lo registre', function () {
        $vinculacion = enLaEmpresaDelContrato(
            fn () => Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create()
        );

        $inicial = enLaEmpresaDelContrato(fn () => $vinculacion->historial()->inicial());

        expect($inicial)->not->toBeNull()
            ->and($inicial?->orden)->toBe(1)
            ->and($inicial?->evento)->toBe(EventoContractual::Inicial)
            ->and($inicial?->esInicial())->toBeTrue();
    });

    it('copia la modalidad y las fechas del contrato que se acaba de registrar', function () {
        $vinculacion = enLaEmpresaDelContrato(
            fn () => Vinculacion::factory()->aTerminoFijo('2026-02-15', '2026-08-14')->create()
        );

        $inicial = enLaEmpresaDelContrato(fn () => $vinculacion->historial()->inicial());

        expect($inicial?->tipo_contrato)->toBe(TipoContrato::Fijo)
            ->and($inicial?->fecha_inicio->toDateString())->toBe('2026-02-15')
            ->and($inicial?->fecha_fin?->toDateString())->toBe('2026-08-14');
    });

    it('deja el tramo sin fecha de finalización en indefinido y en obra o labor', function () {
        [$indefinida, $obra] = enLaEmpresaDelContrato(fn () => [
            Vinculacion::factory()->create(),
            Vinculacion::factory()->deObraOLabor()->create(),
        ]);

        expect(enLaEmpresaDelContrato(fn () => $indefinida->historial()->inicial()?->fecha_fin))->toBeNull()
            ->and(enLaEmpresaDelContrato(fn () => $obra->historial()->inicial()?->fecha_fin))->toBeNull();
    });

    it('no admite dos tramos con el mismo orden en la misma vinculación', function () {
        enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo()->create();

            // El 1 ya lo tiene el periodo inicial.
            PeriodoContractual::factory()->de($vinculacion)->create(['orden' => 1]);
        });
    })->throws(QueryException::class);
});

describe('el historial', function () {
    it('numera cada tramo nuevo a continuación del anterior', function () {
        $ordenes = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-06-30')->create();

            $vinculacion->registrarPeriodo(
                EventoContractual::ProrrogaPactada,
                TipoContrato::Fijo,
                Carbon::parse('2026-07-01'),
                Carbon::parse('2026-12-31'),
            );

            $vinculacion->registrarPeriodo(
                EventoContractual::CambioAIndefinido,
                TipoContrato::Indefinido,
                Carbon::parse('2027-01-01'),
            );

            return $vinculacion->historial()->periodos()->pluck('orden')->all();
        });

        expect($ordenes)->toBe([1, 2, 3]);
    });

    it('incluye el periodo inicial y va del primer tramo al último', function () {
        $eventos = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-06-30')->create();

            PeriodoContractual::factory()->de($vinculacion)->entre('2026-07-01', '2026-12-31')->create();
            PeriodoContractual::factory()->de($vinculacion)->automatica()->entre('2027-01-01', '2027-06-30')->create();

            return $vinculacion->historial()->periodos()
                ->map(fn (PeriodoContractual $tramo) => $tramo->evento->value)
                ->all();
        });

        expect($eventos)->toBe(['inicial', 'prorroga_pactada', 'prorroga_automatica']);
    });

    it('toma como vigente el último tramo, con su modalidad y su vencimiento', function () {
        $vinculacion = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-06-30')->create();

            PeriodoContractual::factory()->de($vinculacion)->entre('2026-07-01', '2027-03-31')->create();

            return $vinculacion;
        });

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->vigente()?->orden)->toBe(2)
            ->and($historial->tipoDeContratoVigente())->toBe(TipoContrato::Fijo)
            ->and($historial->fechaDeVencimiento()?->toDateString())->toBe('2027-03-31');
    });

    it('deja sin fecha de vencimiento el contrato que pasó a indefinido', function () {
        $vinculacion = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create();

            PeriodoContractual::factory()->de($vinculacion)->cambioAIndefinido('2027-01-01')->create();

            return $vinculacion;
        });

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->fechaDeVencimiento())->toBeNull()
            ->and($historial->tipoDeContratoVigente())->toBe(TipoContrato::Indefinido)
            // La fecha de ingreso no se mueve: el vínculo es el mismo y la
            // antigüedad sigue corriendo desde el contrato a término fijo.
            ->and($historial->inicial()?->fecha_inicio->toDateString())->toBe('2026-01-01');
    });
});

describe('las prórrogas acumuladas', function () {
    it('cuenta las pactadas y las automáticas por separado', function () {
        // Se cuentan aparte porque el límite legal no es el mismo: hasta cuatro
        // prórrogas cortas pactadas y solo tres automáticas.
        $historial = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-03-31')->create();

            PeriodoContractual::factory()->de($vinculacion)->entre('2026-04-01', '2026-06-30')->create();
            PeriodoContractual::factory()->de($vinculacion)->entre('2026-07-01', '2026-09-30')->create();
            PeriodoContractual::factory()->de($vinculacion)->automatica()->entre('2026-10-01', '2026-12-31')->create();

            return $vinculacion->historial();
        });

        expect($historial->prorrogasAcumuladas())->toBe(3)
            ->and($historial->prorrogasPactadas())->toBe(2)
            ->and($historial->prorrogasAutomaticas())->toBe(1);
    });

    it('no cuenta como prórroga el periodo inicial ni el cambio a indefinido', function () {
        $historial = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create();

            PeriodoContractual::factory()->de($vinculacion)->cambioAIndefinido('2027-01-01')->create();

            return $vinculacion->historial();
        });

        expect($historial->prorrogasAcumuladas())->toBe(0);
    });

    it('empieza en cero en un contrato recién registrado', function () {
        $vinculacion = enLaEmpresaDelContrato(fn () => Vinculacion::factory()->aTerminoFijo()->create());

        expect(enLaEmpresaDelContrato(fn () => $vinculacion->historial()->prorrogasAcumuladas()))->toBe(0);
    });
});

describe('la duración total del vínculo', function () {
    it('corre desde el primer tramo hasta el vencimiento vigente', function () {
        $historial = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-12-31')->create();

            PeriodoContractual::factory()->de($vinculacion)->entre('2027-01-01', '2027-06-30')->create();

            return $vinculacion->historial();
        });

        // 365 días de 2026 más 181 del primer semestre de 2027, contando los
        // dos extremos.
        expect($historial->inicioDelConteoDelTope()?->toDateString())->toBe('2026-01-01')
            ->and($historial->duracionTotalEnDias())->toBe(546);
    });

    it('cuenta desde la reforma, no desde la firma, en un contrato anterior a ella', function () {
        // Documento 6, C.2: los contratos que se venían prorrogando bajo la
        // norma anterior son situaciones consolidadas y la ley no es
        // retroactiva. El conteo arranca el 25 de junio de 2025.
        $historial = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2023-03-01', '2024-02-29')->create();

            PeriodoContractual::factory()->de($vinculacion)->entre('2024-03-01', '2025-02-28')->create();
            PeriodoContractual::factory()->de($vinculacion)->entre('2025-03-01', '2026-02-28')->create();

            return $vinculacion->historial();
        });

        expect($historial->inicioDelConteoDelTope()?->toDateString())->toBe('2025-06-25')
            ->and($historial->duracionTotalEnDias())->toBe(249);
    });

    it('no devuelve duración cuando el contrato no tiene fecha de finalización', function () {
        $vinculacion = enLaEmpresaDelContrato(fn () => Vinculacion::factory()->create());

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->duracionTotalEnDias())->toBeNull()
            ->and($historial->fechaDeVencimiento())->toBeNull();
    });

    it('mide hasta la fecha que se le pida', function () {
        $vinculacion = enLaEmpresaDelContrato(
            fn () => Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-01-31')->create()
        );

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->duracionTotalEnDias(Carbon::parse('2026-01-10')))->toBe(10);
    });
});

describe('el tope de cuatro años', function () {
    it('lo sitúa cuatro años después del inicio del conteo', function () {
        $vinculacion = enLaEmpresaDelContrato(
            fn () => Vinculacion::factory()->aTerminoFijo('2026-03-01', '2026-12-31')->create()
        );

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->fechaDelTopeLegal()?->toDateString())->toBe('2030-03-01');
    });

    it('lo cuenta desde la reforma en los contratos anteriores a ella', function () {
        $vinculacion = enLaEmpresaDelContrato(
            fn () => Vinculacion::factory()->aTerminoFijo('2019-01-01', '2019-12-31')->create()
        );

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->fechaDelTopeLegal()?->toDateString())->toBe('2029-06-25');
    });

    it('no lo da por superado el día en que se cumple, sí al día siguiente', function () {
        $vinculacion = enLaEmpresaDelContrato(
            fn () => Vinculacion::factory()->aTerminoFijo('2026-03-01', '2026-12-31')->create()
        );

        $historial = enLaEmpresaDelContrato(fn () => $vinculacion->historial());

        expect($historial->superaElTopeLegal(Carbon::parse('2030-02-28')))->toBeFalse()
            ->and($historial->superaElTopeLegal(Carbon::parse('2030-03-01')))->toBeFalse()
            ->and($historial->superaElTopeLegal(Carbon::parse('2030-03-02')))->toBeTrue();
    });
});

describe('la forma de la tabla', function () {
    it('lleva tenant_id, borrado lógico y el trait', function () {
        expect(Schema::hasColumn('periodos_contractuales', 'tenant_id'))->toBeTrue()
            ->and(Schema::hasColumn('periodos_contractuales', 'deleted_at'))->toBeTrue()
            ->and(class_uses_recursive(PeriodoContractual::class))->toContain(PerteneceATenant::class);
    });

    it('tiene su política de aislamiento activa en PostgreSQL', function () {
        expect(DB::table('pg_policies')->pluck('tablename')->all())
            ->toContain('periodos_contractuales');
    });

    it('guarda el evento como texto legible', function () {
        $vinculacion = enLaEmpresaDelContrato(fn () => Vinculacion::factory()->aTerminoFijo()->create());

        $guardado = enLaEmpresaDelContrato(fn () => DB::table('periodos_contractuales')
            ->where('vinculacion_id', $vinculacion->id)
            ->value('evento'));

        expect($guardado)->toBe('inicial');
    });

    it('arma el historial con tramos ya cargados, sin volver a consultar', function () {
        // Es el camino de los listados, que traen las vinculaciones con sus
        // periodos de una vez.
        $periodos = enLaEmpresaDelContrato(function () {
            $vinculacion = Vinculacion::factory()->aTerminoFijo('2026-01-01', '2026-06-30')->create();

            PeriodoContractual::factory()->de($vinculacion)->entre('2026-07-01', '2026-12-31')->create();

            return $vinculacion->load('periodos')->periodos;
        });

        $historial = HistorialDeContrato::con($periodos);

        expect($historial->periodos())->toHaveCount(2)
            ->and($historial->prorrogasAcumuladas())->toBe(1)
            ->and($historial->proximoOrden())->toBe(3);
    });
});
