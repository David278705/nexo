<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\AdvertenciaDeReingreso;
use App\Dominio\Alertas\Reglas\PruebaIndefinidoUnMes;
use App\Dominio\Calculo\SaldoDeVacaciones;
use App\Exports\TerminacionesExport;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Terminacion;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Terminaciones\CategoriaDeRotacion;
use App\Terminaciones\ListadoDeTerminaciones;
use App\Terminaciones\TipoDeSalida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Terminaciones (documento 1, capítulo 7), con el mismo armazón de listado
 * (REV-01, REV-02) y el mismo flujo compartido de registro (F2-03) que los
 * otros cuatro módulos.
 *
 * Lo que este módulo tiene y ninguno más: **el registro apaga al empleado**.
 * Por eso lo primero que se prueba es el «hecho cuando» de la tarea —cambiar el
 * tipo de salida recarga los motivos y el empleado pasa a inactivo— y después
 * las tres reglas propias del capítulo 7: los motivos dependientes, el
 * autocompletado y la categoría de rotación deducida.
 *
 * `usuarioDe()` vive en `RolesYPermisosTest.php` y `empleadoParaNovedad()` en
 * `FlujoDeNovedadTest.php`; Pest carga las funciones de todos los archivos.
 */
beforeEach(function () {
    $this->empresa = Tenant::factory()->create(['nombre' => 'Comercializadora del Valle']);
    $this->usuario = usuarioDe($this->empresa, Rol::Administrador, Modulo::Terminaciones);
});

function datosDeTerminacion(array $cambios = []): array
{
    return [
        'tipo_salida' => 'renuncia',
        'motivo' => 'Mejor oferta laboral — salario',
        'fecha_salida' => '2026-07-31',
        ...$cambios,
    ];
}

describe('registrar la terminación cierra el ciclo de la vinculación', function () {
    it('deja al empleado inactivo y guarda la salida', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $respuesta = $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'observaciones' => 'Entrega el puesto el viernes.',
        ]));

        $respuesta->assertRedirect('/terminaciones')
            ->assertSessionHas('estado', 'Terminación registrada. El empleado pasó a inactivo.');

        [$actualizada, $terminacion] = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            $vinculacion->fresh(),
            Terminacion::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        ]);

        expect($actualizada->estaActiva())->toBeFalse()
            ->and($terminacion->tipo_salida)->toBe(TipoDeSalida::Renuncia)
            ->and($terminacion->motivo)->toBe('Mejor oferta laboral — salario')
            ->and($terminacion->fecha_salida->format('Y-m-d'))->toBe('2026-07-31')
            ->and($terminacion->observaciones)->toBe('Entrega el puesto el viernes.');
    });

    it('no deja terminar dos veces a la misma persona', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
        ]));

        // La segunda no pasa porque ya está inactivo, que es la misma guardia
        // que impide registrar cualquier novedad sobre quien se fue.
        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
        ]))->assertSessionHasErrors('vinculacion_id');

        $cuantas = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->where('vinculacion_id', $vinculacion->id)->count(),
        );

        expect($cuantas)->toBe(1);
    });

    it('deja al empleado tocado por nadie cuando la petición no pasa la validación', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'motivo' => 'Un motivo que nadie escribió',
        ]));

        [$actualizada, $cuantas] = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            $vinculacion->fresh(),
            Terminacion::query()->count(),
        ]);

        expect($actualizada->estaActiva())->toBeTrue()
            ->and($cuantas)->toBe(0);
    });

    it('la fecha de salida registrada es la que responde la vinculación', function () {
        // Un indefinido no tiene `fecha_fin`: antes de F2-09 esta vinculación
        // no sabía decir cuándo se fue la persona.
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432'], ['fecha_fin' => null]);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'fecha_salida' => '2026-07-31',
        ]));

        $salida = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $vinculacion->fresh()->fechaDeSalida(),
        );

        expect($salida)->not->toBeNull()
            ->and($salida->format('Y-m-d'))->toBe('2026-07-31');
    });

    it('deja de acumular vacaciones el día de la salida registrada', function () {
        Carbon::setTestNow(Carbon::parse('2026-08-22'));

        $vinculacion = empleadoParaNovedad(
            ['numero_documento' => '1015887432'],
            ['fecha_ingreso' => '2026-01-01', 'fecha_fin' => null],
        );

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'fecha_salida' => '2026-03-31',
        ]));

        $saldo = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => SaldoDeVacaciones::de($vinculacion->fresh())->al(Carbon::parse('2026-08-22')),
        );

        // Enero, febrero y marzo completos: 3,75 días, y nada después de irse.
        expect($saldo)->toEqualWithDelta(3.75, 0.0001);

        Carbon::setTestNow();
    });
});

