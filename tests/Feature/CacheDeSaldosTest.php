<?php

use App\Contratacion\FichaDeEmpleado;
use App\Dominio\Alertas\ProcesoDiarioDeAlertas;
use App\Dominio\Calculo\CacheDeSaldos;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\SaldoEnCache;
use App\Models\Tenant;
use App\Models\Vacacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Presentacion\Formato;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * La caché de saldos de vacaciones (F4-02, documento 2, 3.4).
 *
 * Lo que hay que demostrar de una caché son dos cosas y tiran en direcciones
 * contrarias, así que las dos tienen su prueba:
 *
 * 1. **Que sirve para lo que se hizo.** El «hecho cuando» de la tarea: sumar el
 *    saldo de doscientos empleados en menos de dos segundos.
 * 2. **Que no manda.** La sección 3.4 y la regla del motor dicen lo mismo con
 *    las mismas palabras —«nunca la fuente de verdad; si hay diferencia, manda
 *    el cálculo»—, y aquí se comprueba envenenando una fila y viendo que quien
 *    pregunta por el saldo de una persona sigue sin enterarse.
 *
 * La fecha se fija en todo el archivo: el saldo depende de cuántos meses
 * cerrados lleva la vinculación, así que una prueba que corriera un día 1 y otra
 * un día 31 no compararían lo mismo.
 */
const HOY_DE_LA_CACHE = '2026-08-23';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse(HOY_DE_LA_CACHE));

    $this->contexto = app(ContextoTenant::class);
    $this->empresa = Tenant::factory()->create(['nombre' => 'Empresa de la caché']);
    $this->contexto->fijar($this->empresa);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Un empleado activo con un año largo de antigüedad, que es lo que hace falta
 * para que tenga saldo que sumar.
 */
function empleadoConSaldo(string $ingreso = '2025-03-01'): Vinculacion
{
    return Vinculacion::factory()->conIngreso($ingreso)->create();
}

/**
 * El saldo de cada activo sumado a pulso, sin pasar por la caché. Es el número
 * con el que se contrasta todo lo de aquí.
 */
function sumaCalculada(?Carbon $al = null): float
{
    $fecha = $al ?? Carbon::today();

    return round(Vinculacion::query()->activas()->get()->sum(
        fn (Vinculacion $vinculacion): float => SaldoDeVacaciones::de($vinculacion)->al($fecha)
    ), 4);
}

describe('el refresco', function () {
    it('deja una fila por empleado activo con el mismo número que calcula el motor', function () {
        $empleados = collect([empleadoConSaldo(), empleadoConSaldo('2024-06-15'), empleadoConSaldo('2025-11-01')]);

        $cuantos = CacheDeSaldos::refrescar();

        expect($cuantos)->toBe(3)
            ->and(SaldoEnCache::query()->count())->toBe(3);

        $empleados->each(function (Vinculacion $vinculacion) {
            $fila = SaldoEnCache::query()->where('vinculacion_id', $vinculacion->getKey())->firstOrFail();

            expect($fila->dias)->toBe(SaldoDeVacaciones::de($vinculacion)->al(Carbon::today()))
                ->and($fila->calculado_al->toDateString())->toBe(HOY_DE_LA_CACHE);
        });
    });

    it('no duplica a nadie al correr dos veces el mismo día', function () {
        empleadoConSaldo();
        empleadoConSaldo();

        CacheDeSaldos::refrescar();
        $identificadores = SaldoEnCache::query()->orderBy('id')->pluck('id')->all();

        CacheDeSaldos::refrescar();

        // Las mismas filas, no otras dos: es el índice único trabajando, igual
        // que en `alertas_enviadas`.
        expect(SaldoEnCache::query()->count())->toBe(2)
            ->and(SaldoEnCache::query()->orderBy('id')->pluck('id')->all())->toBe($identificadores);
    });

    it('actualiza el número cuando cambia una novedad de por medio', function () {
        $empleado = empleadoConSaldo();

        CacheDeSaldos::refrescar();
        $antes = SaldoEnCache::query()->firstOrFail()->dias;

        Vacacion::factory()->de($empleado)->del27al31DeJulio()->create();

        CacheDeSaldos::refrescar();

        $despues = SaldoEnCache::query()->firstOrFail()->dias;

        expect($despues)->toBe(SaldoDeVacaciones::de($empleado)->al(Carbon::today()))
            ->and($antes - $despues)->toEqualWithDelta(5.0, 0.0001);
    });

    it('saca de la caché al empleado que ya no está activo', function () {
        $sigue = empleadoConSaldo();
        $sale = empleadoConSaldo();

        CacheDeSaldos::refrescar();
        expect(SaldoEnCache::query()->count())->toBe(2);

        $sale->registrarTerminacion(TipoDeSalida::Renuncia, 'Motivos personales', Carbon::parse('2026-08-20'));

        CacheDeSaldos::refrescar();

        expect(SaldoEnCache::query()->count())->toBe(1)
            ->and(SaldoEnCache::query()->firstOrFail()->vinculacion_id)->toBe($sigue->getKey());
    });

    it('no deja ni una línea de auditoría', function () {
        // El refresco lo escribe un proceso, no una persona, y reescribe cada
        // noche un número derivado. Auditarlo llenaría la tabla que sirve para
        // saber quién tocó qué con filas sin autor y sin nada que contar.
        empleadoConSaldo();
        empleadoConSaldo();

        $antes = Auditoria::query()->count();

        CacheDeSaldos::refrescar();

        expect(Auditoria::query()->count())->toBe($antes);
    });
});

