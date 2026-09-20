<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\EventoContractual;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Terminaciones\ContratoSinAclarar;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * Una terminación no se registra sobre un contrato a término fijo que nadie
 * ha aclarado (HD-12 · documento 6, D.7).
 *
 * El caso que reprodujo David: contrato del 1 de enero al 31 de marzo de 2026,
 * prórroga hasta el 30 de junio, nada registrado después, y una salida del 10 de
 * septiembre que el sistema aceptaba. Queda un vacío del 1 de julio al 10 de
 * septiembre en el que nadie sabe qué contrato regía, y de eso depende la
 * indemnización si la salida la motivó la empresa.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php`; `datosDeTerminacion()` en
 * `TerminacionesTest.php`, igual que `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create();
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Terminaciones);
});

/**
 * El contrato del caso de David: del 1 de enero al 31 de marzo de 2026, con
 * una prórroga hasta el 30 de junio y nada después.
 */
function contratoConProrrogaHastaJunio(): Vinculacion
{
    return app(ContextoTenant::class)->paraTenant(test()->empresa, function (): Vinculacion {
        $vinculacion = Vinculacion::factory()
            ->de(Persona::factory()->create(['numero_documento' => '1015887432']))
            ->aTerminoFijo('2026-01-01', '2026-03-31')
            ->create();

        $vinculacion->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2026-06-30'));

        return $vinculacion;
    });
}

function salirEl(Vinculacion $vinculacion, string $fecha): TestResponse
{
    return test()->actingAs(test()->usuario)->post('/terminaciones', datosDeTerminacion([
        'vinculacion_id' => $vinculacion->id,
        'fecha_salida' => $fecha,
    ]));
}

function terminacionesDe(Vinculacion $vinculacion): int
{
    return app(ContextoTenant::class)->paraTenant(
        test()->empresa,
        fn () => Terminacion::query()->where('vinculacion_id', $vinculacion->id)->count(),
    );
}

describe('el contrato a término fijo con el vacío sin aclarar', function () {
    it('bloquea la salida y dice las fechas exactas que quedan sin cubrir', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        $respuesta = salirEl($vinculacion, '2026-09-10');

        $respuesta->assertSessionHasErrors('fecha_salida');

        $mensaje = session('errors')->first('fecha_salida');

        expect($mensaje)->toContain('venció el 30 de junio de 2026')
            ->toContain('los días del 1 de julio de 2026 al 10 de septiembre de 2026')
            ->toContain('indemnización');
    });

    it('ofrece las dos salidas del vacío: la prórroga y el cambio a indefinido', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-09-10');

        $mensaje = session('errors')->first('fecha_salida');

        expect($mensaje)->toContain('registra la prórroga')
            ->toContain('cambio a término indefinido');
    });

    it('no toca al empleado: sigue activo y sin terminación', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-09-10');

        $actual = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect($actual->estaActiva())->toBeTrue()
            ->and(terminacionesDe($vinculacion))->toBe(0);
    });

    it('cuando el vacío es de un solo día lo dice en singular', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-07-01')->assertSessionHasErrors('fecha_salida');

        expect(session('errors')->first('fecha_salida'))->toContain('quedaría sin cubrir el día 1 de julio de 2026');
    });

    it('deja de bloquear en cuanto se registra la prórroga que cubre la salida', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-09-10')->assertSessionHasErrors('fecha_salida');

        app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $vinculacion->fresh()->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2026-12-31')),
        );

        salirEl($vinculacion, '2026-09-10')->assertRedirect('/terminaciones');

        expect(terminacionesDe($vinculacion))->toBe(1);
    });

    it('deja de bloquear en cuanto el contrato pasa a término indefinido', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $vinculacion->fresh()->cambiarATerminoIndefinido(Carbon::parse('2026-07-01')),
        );

        salirEl($vinculacion, '2026-09-10')->assertRedirect('/terminaciones');

        expect(terminacionesDe($vinculacion))->toBe(1);
    });

    it('sigue bloqueando aunque la prórroga registrada no alcance a cubrir la salida', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $vinculacion->fresh()->renovar(EventoContractual::ProrrogaPactada, Carbon::parse('2026-08-31')),
        );

        salirEl($vinculacion, '2026-09-10')->assertSessionHasErrors('fecha_salida');

        expect(session('errors')->first('fecha_salida'))
            ->toContain('venció el 31 de agosto de 2026')
            ->toContain('del 1 de septiembre de 2026 al 10 de septiembre de 2026');
    });
});

describe('lo que la regla no toca', function () {
    it('deja registrar la salida dentro del periodo vigente', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-06-15')->assertRedirect('/terminaciones');

        expect(terminacionesDe($vinculacion))->toBe(1);
    });

    it('deja registrar la salida el mismo día del vencimiento', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-06-30')->assertRedirect('/terminaciones');

        expect(terminacionesDe($vinculacion))->toBe(1);
    });

    it('no afecta a un contrato a término indefinido', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        salirEl($vinculacion, '2030-01-31')->assertRedirect('/terminaciones');

        expect(terminacionesDe($vinculacion))->toBe(1);
    });

    it('no afecta a un contrato de obra o labor', function () {
        $vinculacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Vinculacion::factory()
                ->de(Persona::factory()->create(['numero_documento' => '1015887432']))
                ->deObraOLabor()
                ->create(),
        );

        salirEl($vinculacion, '2030-01-31')->assertRedirect('/terminaciones');

        expect(terminacionesDe($vinculacion))->toBe(1);
    });

    it('no responde por el vacío cuando la fecha de salida ni siquiera es una fecha', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, 'no es una fecha')->assertSessionHasErrors('fecha_salida');

        expect(session('errors')->first('fecha_salida'))->not->toContain('venció');
    });
});

describe('corregir una salida ya registrada', function () {
    it('no deja mover la fecha a una posterior al vencimiento', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-06-15')->assertRedirect('/terminaciones');

        $terminacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        $this->actingAs($this->usuario)
            ->put('/terminaciones/'.$terminacion->id, datosDeTerminacion(['fecha_salida' => '2026-09-10']))
            ->assertSessionHasErrors('fecha_salida');

        $sinTocar = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->findOrFail($terminacion->id),
        );

        expect($sinTocar->fecha_salida->toDateString())->toBe('2026-06-15');
    });

    it('sí deja corregir un dato cualquiera de una salida dentro del periodo vigente', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        salirEl($vinculacion, '2026-06-15')->assertRedirect('/terminaciones');

        $terminacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        $this->actingAs($this->usuario)
            ->put('/terminaciones/'.$terminacion->id, datosDeTerminacion(['fecha_salida' => '2026-06-20']))
            ->assertRedirect('/terminaciones');
    });
});

describe('ContratoSinAclarar', function () {
    it('cuenta el vacío desde el día siguiente al vencimiento hasta la salida, los dos incluidos', function () {
        $vinculacion = contratoConProrrogaHastaJunio();

        $vacio = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => ContratoSinAclarar::para($vinculacion->fresh(), Carbon::parse('2026-09-10')),
        );

        expect($vacio)->not->toBeNull()
            ->and($vacio->desde()->toDateString())->toBe('2026-07-01')
            ->and($vacio->hasta()->toDateString())->toBe('2026-09-10');
    });
});