describe('los motivos dependientes del tipo de salida', function () {
    it('ofrece los siete tipos con sus motivos', function () {
        $opciones = $this->actingAs($this->usuario)
            ->get('/terminaciones')
            ->viewData('page')['props']['opciones'];

        expect($opciones['tipos'])->toHaveCount(7)
            ->and($opciones['motivosPorTipo']['renuncia']['motivos'])->toHaveCount(11)
            ->and($opciones['motivosPorTipo']['renuncia']['motivos'][0])
            ->toBe('Mejor oferta laboral — salario')
            ->and($opciones['motivosPorTipo']['renuncia']['automatico'])->toBeNull();
    });

    it('no ofrece la pensión entre los motivos de renuncia, sino como justa causa', function () {
        $renuncia = TipoDeSalida::Renuncia->motivos();
        $justaCausa = TipoDeSalida::DespidoConJustaCausa->motivos();

        expect($renuncia)->not->toContain('Reconocimiento de pensión')
            ->and($justaCausa)->toContain('Reconocimiento de pensión');
    });

    it('no guarda un motivo que no es de ese tipo de salida', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'tipo_salida' => 'despido_con_justa_causa',
            'motivo' => 'Estudios',
        ]))->assertSessionHasErrors([
            'motivo' => 'Ese motivo no corresponde al tipo de salida elegido.',
        ]);
    });

    it('exige el motivo en los tipos que no lo autocompletan', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'motivo' => '',
        ]))->assertSessionHasErrors(['motivo' => 'Elige el motivo de la baja.']);
    });

    it('no registra con un tipo de salida que no es ninguno de los siete', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'tipo_salida' => 'abandono',
        ]))->assertSessionHasErrors('tipo_salida');
    });
});

describe('el autocompletado de los dos tipos sin submotivos', function () {
    it('completa el motivo de la expiración del plazo aunque no llegue ninguno', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '80112334']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'tipo_salida' => 'expiracion_del_plazo',
            'motivo' => '',
        ]));

        $terminacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        expect($terminacion->motivo)->toBe('Expiración del plazo fijo pactado');
    });

    it('ignora el motivo que llegue en la obra terminada', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '80112334']);

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            'tipo_salida' => 'obra_terminada',
            'motivo' => 'Estudios',
        ]));

        $terminacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->where('vinculacion_id', $vinculacion->id)->firstOrFail(),
        );

        expect($terminacion->motivo)->toBe('Finalización de la obra contratada');
    });

    it('declara cuál de los siete tipos autocompleta su motivo', function () {
        $automaticos = array_filter(
            TipoDeSalida::cases(),
            fn (TipoDeSalida $tipo): bool => $tipo->motivoAutomatico() !== null,
        );

        expect(array_values($automaticos))->toBe([
            TipoDeSalida::ExpiracionDelPlazo,
            TipoDeSalida::ObraTerminada,
        ]);
    });
});

describe('la categoría de rotación deducida', function () {
    it('agrupa los siete tipos como la tabla del documento 1, 7.4', function () {
        $categorias = [];

        foreach (TipoDeSalida::cases() as $tipo) {
            $categorias[$tipo->value] = $tipo->categoriaDeRotacion();
        }

        expect($categorias)->toBe([
            'renuncia' => CategoriaDeRotacion::Voluntaria,
            'mutuo_acuerdo' => CategoriaDeRotacion::Involuntaria,
            'periodo_de_prueba' => CategoriaDeRotacion::Involuntaria,
            'despido_con_justa_causa' => CategoriaDeRotacion::Involuntaria,
            'sin_justa_causa' => CategoriaDeRotacion::Involuntaria,
            'expiracion_del_plazo' => CategoriaDeRotacion::FinNatural,
            'obra_terminada' => CategoriaDeRotacion::FinNatural,
        ]);
    });

    it('no se guarda en ninguna columna: la fila la deduce del tipo', function () {
        expect(Schema::hasColumn('terminaciones', 'categoria_rotacion'))->toBeFalse();

        $vinculacion = empleadoParaNovedad(['numero_documento' => '1032447891']);

        $terminacion = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::factory()
                ->de($vinculacion)
                ->deTipo(TipoDeSalida::SinJustaCausa, 'Reestructuración o reducción de personal')
                ->create(),
        );

        $fila = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => ListadoDeTerminaciones::fila($terminacion),
        );

        expect($fila['rotacion'])->toBe('Involuntaria')
            ->and($fila['tono'])->toBe('advertencia');
    });
});