describe('el total de la planta', function () {
    it('suma exactamente lo mismo que calcular uno por uno', function () {
        empleadoConSaldo();
        empleadoConSaldo('2024-06-15');
        empleadoConSaldo('2025-11-01');

        CacheDeSaldos::refrescar();

        $total = CacheDeSaldos::total();

        expect($total->dias)->toBe(sumaCalculada())
            ->and($total->empleados)->toBe(3)
            ->and($total->desdeLaCache)->toBe(3)
            ->and($total->calculadosAlVuelo)->toBe(0)
            ->and($total->vieneToda())->toBeTrue()
            ->and($total->al->toDateString())->toBe(HOY_DE_LA_CACHE);
    });

    it('incluye al empleado registrado después del último refresco, calculándolo al vuelo', function () {
        empleadoConSaldo();
        CacheDeSaldos::refrescar();

        // Registrado esta mañana, así que todavía no ha pasado por ninguna
        // noche. Su antigüedad no tiene nada que ver: lo reciente es la ficha.
        $reciente = empleadoConSaldo('2024-02-01');

        $total = CacheDeSaldos::total();

        expect($total->empleados)->toBe(2)
            ->and($total->desdeLaCache)->toBe(1)
            ->and($total->calculadosAlVuelo)->toBe(1)
            ->and($total->vieneToda())->toBeFalse()
            // Y el número es el correcto, no el viejo: la caché puede dar una
            // cifra de ayer, nunca una que se olvide de alguien.
            ->and($total->dias)->toBe(sumaCalculada())
            ->and($total->dias)->toBeGreaterThan(
                SaldoDeVacaciones::de($reciente)->al(Carbon::today())
            );
    });

    it('responde sin caché ninguna, solo que despacio', function () {
        empleadoConSaldo();
        empleadoConSaldo('2024-06-15');

        // Nadie ha refrescado todavía. Borrar la tabla entera no pierde un dato:
        // es la comprobación de que esto es una optimización y no un almacén.
        $total = CacheDeSaldos::total();

        expect($total->dias)->toBe(sumaCalculada())
            ->and($total->desdeLaCache)->toBe(0)
            ->and($total->calculadosAlVuelo)->toBe(2)
            ->and($total->al->toDateString())->toBe(HOY_DE_LA_CACHE);
    });

    it('deja fuera al empleado terminado aunque su fila siga en la caché', function () {
        $sigue = empleadoConSaldo();
        $sale = empleadoConSaldo();

        CacheDeSaldos::refrescar();

        // Se termina **después** del refresco: la fila sigue ahí hasta la noche
        // siguiente, y el total no puede contarla.
        $sale->registrarTerminacion(TipoDeSalida::Renuncia, 'Motivos personales', Carbon::parse('2026-08-20'));

        $total = CacheDeSaldos::total();

        expect($total->empleados)->toBe(1)
            ->and($total->dias)->toBe(round(SaldoDeVacaciones::de($sigue)->al(Carbon::today()), 4));
    });

    /**
     * El «hecho cuando» de F4-02.
     *
     * Se mide solo la lectura, que es lo que ocurre dentro de la petición del
     * panel de inicio. El refresco tarda lo que tenga que tardar: corre de
     * noche y no hay nadie esperándolo.
     */
    it('suma el saldo de 200 empleados en menos de dos segundos', function () {
        // Las personas se crean de una sola tanda para que compartan municipio:
        // una fábrica por persona agotaría los códigos de departamento del
        // catálogo, y lo que se está midiendo no es eso.
        Persona::factory()->count(200)->create()->each(
            fn (Persona $persona) => Vinculacion::factory()->de($persona)->conIngreso('2024-05-01')->create()
        );

        CacheDeSaldos::refrescar();

        $arranque = microtime(true);
        $total = CacheDeSaldos::total();
        $tardanza = microtime(true) - $arranque;

        expect($total->empleados)->toBe(200)
            ->and($total->calculadosAlVuelo)->toBe(0)
            ->and($total->dias)->toBeGreaterThan(0.0)
            ->and($tardanza)->toBeLessThan(2.0);
    });
});

