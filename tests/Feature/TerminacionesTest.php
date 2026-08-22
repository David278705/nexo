<?php

use App\Autorizacion\Modulo;
use App\Autorizacion\Rol;
use App\Contratacion\AdvertenciaDeReingreso;
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