describe('la confirmación con la consecuencia', function () {
    it('dice el nombre del empleado, que pasará a inactivo y que dejarán de llegarle alertas', function () {
        $vinculacion = empleadoParaNovedad([
            'numero_documento' => '1015887432',
            'primer_nombre' => 'Carlos',
            'segundo_nombre' => 'Alberto',
            'primer_apellido' => 'Zapata',
            'segundo_apellido' => null,
        ]);

        $respuesta = $this->actingAs($this->usuario)
            ->get('/terminaciones?'.http_build_query(['vinculacion_id' => $vinculacion->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Terminaciones/Index',
                'X-Inertia-Partial-Data' => 'avisoDeTerminacion',
            ]);

        expect($respuesta->json()['props']['avisoDeTerminacion'])->toBe(
            'Al guardar, Carlos Alberto Zapata pasará a estado inactivo y el sistema dejará de '
            .'generar alertas sobre esta persona. El registro cierra el ciclo de esta vinculación.'
        );
    });

    it('no resuelve el aviso en la carga de la pantalla', function () {
        $props = $this->actingAs($this->usuario)->get('/terminaciones')->viewData('page')['props'];

        expect($props)->not->toHaveKey('avisoDeTerminacion');
    });
});

describe('el listado', function () {
    it('enseña las siete columnas del prototipo', function () {
        $vinculacion = empleadoParaNovedad(
            [
                'numero_documento' => '1015887432',
                'primer_nombre' => 'Carlos',
                'segundo_nombre' => null,
                'primer_apellido' => 'Zapata',
                'segundo_apellido' => null,
            ],
            ['cargo' => 'Auxiliar Contable'],
        );

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Terminacion::factory()
            ->de($vinculacion)
            ->deTipo(TipoDeSalida::Renuncia, 'Mejor oferta laboral — salario')
            ->create(['fecha_salida' => '2026-07-31']));

        $fila = $this->actingAs($this->usuario)
            ->get('/terminaciones')
            ->viewData('page')['props']['terminaciones']['filas'][0];

        expect($fila['documento'])->toBe('1015887432')
            ->and($fila['nombre'])->toBe('Carlos Zapata')
            ->and($fila['cargo'])->toBe('Auxiliar Contable')
            ->and($fila['salida'])->toBe('31/07/2026')
            ->and($fila['tipo'])->toBe('Renuncia')
            ->and($fila['motivo'])->toBe('Mejor oferta laboral — salario')
            ->and($fila['rotacion'])->toBe('Voluntaria');
    });

    it('filtra por tipo de salida y lo dice en la barra', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Terminacion::factory()->de($vinculacion)->deTipo(TipoDeSalida::Renuncia)->create();
            Terminacion::factory()->de($vinculacion)->deTipo(TipoDeSalida::SinJustaCausa)->create();
        });

        $props = $this->actingAs($this->usuario)
            ->get('/terminaciones?tipo=sin_justa_causa')
            ->viewData('page')['props'];

        expect($props['terminaciones']['total'])->toBe(1)
            ->and($props['terminaciones']['filas'][0]['tipo'])->toBe('Terminación sin justa causa')
            ->and($props['aplicados'])->toContain([
                'clave' => 'tipo',
                'texto' => 'Tipo: Terminación sin justa causa',
            ]);
    });

    it('filtra por motivo de la baja', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Terminacion::factory()->de($vinculacion)->deTipo(TipoDeSalida::Renuncia, 'Estudios')->create();
            Terminacion::factory()->de($vinculacion)->deTipo(TipoDeSalida::Renuncia, 'Motivos de salud')->create();
        });

        $props = $this->actingAs($this->usuario)
            ->get('/terminaciones?'.http_build_query(['motivo' => 'Estudios']))
            ->viewData('page')['props'];

        expect($props['terminaciones']['total'])->toBe(1)
            ->and($props['terminaciones']['filas'][0]['motivo'])->toBe('Estudios')
            ->and($props['aplicados'])->toContain(['clave' => 'motivo', 'texto' => 'Motivo: Estudios']);
    });

    it('filtra por rango de fecha de salida', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        app(ContextoTenant::class)->paraTenant($this->empresa, function () use ($vinculacion) {
            Terminacion::factory()->de($vinculacion)->create(['fecha_salida' => '2026-07-31']);
            Terminacion::factory()->de($vinculacion)->create(['fecha_salida' => '2026-08-15']);
        });

        $props = $this->actingAs($this->usuario)
            ->get('/terminaciones?desde=2026-08-01')
            ->viewData('page')['props'];

        expect($props['terminaciones']['total'])->toBe(1)
            ->and($props['terminaciones']['filas'][0]['salida'])->toBe('15/08/2026');
    });
});