describe('la caché nunca es la fuente de verdad', function () {
    /**
     * La regla del motor, palabra por palabra: «si hay diferencia, manda el
     * cálculo». Se envenena la fila y se pregunta por el saldo de esa persona
     * por los caminos que un usuario tiene para preguntarlo.
     */
    it('la ficha del empleado enseña el saldo calculado, no el de la fila envenenada', function () {
        $empleado = empleadoConSaldo();

        CacheDeSaldos::refrescar();

        SaldoEnCache::query()->firstOrFail()->forceFill(['dias' => 999.0])->save();

        $calculado = SaldoDeVacaciones::de($empleado)->al(Carbon::today());

        expect(FichaDeEmpleado::de($empleado)->saldoDeVacaciones()['valor'])
            ->toBe(Formato::diasDecimales($calculado))
            ->not->toContain('999');
    });

    it('el refresco siguiente corrige sola la fila envenenada', function () {
        $empleado = empleadoConSaldo();

        CacheDeSaldos::refrescar();
        SaldoEnCache::query()->firstOrFail()->forceFill(['dias' => 999.0])->save();

        CacheDeSaldos::refrescar();

        expect(SaldoEnCache::query()->firstOrFail()->dias)
            ->toBe(SaldoDeVacaciones::de($empleado)->al(Carbon::today()));
    });
});

describe('el proceso diario', function () {
    it('refresca la caché de cada empresa, dentro del contexto de cada una', function () {
        Queue::fake();

        $otra = Tenant::factory()->create(['nombre' => 'La otra empresa']);

        $this->contexto->paraTenant($this->empresa, function () {
            empleadoConSaldo();
            empleadoConSaldo();
        });

        $this->contexto->paraTenant($otra, fn () => empleadoConSaldo());

        $resumen = app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(HOY_DE_LA_CACHE));

        expect($resumen->saldosRefrescados())->toBe(3)
            ->and($resumen->fallidas())->toBe([])
            ->and($this->contexto->paraTenant($this->empresa, fn () => SaldoEnCache::query()->count()))->toBe(2)
            ->and($this->contexto->paraTenant($otra, fn () => SaldoEnCache::query()->count()))->toBe(1);
    });

    it('no refresca la caché de una empresa que no está activa', function () {
        Queue::fake();

        $suspendida = Tenant::factory()->suspendida()->create(['nombre' => 'Suspendida por mora']);

        $this->contexto->paraTenant($suspendida, fn () => empleadoConSaldo());

        app(ProcesoDiarioDeAlertas::class)->correr(Carbon::parse(HOY_DE_LA_CACHE));

        expect($this->contexto->paraTenant($suspendida, fn () => SaldoEnCache::query()->count()))->toBe(0);
    });
});