/**
 * REV-03, la decisión de David: «eliminar es volver al pasado». Borrar la salida
 * devuelve al empleado a activo, con la fecha de fin intacta y con todas sus
 * alertas, igual que si el despido nunca se hubiera registrado.
 */
describe('corregir y eliminar (REV-03)', function () {
    /**
     * Registra la salida por la ruta de verdad y devuelve la fila creada.
     */
    function registrarTerminacionDe(Vinculacion $vinculacion, array $cambios = []): Terminacion
    {
        test()->actingAs(test()->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $vinculacion->id,
            ...$cambios,
        ]))->assertRedirect('/terminaciones');

        return app(ContextoTenant::class)->paraTenant(
            test()->empresa,
            fn () => Terminacion::query()->orderByDesc('id')->firstOrFail(),
        );
    }

    /**
     * El «hecho cuando» de la tarea, primera mitad: el empleado queda como
     * estaba antes de que la salida existiera.
     */
    it('eliminar la salida devuelve al empleado a activo y sin fecha de salida', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $terminacion = registrarTerminacionDe($vinculacion);

        $this->actingAs($this->usuario)
            ->delete('/terminaciones/'.$terminacion->id)
            ->assertRedirect('/terminaciones')
            ->assertSessionHas('estado', 'Terminación eliminada. El empleado volvió a estar activo.');

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect($actualizada->estaActiva())->toBeTrue()
            ->and($actualizada->fechaDeSalida())->toBeNull()
            // La fila sigue ahí, marcada: nunca hay borrado físico (regla 4).
            ->and(Terminacion::withoutGlobalScopes()->count())->toBe(1)
            ->and(Terminacion::withoutGlobalScopes()->whereNotNull('deleted_at')->count())->toBe(1);
    });

    /**
     * El «hecho cuando» de la tarea, segunda mitad: «con él vuelven todas sus
     * alertas». Es la promesa que vende el producto, así que se comprueba
     * contra el motor y no contra el estado.
     */
    it('con el empleado vuelven sus alertas, que la salida había apagado', function () {
        $vinculacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->conIngreso('2026-04-15')
            ->de(Persona::factory()->create(['numero_documento' => '1015887432']))
            ->create());

        expect(alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14'))->toHaveCount(1);

        $terminacion = registrarTerminacionDe($vinculacion, ['fecha_salida' => '2026-05-02']);

        expect(alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14'))->toBe([]);

        $this->actingAs($this->usuario)->delete('/terminaciones/'.$terminacion->id);

        expect(alertasDe(new PruebaIndefinidoUnMes, $this->empresa, '2026-05-14'))->toHaveCount(1);
    });

    /**
     * «Volver al pasado» es dejar al empleado como estaba, no borrarle datos que
     * la salida nunca tocó: en un término fijo, `fecha_fin` es hasta cuándo se
     * pactó el contrato, y eso ya estaba escrito antes de que nadie se fuera.
     */
    it('no borra la fecha hasta la que estaba pactado un contrato a término fijo', function () {
        $vinculacion = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Vinculacion::factory()
            ->aTerminoFijo('2026-01-15', '2026-12-31')
            ->de(Persona::factory()->create(['numero_documento' => '1015887432']))
            ->create());

        $terminacion = registrarTerminacionDe($vinculacion, ['fecha_salida' => '2026-07-31']);

        $this->actingAs($this->usuario)->delete('/terminaciones/'.$terminacion->id);

        $actualizada = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => $vinculacion->fresh());

        expect($actualizada->getAttribute('fecha_fin')->toDateString())->toBe('2026-12-31');
    });

    it('deja registrar otra salida después de haber deshecho la primera', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $primera = registrarTerminacionDe($vinculacion);

        $this->actingAs($this->usuario)->delete('/terminaciones/'.$primera->id);

        $segunda = registrarTerminacionDe($vinculacion, [
            'tipo_salida' => 'mutuo_acuerdo',
            'motivo' => 'Acuerdo económico de retiro',
        ]);

        expect($segunda->tipo_salida)->toBe(TipoDeSalida::MutuoAcuerdo)
            ->and($segunda->id)->not->toBe($primera->id);
    });

    it('corregir la salida cambia el tipo, el motivo y la categoría de rotación deducida', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $terminacion = registrarTerminacionDe($vinculacion);

        $this->actingAs($this->usuario)
            ->put('/terminaciones/'.$terminacion->id, datosDeTerminacion([
                'tipo_salida' => 'sin_justa_causa',
                'motivo' => 'Reestructuración o reducción de personal',
                'fecha_salida' => '2026-08-15',
                'observaciones' => 'Se corrigió el tipo de salida.',
            ]))
            ->assertRedirect('/terminaciones')
            ->assertSessionHas('estado', 'Terminación actualizada.');

        [$corregida, $actualizada] = app(ContextoTenant::class)->paraTenant($this->empresa, fn () => [
            Terminacion::query()->findOrFail($terminacion->id),
            $vinculacion->fresh(),
        ]);

        expect($corregida->tipo_salida)->toBe(TipoDeSalida::SinJustaCausa)
            ->and($corregida->motivo)->toBe('Reestructuración o reducción de personal')
            ->and($corregida->categoriaDeRotacion())->toBe(CategoriaDeRotacion::Involuntaria)
            ->and($corregida->observaciones)->toBe('Se corrigió el tipo de salida.')
            // Corregir la salida no revive a nadie, y la fecha que manda es la
            // que acaba de quedar registrada.
            ->and($actualizada->estaActiva())->toBeFalse()
            ->and($actualizada->fechaDeSalida()->toDateString())->toBe('2026-08-15');
    });

    it('sigue autocompletando el motivo de los dos tipos que no lo preguntan', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $terminacion = registrarTerminacionDe($vinculacion);

        $this->actingAs($this->usuario)->put('/terminaciones/'.$terminacion->id, datosDeTerminacion([
            'tipo_salida' => 'expiracion_del_plazo',
            'motivo' => 'Un motivo que nadie escribió',
        ]));

        $corregida = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->findOrFail($terminacion->id),
        );

        expect($corregida->motivo)->toBe(TipoDeSalida::ExpiracionDelPlazo->motivoAutomatico());
    });

    it('no corrige con un motivo que no es del tipo elegido', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $terminacion = registrarTerminacionDe($vinculacion);

        $this->actingAs($this->usuario)
            ->put('/terminaciones/'.$terminacion->id, datosDeTerminacion([
                'tipo_salida' => 'mutuo_acuerdo',
                'motivo' => 'Estudios',
            ]))
            ->assertSessionHasErrors('motivo');

        $sinTocar = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Terminacion::query()->findOrFail($terminacion->id),
        );

        expect($sinTocar->tipo_salida)->toBe(TipoDeSalida::Renuncia);
    });

    it('la confirmación dice que el empleado vuelve a estar activo y a recibir alertas', function () {
        $vinculacion = empleadoParaNovedad([
            'numero_documento' => '1015887432',
            'primer_nombre' => 'Carlos',
            'segundo_nombre' => null,
            'primer_apellido' => 'Ramírez',
            'segundo_apellido' => null,
        ]);

        $terminacion = registrarTerminacionDe($vinculacion, ['fecha_salida' => '2026-07-31']);

        $frase = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => $terminacion->confirmacionDeBorrado(),
        );

        expect($frase)->toBe(
            'Vas a eliminar la terminación del 31 de julio de 2026 de Carlos Ramírez. '
            .'Carlos Ramírez volverá a estar activo y a recibir alertas. '
            .'Esta acción queda registrada en la auditoría.'
        );
    });

    it('la pantalla trae el detalle de la salida solo cuando se le pide', function () {
        $vinculacion = empleadoParaNovedad(['numero_documento' => '1015887432']);

        $terminacion = registrarTerminacionDe($vinculacion, [
            'observaciones' => 'Entrega el puesto el viernes.',
        ]);

        expect($this->actingAs($this->usuario)->get('/terminaciones')->viewData('page')['props'])
            ->not->toHaveKey('novedad');

        $detalle = $this->actingAs($this->usuario)
            ->get('/terminaciones?'.http_build_query(['novedad' => $terminacion->id]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Terminaciones/Index',
                'X-Inertia-Partial-Data' => 'novedad',
            ])
            ->json()['props']['novedad'];

        expect($detalle['id'])->toBe($terminacion->id)
            ->and($detalle['valores']['tipo_salida'])->toBe('renuncia')
            ->and($detalle['valores']['motivo'])->toBe('Mejor oferta laboral — salario')
            ->and($detalle['valores']['fecha_salida'])->toBe('2026-07-31')
            ->and($detalle['valores']['observaciones'])->toBe('Entrega el puesto el viernes.')
            // Una salida no tiene ningún campo de solo lectura que contar.
            ->and($detalle['contexto'])->toBe([])
            ->and($detalle['datos'])->not->toBeEmpty();
    });
});

describe('el permiso del módulo', function () {
    it('responde 403 a quien no tiene el módulo de terminaciones asignado', function () {
        $sinPermiso = usuarioDe($this->empresa, Rol::Administrador, Modulo::Empleados);

        $this->actingAs($sinPermiso)->get('/terminaciones')->assertForbidden();
        $this->actingAs($sinPermiso)->get('/terminaciones/exportar')->assertForbidden();
        $this->actingAs($sinPermiso)->post('/terminaciones', datosDeTerminacion())->assertForbidden();
    });
});

describe('la exportación', function () {
    it('trae documento, nombre, cargo y la categoría de rotación', function () {
        Excel::fake();

        $vinculacion = empleadoParaNovedad(
            [
                'numero_documento' => '1032447891',
                'primer_nombre' => 'Yeimy',
                'segundo_nombre' => 'Paola',
                'primer_apellido' => 'Suárez',
                'segundo_apellido' => null,
            ],
            ['cargo' => 'Auxiliar de Ventas'],
        );

        app(ContextoTenant::class)->paraTenant($this->empresa, fn () => Terminacion::factory()
            ->de($vinculacion)
            ->deTipo(TipoDeSalida::SinJustaCausa, 'Reestructuración o reducción de personal')
            ->create(['fecha_salida' => '2026-08-15']));

        $this->actingAs($this->usuario)->get('/terminaciones/exportar')->assertOk();

        Excel::assertDownloaded(
            'terminaciones-'.now()->format('Y-m-d').'.xlsx',
            function (TerminacionesExport $exportacion) {
                $fila = ListadoDeTerminaciones::fila($exportacion->collection()->first());

                return $fila['documento'] === '1032447891'
                    && $fila['nombre'] === 'Yeimy Paola Suárez'
                    && $fila['cargo'] === 'Auxiliar de Ventas'
                    && $fila['tipo'] === 'Terminación sin justa causa'
                    && $fila['motivo'] === 'Reestructuración o reducción de personal'
                    && $fila['rotacion'] === 'Involuntaria';
            }
        );
    });
});

describe('la advertencia de reingreso, que era la deuda de F1-05', function () {
    it('mide los 30 días contra la fecha de salida registrada', function () {
        $persona = app(ContextoTenant::class)->paraTenant($this->empresa, function (): Persona {
            $persona = Persona::factory()->create(['numero_documento' => '1017245880']);

            Vinculacion::factory()->de($persona)->create(['fecha_fin' => null]);

            return $persona;
        });

        $anterior = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => Vinculacion::query()->where('persona_id', $persona->getKey())->firstOrFail(),
        );

        $this->actingAs($this->usuario)->post('/terminaciones', datosDeTerminacion([
            'vinculacion_id' => $anterior->getKey(),
            'fecha_salida' => '2026-07-31',
        ]));

        $aviso = app(ContextoTenant::class)->paraTenant(
            $this->empresa,
            fn () => AdvertenciaDeReingreso::para('1017245880', '2026-08-10'),
        );

        // Diez días después de la salida: dentro de los 30 que exigen énfasis.
        expect($aviso)->not->toBeNull()
            ->and($aviso['reciente'])->toBeTrue();
    });
});
